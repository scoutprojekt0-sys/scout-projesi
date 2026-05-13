<?php

namespace Tests\Feature;

use App\Models\DevicePushToken;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\FirebasePushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_send_triggers_push_delivery_service(): void
    {
        $sender = User::factory()->create(['role' => 'scout', 'name' => 'Sender']);
        $recipient = User::factory()->create(['role' => 'player', 'name' => 'Recipient']);

        UserNotificationPreference::query()->create([
            'user_id' => $recipient->id,
            'allow_match_alerts' => true,
            'allow_push_notifications' => true,
            'allow_inbox_push' => true,
            'allow_offer_alerts' => true,
        ]);

        DevicePushToken::query()->create([
            'user_id' => $recipient->id,
            'token' => 'device-token-123',
            'platform' => 'android',
            'notifications_enabled' => true,
            'last_seen_at' => now(),
        ]);

        $mock = Mockery::mock(FirebasePushService::class);
        $mock->shouldReceive('sendToUsers')
            ->once()
            ->withArgs(function ($userIds, $title, $body, $data, $preferenceKey) use ($recipient) {
                return $userIds === [$recipient->id]
                    && $title === 'Yeni mesaj'
                    && $body === 'Sender sana mesaj gonderdi.'
                    && ($data['route'] ?? null) === 'messages'
                    && ($data['type'] ?? null) === 'new_message'
                    && $preferenceKey === 'allow_inbox_push';
            });
        $this->app->instance(FirebasePushService::class, $mock);

        Sanctum::actingAs($sender, ['contact:write', 'contact:read']);

        $this->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'subject' => 'Hello',
            'message' => 'Test body',
        ])->assertCreated();
    }
}
