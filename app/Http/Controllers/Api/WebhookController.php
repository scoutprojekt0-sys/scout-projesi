<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayment;
use App\Models\Payment;
use App\Models\PlayerBoost;
use App\Models\Subscription;
use App\Services\IyzicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use ApiResponds;

    public function __construct(
        private IyzicoService $iyzicoService
    ) {}

    public function stripe(Request $request): JsonResponse
    {
        $payload = (string) $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret', '');
        $toleranceSeconds = max(30, (int) config('services.stripe.webhook_tolerance_seconds', 300));

        if ($secret !== '' && ! $this->verifyStripeSignature($payload, $sigHeader, $secret, $toleranceSeconds)) {
            Log::warning('Stripe webhook: invalid signature', ['ip' => $request->ip()]);

            return $this->errorResponse('Invalid signature', 400, 'invalid_webhook_signature');
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return $this->errorResponse('Invalid payload', 400, 'invalid_webhook_payload');
        }

        $eventId = (string) ($event['id'] ?? '');
        if ($this->isDuplicateWebhook('stripe', $eventId)) {
            return $this->successResponse(null, 'Stripe webhook yinelenen event; atlandi.');
        }

        Log::info('Stripe webhook received', ['type' => $event['type'] ?? 'unknown']);

        match ($event['type'] ?? '') {
            'payment_intent.succeeded' => $this->handleStripePaymentSucceeded($event['data']['object'] ?? []),
            'customer.subscription.deleted' => $this->handleStripeSubscriptionCancelled($event['data']['object'] ?? []),
            'invoice.payment_failed' => $this->handleStripePaymentFailed($event['data']['object'] ?? []),
            default => null,
        };

        return $this->successResponse(null, 'Stripe webhook islendi.');
    }

    public function paypal(Request $request): JsonResponse
    {
        $payloadRaw = (string) $request->getContent();
        $payload = json_decode($payloadRaw, true);
        if (! is_array($payload)) {
            return $this->errorResponse('Invalid payload', 400, 'invalid_webhook_payload');
        }

        $webhookSecret = (string) config('services.paypal.webhook_secret', '');
        if ($webhookSecret !== '' && ! $this->verifyPayPalSignature($payloadRaw, (string) $request->header('PayPal-Transmission-Sig', ''), $webhookSecret)) {
            Log::warning('PayPal webhook: invalid signature', ['ip' => $request->ip()]);

            return $this->errorResponse('Invalid signature', 400, 'invalid_webhook_signature');
        }

        $eventId = (string) ($payload['id'] ?? '');
        if ($this->isDuplicateWebhook('paypal', $eventId)) {
            return $this->successResponse(null, 'PayPal webhook yinelenen event; atlandi.');
        }

        $eventType = $payload['event_type'] ?? 'unknown';

        Log::info('PayPal webhook received', ['event_type' => $eventType]);

        match ($eventType) {
            'PAYMENT.SALE.COMPLETED' => $this->handlePayPalPaymentCompleted($payload),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handlePayPalSubscriptionCancelled($payload),
            'PAYMENT.SALE.DENIED' => $this->handlePayPalPaymentDenied($payload),
            default => null,
        };

        return $this->successResponse(null, 'PayPal webhook islendi.');
    }

    public function iyzico(Request $request): JsonResponse
    {
        if ($request->has('token')) {
            return $this->handleIyzicoCallbackToken($request);
        }

        $payload = $request->all();
        if (! is_array($payload) || $payload === []) {
            return $this->errorResponse('Invalid payload', 400, 'invalid_webhook_payload');
        }

        $signature = (string) $request->header('X-IYZ-SIGNATURE-V3', '');
        if ($signature !== '' && ! $this->iyzicoService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('iyzico webhook: invalid signature', ['ip' => $request->ip()]);

            return $this->errorResponse('Invalid signature', 400, 'invalid_webhook_signature');
        }

        $eventId = (string) ($payload['iyziEventTime'] ?? $payload['paymentConversationId'] ?? '');
        if ($this->isDuplicateWebhook('iyzico', $eventId)) {
            return $this->successResponse(null, 'iyzico webhook yinelenen event; atlandi.');
        }

        $payment = $this->resolveIyzicoPayment(
            (string) ($payload['paymentConversationId'] ?? ''),
            (string) ($payload['iyziPaymentId'] ?? $payload['paymentId'] ?? '')
        );

        if (! $payment) {
            return $this->errorResponse('Odeme bulunamadi', 404, 'payment_not_found');
        }

        $status = strtoupper((string) ($payload['status'] ?? 'FAILURE'));
        $providerPaymentId = (string) ($payload['iyziPaymentId'] ?? $payload['paymentId'] ?? '');

        if ($status === 'SUCCESS') {
            $this->completeIyzicoPayment($payment, $providerPaymentId);
        } else {
            $this->failIyzicoPayment($payment, (string) ($payload['errorMessage'] ?? 'iyzico payment failed'));
        }

        return $this->successResponse(null, 'iyzico webhook islendi.');
    }

    private function handleIyzicoCallbackToken(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');
        if ($token === '') {
            return $this->errorResponse('Invalid payload', 400, 'invalid_webhook_payload');
        }

        try {
            $result = $this->iyzicoService->retrieveCheckoutResult($token, (string) $request->input('conversationId', ''));
        } catch (\Throwable $exception) {
            Log::warning('iyzico callback retrieve failed', ['message' => $exception->getMessage()]);

            return $this->errorResponse($exception->getMessage(), 502, 'iyzico_callback_retrieve_failed');
        }

        $payment = $this->resolveIyzicoPayment(
            (string) ($result['conversationId'] ?? ''),
            (string) ($result['paymentId'] ?? '')
        );

        if (! $payment) {
            return $this->errorResponse('Odeme bulunamadi', 404, 'payment_not_found');
        }

        $status = strtoupper((string) ($result['paymentStatus'] ?? 'FAILURE'));
        if ($status === 'SUCCESS') {
            $this->completeIyzicoPayment($payment, (string) ($result['paymentId'] ?? ''));
        } else {
            $this->failIyzicoPayment($payment, (string) ($result['errorMessage'] ?? 'iyzico payment failed'));
        }

        return $this->successResponse([
            'payment_id' => $payment->id,
            'status' => strtolower($status),
        ], 'iyzico callback islendi.');
    }

    private function handleStripePaymentSucceeded(array $object): void
    {
        $stripePaymentId = $object['id'] ?? null;
        if (! $stripePaymentId) {
            return;
        }

        $payment = Payment::where('transaction_id', $stripePaymentId)
            ->where('payment_method', 'stripe')
            ->first();

        if ($payment && $payment->status !== 'completed') {
            ProcessPayment::dispatch($payment);
        }
    }

    private function handleStripeSubscriptionCancelled(array $object): void
    {
        $stripeSubId = $object['id'] ?? null;
        if (! $stripeSubId) {
            return;
        }

        Subscription::where('stripe_subscription_id', $stripeSubId)
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        Log::info('Stripe subscription cancelled via webhook', ['stripe_id' => $stripeSubId]);
    }

    private function handleStripePaymentFailed(array $object): void
    {
        $stripeSubId = $object['subscription'] ?? null;
        if (! $stripeSubId) {
            return;
        }

        Subscription::where('stripe_subscription_id', $stripeSubId)
            ->update(['status' => 'past_due']);

        Log::warning('Stripe payment failed via webhook', ['stripe_subscription_id' => $stripeSubId]);
    }

    private function verifyStripeSignature(string $payload, string $sigHeader, string $secret, int $toleranceSeconds): bool
    {
        try {
            $parts = explode(',', $sigHeader);
            $timestamp = null;
            $signatures = [];

            foreach ($parts as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
                if ($key === 't') {
                    $timestamp = $value;
                } elseif ($key === 'v1') {
                    $signatures[] = $value;
                }
            }

            if (! $timestamp || empty($signatures)) {
                return false;
            }

            $timestampInt = (int) $timestamp;
            if ($timestampInt <= 0 || abs(now()->timestamp - $timestampInt) > $toleranceSeconds) {
                return false;
            }

            $signedPayload = $timestamp.'.'.$payload;
            $expected = hash_hmac('sha256', $signedPayload, $secret);

            foreach ($signatures as $sig) {
                if (hash_equals($expected, $sig)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function handlePayPalPaymentCompleted(array $payload): void
    {
        $paypalTxId = $payload['resource']['id'] ?? null;
        if (! $paypalTxId) {
            return;
        }

        $payment = Payment::where('transaction_id', $paypalTxId)
            ->where('payment_method', 'paypal')
            ->first();

        if ($payment && $payment->status !== 'completed') {
            ProcessPayment::dispatch($payment);
        }
    }

    private function handlePayPalSubscriptionCancelled(array $payload): void
    {
        $paypalSubId = $payload['resource']['id'] ?? null;
        if (! $paypalSubId) {
            return;
        }

        Subscription::where('paypal_subscription_id', $paypalSubId)
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        Log::info('PayPal subscription cancelled via webhook', ['paypal_id' => $paypalSubId]);
    }

    private function handlePayPalPaymentDenied(array $payload): void
    {
        $paypalSubId = $payload['resource']['billing_agreement_id'] ?? null;
        if (! $paypalSubId) {
            return;
        }

        Subscription::where('paypal_subscription_id', $paypalSubId)
            ->update(['status' => 'past_due']);

        Log::warning('PayPal payment denied via webhook', ['paypal_subscription_id' => $paypalSubId]);
    }

    private function verifyPayPalSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        $signature = trim($signatureHeader);
        if ($signature === '') {
            return false;
        }

        $expectedHex = hash_hmac('sha256', $payload, $secret);
        $expectedBase64 = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expectedHex, $signature) || hash_equals($expectedBase64, $signature);
    }

    private function completeIyzicoPayment(Payment $payment, string $providerPaymentId): void
    {
        $payment->update([
            'transaction_id' => $providerPaymentId !== '' ? $providerPaymentId : $payment->transaction_id,
            'metadata' => array_merge($payment->metadata ?? [], [
                'provider_status' => 'success',
            ]),
        ]);

        if ($payment->status !== 'completed') {
            ProcessPayment::dispatchSync($payment->fresh());
        }
    }

    private function failIyzicoPayment(Payment $payment, string $message): void
    {
        $payment->update([
            'status' => 'failed',
            'metadata' => array_merge($payment->metadata ?? [], [
                'provider_status' => 'failed',
                'provider_error' => $message,
            ]),
        ]);

        PlayerBoost::where('payment_id', $payment->id)
            ->update(['status' => 'failed']);
    }

    private function resolveIyzicoPayment(string $conversationId, string $providerPaymentId): ?Payment
    {
        $localPaymentId = $this->iyzicoService->extractPaymentIdFromConversationId($conversationId);
        if ($localPaymentId !== null) {
            return Payment::find($localPaymentId);
        }

        if ($providerPaymentId !== '') {
            return Payment::where('transaction_id', $providerPaymentId)->first();
        }

        return null;
    }

    private function isDuplicateWebhook(string $provider, string $eventId): bool
    {
        if ($eventId === '') {
            return false;
        }

        $cacheKey = sprintf('webhook:%s:event:%s', $provider, $eventId);

        return ! Cache::add($cacheKey, 1, now()->addDay());
    }
}
