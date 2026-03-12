<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayment;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use ApiResponds;
    // -------------------------------------------------------
    // Stripe Webhook
    // -------------------------------------------------------
    public function stripe(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret    = (string) config('services.stripe.webhook_secret', '');

        // İmza doğrulama
        if ($secret !== '' && ! $this->verifyStripeSignature($payload, $sigHeader, $secret)) {
            Log::warning('Stripe webhook: invalid signature', ['ip' => $request->ip()]);

            return $this->errorResponse('Invalid signature', 400, 'invalid_webhook_signature');
        }

        $event = json_decode($payload, true);
        $type  = $event['data']['object'] ?? [];

        Log::info('Stripe webhook received', ['type' => $event['type'] ?? 'unknown']);

        match ($event['type'] ?? '') {
            'payment_intent.succeeded'    => $this->handleStripePaymentSucceeded($event['data']['object']),
            'customer.subscription.deleted' => $this->handleStripeSubscriptionCancelled($event['data']['object']),
            'invoice.payment_failed'      => $this->handleStripePaymentFailed($event['data']['object']),
            default                       => null,
        };

        return $this->successResponse(null, 'Stripe webhook islendi.');
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

    private function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
    {
        try {
            $parts     = explode(',', $sigHeader);
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

            $signedPayload = $timestamp . '.' . $payload;
            $expected      = hash_hmac('sha256', $signedPayload, $secret);

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

    // -------------------------------------------------------
    // PayPal Webhook
    // -------------------------------------------------------
    public function paypal(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? 'unknown';

        Log::info('PayPal webhook received', ['event_type' => $eventType]);

        match ($eventType) {
            'PAYMENT.SALE.COMPLETED'        => $this->handlePayPalPaymentCompleted($payload),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handlePayPalSubscriptionCancelled($payload),
            'PAYMENT.SALE.DENIED'           => $this->handlePayPalPaymentDenied($payload),
            default                         => null,
        };

        return $this->successResponse(null, 'PayPal webhook islendi.');
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
}
