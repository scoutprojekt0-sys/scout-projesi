<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReleaseHardeningSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_sensitive_data_quality_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/data-quality/audit-log')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_admin_only');

        $this->getJson('/api/data-quality/conflicts')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_admin_only');

        $this->getJson('/api/data-quality/missing-source')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_admin_only');
    }

    public function test_admin_can_access_sensitive_data_quality_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['profile:read']);

        $this->getJson('/api/data-quality/conflicts')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('current_page', 1);
    }

    public function test_non_moderator_cannot_access_week10_anomaly_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'player', 'editor_role' => 'none']);
        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/moderation/high-risk')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_moderation_only');

        $this->postJson('/api/moderation/999/score')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_moderation_only');
    }

    public function test_stripe_webhook_replay_event_is_ignored_after_first_delivery(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test');
        Config::set('services.stripe.webhook_tolerance_seconds', 300);

        $payload = [
            'id' => 'evt_test_replay_1',
            'type' => 'unknown.event',
            'data' => ['object' => []],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->stripeSignature($jsonPayload, 'whsec_test', now()->timestamp);

        $this->withHeader('Stripe-Signature', $signature)
            ->postJson('/api/webhooks/stripe', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->withHeader('Stripe-Signature', $signature)
            ->postJson('/api/webhooks/stripe', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Stripe webhook yinelenen event; atlandi.');
    }

    public function test_paypal_webhook_requires_valid_signature_when_secret_is_set(): void
    {
        Config::set('services.paypal.webhook_secret', 'paypal_test_secret');

        $payload = [
            'id' => 'WH-TEST-1',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'resource' => ['id' => 'TX-1'],
        ];

        $this->withHeader('PayPal-Transmission-Sig', 'invalid-signature')
            ->postJson('/api/webhooks/paypal', $payload)
            ->assertStatus(400)
            ->assertJsonPath('code', 'invalid_webhook_signature');
    }

    public function test_iyzico_webhook_requires_valid_signature_when_secret_is_set(): void
    {
        Config::set('services.iyzico.api_key', 'iyzi-api-key');
        Config::set('services.iyzico.secret_key', 'iyzi-secret-key');
        Config::set('services.iyzico.callback_url', 'https://api.example.com/api/webhooks/iyzico');

        $payload = [
            'iyziEventType' => 'PAYMENT_API',
            'iyziPaymentId' => 'iyzi-payment-1',
            'paymentConversationId' => 'boost-payment-1',
            'status' => 'SUCCESS',
        ];

        $this->withHeader('X-IYZ-SIGNATURE-V3', 'invalid-signature')
            ->postJson('/api/webhooks/iyzico', $payload)
            ->assertStatus(400)
            ->assertJsonPath('code', 'invalid_webhook_signature');
    }

    private function stripeSignature(string $payload, string $secret, int $timestamp): string
    {
        $signedPayload = $timestamp.'.'.$payload;
        $hmac = hash_hmac('sha256', $signedPayload, $secret);

        return 't='.$timestamp.',v1='.$hmac;
    }
}
