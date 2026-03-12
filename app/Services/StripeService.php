<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeService
{
    private string $apiKey = '';
    private string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('services.stripe.secret', '');
    }

    private function ensureConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in your .env file.');
        }
    }

    public function createCustomer($user): array
    {
        $this->ensureConfigured();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->asForm()->post($this->baseUrl . '/customers', [
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => $user->id],
        ]);

        return $response->json();
    }

    public function createSubscription($user, $plan): array
    {
        if (!$user->stripe_customer_id) {
            $customer = $this->createCustomer($user);
            $user->update(['stripe_customer_id' => $customer['id']]);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->asForm()->post($this->baseUrl . '/subscriptions', [
            'customer' => $user->stripe_customer_id,
            'items' => [['price' => $plan->stripe_price_id]],
        ]);

        return $response->json();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $this->ensureConfigured();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->delete($this->baseUrl . '/subscriptions/' . $subscriptionId);

        return $response->json();
    }
}
