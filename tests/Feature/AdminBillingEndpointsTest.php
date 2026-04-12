<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBillingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_billing_endpoints_expose_minimized_payment_and_subscription_payloads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create([
            'role' => 'player',
            'name' => 'Paid User',
            'email' => 'paid@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Scout Pro',
            'slug' => 'scout-pro',
            'price' => 199.00,
            'currency' => 'TRY',
            'billing_cycle' => 'monthly',
            'features' => ['priority_support' => true],
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'user_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'stripe_subscription_id' => 'sub_secret_123',
            'paypal_subscription_id' => 'paypal_secret_456',
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        Payment::query()->create([
            'user_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'boost_package_id' => null,
            'amount' => 199.00,
            'currency' => 'TRY',
            'payment_method' => 'stripe',
            'payment_context' => 'subscription',
            'transaction_id' => 'txn_secret_123',
            'status' => 'completed',
            'metadata' => [
                'card_last4' => '4242',
                'provider_token' => 'secret-token',
            ],
        ]);

        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->getJson('/api/admin/billing/payments')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.user.name', 'Paid User')
            ->assertJsonMissingPath('data.data.0.user.email')
            ->assertJsonMissingPath('data.data.0.transaction_id')
            ->assertJsonMissingPath('data.data.0.metadata');

        $this->getJson('/api/admin/billing/subscriptions')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.user.name', 'Paid User')
            ->assertJsonPath('data.data.0.plan.slug', 'scout-pro')
            ->assertJsonMissingPath('data.data.0.user.email')
            ->assertJsonMissingPath('data.data.0.stripe_subscription_id')
            ->assertJsonMissingPath('data.data.0.paypal_subscription_id');
    }
}
