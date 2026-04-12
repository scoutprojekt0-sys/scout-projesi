<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_notification_routes_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
        $this->patchJson('/api/notifications/1/read')->assertStatus(401);
        $this->postJson('/api/notifications/read-all')->assertStatus(401);
    }

    public function test_authenticated_user_sees_only_own_notifications_and_unread_meta(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $team = User::factory()->create(['role' => 'team']);

        DB::table('notifications')->insert([
            [
                'user_id' => $player->id,
                'type' => 'opportunity',
                'payload' => json_encode([
                    'actor_user_id' => 123,
                    'actor_name' => 'Scout One',
                    'secret_token' => 'hidden',
                    'email' => 'private@example.com',
                ]),
                'is_read' => false,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'user_id' => $player->id,
                'type' => 'message',
                'payload' => json_encode(['role' => 'player']),
                'is_read' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $team->id,
                'type' => 'contact',
                'payload' => json_encode(['role' => 'team']),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($player, ['profile:read']);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.type', 'message')
            ->assertJsonPath('data.1.type', 'opportunity')
            ->assertJsonPath('data.1.payload.actor_user_id', 123)
            ->assertJsonMissingPath('data.1.payload.secret_token')
            ->assertJsonMissingPath('data.1.payload.email');

        $this->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'opportunity')
            ->assertJsonPath('meta.filters.unread', true);
    }

    public function test_authenticated_user_can_mark_one_notification_as_read(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $otherUser = User::factory()->create(['role' => 'team']);

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $player->id,
            'type' => 'opportunity',
            'payload' => json_encode(['role' => 'player']),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherNotificationId = DB::table('notifications')->insertGetId([
            'user_id' => $otherUser->id,
            'type' => 'contact',
            'payload' => json_encode(['role' => 'team']),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($player, ['profile:write']);

        $this->patchJson('/api/notifications/'.$notificationId.'/read')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'user_id' => $player->id,
            'is_read' => true,
        ]);

        $this->patchJson('/api/notifications/'.$otherNotificationId.'/read')
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    public function test_authenticated_user_can_mark_all_notifications_as_read(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        DB::table('notifications')->insert([
            [
                'user_id' => $player->id,
                'type' => 'opportunity',
                'payload' => json_encode(['role' => 'player']),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $player->id,
                'type' => 'message',
                'payload' => json_encode(['role' => 'player']),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($player, ['profile:write']);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $player->id,
            'is_read' => false,
        ]);
    }
}
