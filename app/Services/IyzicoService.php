<?php

namespace App\Services;

use App\Models\BoostPackage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class IyzicoService
{
    private string $apiKey = '';
    private string $secretKey = '';
    private string $baseUrl = '';
    private string $callbackUrl = '';
    private string $defaultIdentityNumber = '';

    public function __construct()
    {
        $this->apiKey = (string) config('services.iyzico.api_key', '');
        $this->secretKey = (string) config('services.iyzico.secret_key', '');
        $this->baseUrl = rtrim((string) config('services.iyzico.base_url', 'https://sandbox-api.iyzipay.com'), '/');
        $this->callbackUrl = (string) config('services.iyzico.callback_url', rtrim((string) config('app.url', ''), '/').'/api/webhooks/iyzico');
        $this->defaultIdentityNumber = (string) config('services.iyzico.default_identity_number', '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->secretKey !== '' && $this->callbackUrl !== '';
    }

    public function initializeBoostCheckout(User $user, BoostPackage $package, Payment $payment): array
    {
        $this->ensureConfigured();

        $uriPath = '/payment/iyzipos/checkoutform/initialize/auth/ecom';
        $conversationId = $this->conversationIdForPayment($payment);
        $price = number_format((float) $package->price, 2, '.', '');
        $body = [
            'locale' => 'tr',
            'conversationId' => $conversationId,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => $package->currency,
            'basketId' => 'boost-'.$payment->id,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => $this->callbackUrl,
            'enabledInstallments' => [1],
            'buyer' => $this->buyerPayload($user),
            'billingAddress' => $this->addressPayload($user),
            'shippingAddress' => $this->addressPayload($user),
            'basketItems' => [[
                'id' => 'boost-package-'.$package->id,
                'price' => $price,
                'name' => $package->name,
                'category1' => 'Scout Visibility',
                'category2' => 'Player Boost',
                'itemType' => 'VIRTUAL',
            ]],
        ];

        $response = Http::withHeaders($this->authHeaders($uriPath, $body))
            ->acceptJson()
            ->post($this->baseUrl.$uriPath, $body);

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload) || ($payload['status'] ?? 'failure') !== 'success') {
            throw new RuntimeException((string) ($payload['errorMessage'] ?? 'iyzico checkout initialize failed.'));
        }

        return [
            'conversation_id' => $conversationId,
            'token' => $payload['token'] ?? null,
            'payment_page_url' => $payload['paymentPageUrl'] ?? null,
            'checkout_form_content' => $payload['checkoutFormContent'] ?? null,
            'raw' => $payload,
        ];
    }

    public function retrieveCheckoutResult(string $token, ?string $conversationId = null): array
    {
        $this->ensureConfigured();

        $uriPath = '/payment/iyzipos/checkoutform/auth/ecom/detail';
        $body = [
            'locale' => 'tr',
            'token' => $token,
        ];

        if ($conversationId !== null && $conversationId !== '') {
            $body['conversationId'] = $conversationId;
        }

        $response = Http::withHeaders($this->authHeaders($uriPath, $body))
            ->acceptJson()
            ->post($this->baseUrl.$uriPath, $body);

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload)) {
            throw new RuntimeException('iyzico checkout retrieve failed.');
        }

        return $payload;
    }

    public function verifyWebhookSignature(array $payload, string $signatureHeader): bool
    {
        if (! $this->isConfigured() || trim($signatureHeader) === '') {
            return false;
        }

        $signatureHeader = trim($signatureHeader);
        $iyziEventType = (string) ($payload['iyziEventType'] ?? '');
        $status = (string) ($payload['status'] ?? '');

        if ($iyziEventType === '' || $status === '') {
            return false;
        }

        if (isset($payload['token'])) {
            $key = $this->secretKey
                .$iyziEventType
                .(string) ($payload['iyziPaymentId'] ?? '')
                .(string) ($payload['token'] ?? '')
                .(string) ($payload['paymentConversationId'] ?? '')
                .$status;
        } else {
            $key = $this->secretKey
                .$iyziEventType
                .(string) ($payload['iyziPaymentId'] ?? $payload['paymentId'] ?? '')
                .(string) ($payload['paymentConversationId'] ?? '')
                .$status;
        }

        $calculated = bin2hex(hash_hmac('sha256', $key, $this->secretKey, true));

        return hash_equals($calculated, $signatureHeader);
    }

    public function conversationIdForPayment(Payment $payment): string
    {
        return 'boost-payment-'.$payment->id;
    }

    public function extractPaymentIdFromConversationId(?string $conversationId): ?int
    {
        if (! is_string($conversationId)) {
            return null;
        }

        if (preg_match('/^boost-payment-(\d+)$/', trim($conversationId), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('iyzico is not configured. Set IYZICO_API_KEY, IYZICO_SECRET_KEY and IYZICO_CALLBACK_URL.');
        }
    }

    private function authHeaders(string $uriPath, array $body): array
    {
        $randomKey = (string) now()->getTimestampMs();
        $requestBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $randomKey.$uriPath.$requestBody, $this->secretKey);
        $authorization = base64_encode('apiKey:'.$this->apiKey.'&randomKey:'.$randomKey.'&signature:'.$signature);

        return [
            'Authorization' => 'IYZWSv2 '.$authorization,
            'x-iyzi-rnd' => $randomKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function buyerPayload(User $user): array
    {
        $nameParts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
        $firstName = $nameParts[0] ?? 'Scout';
        $surname = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'User';
        $city = (string) ($user->city ?: 'Istanbul');
        $country = (string) ($user->country ?: 'Turkey');

        return [
            'id' => (string) $user->id,
            'name' => Str::limit($firstName, 50, ''),
            'surname' => Str::limit($surname, 50, ''),
            'identityNumber' => $this->defaultIdentityNumber !== '' ? $this->defaultIdentityNumber : '11111111111',
            'email' => $user->email,
            'gsmNumber' => $this->normalizePhone((string) ($user->phone ?: '+905000000000')),
            'registrationDate' => optional($user->created_at)->format('Y-m-d H:i:s') ?? now()->subMonth()->format('Y-m-d H:i:s'),
            'lastLoginDate' => now()->format('Y-m-d H:i:s'),
            'registrationAddress' => $city.' / '.$country,
            'city' => $city,
            'country' => $country,
            'zipCode' => '34000',
            'ip' => request()->ip() ?: '127.0.0.1',
        ];
    }

    private function addressPayload(User $user): array
    {
        $city = (string) ($user->city ?: 'Istanbul');
        $country = (string) ($user->country ?: 'Turkey');

        return [
            'address' => $city.' / '.$country,
            'zipCode' => '34000',
            'contactName' => $user->name,
            'city' => $city,
            'country' => $country,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '+905000000000';
        }

        if (str_starts_with($digits, '90')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+9'.$digits;
        }

        return '+90'.$digits;
    }
}
