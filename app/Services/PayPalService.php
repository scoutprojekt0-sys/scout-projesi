<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalService
{
    private string $clientId = '';
    private string $secret = '';
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = (string) config('services.paypal.client_id', '');
        $this->secret = (string) config('services.paypal.secret', '');
        $mode = config('services.paypal.mode', 'sandbox');
        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function ensureConfigured(): void
    {
        if ($this->clientId === '' || $this->secret === '') {
            throw new RuntimeException('PayPal is not configured. Set PAYPAL_CLIENT_ID and PAYPAL_SECRET in your .env file.');
        }
    }

    private function getAccessToken(): string
    {
        $this->ensureConfigured();

        $response = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        return $response->json()['access_token'];
    }

    public function createSubscription($user, $plan): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post($this->baseUrl . '/v1/billing/subscriptions', [
            'plan_id' => $plan->paypal_plan_id,
            'subscriber' => [
                'name' => ['given_name' => $user->name],
                'email_address' => $user->email,
            ],
            'application_context' => [
                'return_url' => config('app.url') . '/billing/success',
                'cancel_url' => config('app.url') . '/billing/cancel',
            ],
        ]);

        return $response->json();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post(
            $this->baseUrl . '/v1/billing/subscriptions/' . $subscriptionId . '/cancel',
            ['reason' => 'Customer request']
        );

        return ['status' => $response->successful() ? 'cancelled' : 'error'];
    }
}
