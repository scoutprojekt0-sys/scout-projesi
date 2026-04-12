<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayment;
use App\Models\BoostPackage;
use App\Models\Payment;
use App\Models\PlayerBoost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoostBillingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_active_boost_packages(): void
    {
        $this->getJson('/api/billing/boost-packages')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'daily-boost')
            ->assertJsonPath('data.1.slug', 'weekly-boost')
            ->assertJsonPath('data.2.slug', 'monthly-boost');
    }

    public function test_player_can_start_boost_purchase(): void
    {
        $player = User::factory()->player()->create();
        $package = BoostPackage::query()->create([
            'name' => 'Aylik Vitrin',
            'slug' => 'aylik-vitrin',
            'price' => 1299.00,
            'currency' => 'TRY',
            'duration_days' => 30,
            'discover_score' => 25,
            'active' => true,
        ]);

        Sanctum::actingAs($player, ['player']);

        $this->postJson('/api/billing/boost-purchase', [
            'boost_package_id' => $package->id,
            'payment_method' => 'iyzico',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.provider', 'iyzico')
            ->assertJsonPath('data.payment.payment_context', 'boost')
            ->assertJsonPath('data.boost.status', 'pending')
            ->assertJsonMissingPath('data.payment.metadata')
            ->assertJsonMissingPath('data.payment.transaction_id')
            ->assertJsonMissingPath('data.boost.metadata');

        $this->assertDatabaseHas('payments', [
            'user_id' => $player->id,
            'boost_package_id' => $package->id,
            'payment_context' => 'boost',
            'payment_method' => 'iyzico',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('player_boosts', [
            'user_id' => $player->id,
            'boost_package_id' => $package->id,
            'status' => 'pending',
        ]);
    }

    public function test_completed_boost_payment_activates_player_boost_and_status_history_endpoints(): void
    {
        $player = User::factory()->player()->create();
        $package = BoostPackage::query()->create([
            'name' => 'Scout Plus',
            'slug' => 'scout-plus',
            'price' => 899.00,
            'currency' => 'TRY',
            'duration_days' => 14,
            'discover_score' => 15,
            'active' => true,
        ]);

        $payment = Payment::query()->create([
            'user_id' => $player->id,
            'subscription_id' => null,
            'boost_package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'TRY',
            'payment_method' => 'iyzico',
            'payment_context' => 'boost',
            'transaction_id' => 'iyzico_test_123',
            'status' => 'pending',
            'metadata' => ['purpose' => 'discover_boost'],
        ]);

        PlayerBoost::query()->create([
            'user_id' => $player->id,
            'boost_package_id' => $package->id,
            'payment_id' => $payment->id,
            'status' => 'pending',
            'metadata' => ['package_name' => $package->name],
        ]);

        (new ProcessPayment($payment))->handle();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('player_boosts', [
            'payment_id' => $payment->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($player, ['player']);

        $this->getJson('/api/billing/boost-status')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.package.slug', 'scout-plus');

        $this->getJson('/api/billing/boost-history')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.package.slug', 'scout-plus')
            ->assertJsonMissingPath('data.0.payment.metadata')
            ->assertJsonMissingPath('data.0.payment.transaction_id');
    }

    public function test_player_can_start_boost_purchase_with_configured_iyzico_checkout(): void
    {
        Config::set('services.iyzico.api_key', 'iyzi-api-key');
        Config::set('services.iyzico.secret_key', 'iyzi-secret-key');
        Config::set('services.iyzico.base_url', 'https://sandbox-api.iyzipay.com');
        Config::set('services.iyzico.callback_url', 'https://api.example.com/api/webhooks/iyzico');
        Config::set('services.iyzico.default_identity_number', '11111111111');

        Http::fake([
            'https://sandbox-api.iyzipay.com/payment/iyzipos/checkoutform/initialize/auth/ecom' => Http::response([
                'status' => 'success',
                'token' => 'checkout-token-1',
                'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com/checkout/form/token/checkout-token-1',
                'checkoutFormContent' => '<form></form>',
            ], 200),
        ]);

        $player = User::factory()->player()->create();
        $package = BoostPackage::query()->create([
            'name' => 'One Cik',
            'slug' => 'one-cik',
            'price' => 299.00,
            'currency' => 'TRY',
            'duration_days' => 7,
            'discover_score' => 12,
            'active' => true,
        ]);

        Sanctum::actingAs($player, ['player']);

        $this->postJson('/api/billing/boost-purchase', [
            'boost_package_id' => $package->id,
            'payment_method' => 'iyzico',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.provider_status', 'checkout_ready')
            ->assertJsonPath('data.payment_page_url', 'https://sandbox-cpp.iyzipay.com/checkout/form/token/checkout-token-1')
            ->assertJsonPath('data.checkout_token', 'checkout-token-1');

        $payment = Payment::query()->latest('id')->firstOrFail();

        $this->assertSame('checkout-token-1', $payment->metadata['checkout_token']);
        $this->assertSame('boost-payment-'.$payment->id, $payment->metadata['conversation_id']);
    }

    public function test_iyzico_callback_completes_pending_boost_payment(): void
    {
        Config::set('services.iyzico.api_key', 'iyzi-api-key');
        Config::set('services.iyzico.secret_key', 'iyzi-secret-key');
        Config::set('services.iyzico.base_url', 'https://sandbox-api.iyzipay.com');
        Config::set('services.iyzico.callback_url', 'https://api.example.com/api/webhooks/iyzico');
        Config::set('services.iyzico.default_identity_number', '11111111111');

        Http::fake([
            'https://sandbox-api.iyzipay.com/payment/iyzipos/checkoutform/auth/ecom/detail' => Http::response([
                'status' => 'success',
                'paymentStatus' => 'SUCCESS',
                'paymentId' => 'iyzi-payment-123',
                'conversationId' => 'boost-payment-1',
            ], 200),
        ]);

        $player = User::factory()->player()->create();
        $package = BoostPackage::query()->create([
            'name' => 'Vitrin',
            'slug' => 'vitrin',
            'price' => 599.00,
            'currency' => 'TRY',
            'duration_days' => 10,
            'discover_score' => 20,
            'active' => true,
        ]);

        $payment = Payment::query()->create([
            'user_id' => $player->id,
            'subscription_id' => null,
            'boost_package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'TRY',
            'payment_method' => 'iyzico',
            'payment_context' => 'boost',
            'status' => 'pending',
            'metadata' => ['conversation_id' => 'boost-payment-1'],
        ]);

        PlayerBoost::query()->create([
            'user_id' => $player->id,
            'boost_package_id' => $package->id,
            'payment_id' => $payment->id,
            'status' => 'pending',
        ]);

        $this->postJson('/api/webhooks/iyzico', [
            'token' => 'checkout-token-1',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'success');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'transaction_id' => 'iyzi-payment-123',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('player_boosts', [
            'payment_id' => $payment->id,
            'status' => 'active',
        ]);
    }
}
