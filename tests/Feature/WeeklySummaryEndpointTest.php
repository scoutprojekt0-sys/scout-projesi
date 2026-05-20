<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeeklySummaryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_summary_requires_authentication(): void
    {
        $this->getJson('/api/discovery/weekly-summary')->assertStatus(401);
    }

    public function test_player_weekly_summary_returns_real_counts(): void
    {
        $player = User::factory()->create(['role' => 'player', 'name' => 'Lia Player']);
        $viewer = User::factory()->create(['role' => 'scout']);
        $follower = User::factory()->create(['role' => 'manager']);
        $sender = User::factory()->create(['role' => 'club']);
        $club = User::factory()->create(['role' => 'club']);

        DB::table('profile_views')->insert([
            'viewer_user_id' => $viewer->id,
            'viewed_user_id' => $player->id,
            'ip_address' => '127.0.0.1',
            'viewed_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        DB::table('favorites')->insert([
            'user_id' => $follower->id,
            'target_user_id' => $player->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('contacts')->insert([
            'from_user_id' => $sender->id,
            'to_user_id' => $player->id,
            'subject' => 'Test',
            'message' => 'Haftalik mesaj',
            'status' => 'new',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('club_offers')->insert([
            'club_user_id' => $club->id,
            'target_player_user_id' => $player->id,
            'player_name' => $player->name,
            'amount_eur' => 10000,
            'status' => 'sent',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($player, ['profile:read']);

        $this->getJson('/api/discovery/weekly-summary')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.role', 'player')
            ->assertJsonPath('data.stats.stat_one', 1)
            ->assertJsonPath('data.stats.stat_two', 1)
            ->assertJsonPath('data.stats.stat_three', 2)
            ->assertJsonPath('data.metric_two', 1)
            ->assertJsonPath('data.signals.rank_value', '#1')
            ->assertJsonPath('data.top_items.0.title', 'Lia Player');
    }
}
