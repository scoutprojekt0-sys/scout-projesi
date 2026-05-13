<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DevicePushTokenEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_token_routes_require_authentication(): void
    {
        $this->postJson('/api/devices/push-tokens', [
            'token' => 'token-123',
        ])->assertStatus(401);

        $this->deleteJson('/api/devices/push-tokens', [
            'token' => 'token-123',
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_store_and_remove_push_token(): void
    {
        $user = User::factory()->player()->create();

        Sanctum::actingAs($user, ['profile:write']);

        $this->postJson('/api/devices/push-tokens', [
            'token' => 'token-123',
            'platform' => 'android',
            'notifications_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.token', 'token-123')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.notifications_enabled', true);

        $this->assertDatabaseHas('device_push_tokens', [
            'user_id' => $user->id,
            'token' => 'token-123',
            'platform' => 'android',
            'notifications_enabled' => true,
        ]);

        $this->deleteJson('/api/devices/push-tokens', [
            'token' => 'token-123',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('device_push_tokens', [
            'user_id' => $user->id,
            'token' => 'token-123',
        ]);
    }

    public function test_notification_preferences_include_push_flags(): void
    {
        $user = User::factory()->player()->create([
            'sport' => 'football',
            'city' => 'Istanbul',
        ]);

        Sanctum::actingAs($user, ['profile:read', 'profile:write']);

        $this->getJson('/api/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.allow_push_notifications', true)
            ->assertJsonPath('data.allow_inbox_push', true)
            ->assertJsonPath('data.allow_offer_alerts', true);

        $this->putJson('/api/notifications/preferences', [
            'allow_match_alerts' => false,
            'allow_push_notifications' => false,
            'allow_inbox_push' => false,
            'allow_offer_alerts' => true,
            'sport' => 'football',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
        ])
            ->assertOk()
            ->assertJsonPath('data.allow_match_alerts', false)
            ->assertJsonPath('data.allow_push_notifications', false)
            ->assertJsonPath('data.allow_inbox_push', false)
            ->assertJsonPath('data.allow_offer_alerts', true)
            ->assertJsonPath('data.district', 'Kadikoy');

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $user->id,
            'allow_match_alerts' => false,
            'allow_push_notifications' => false,
            'allow_inbox_push' => false,
            'allow_offer_alerts' => true,
            'district' => 'Kadikoy',
        ]);
    }
}
