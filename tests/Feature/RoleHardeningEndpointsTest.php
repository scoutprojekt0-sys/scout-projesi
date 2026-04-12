<?php

namespace Tests\Feature;

use App\Models\ClubPromo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleHardeningEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scout_cannot_list_global_transfer_feed(): void
    {
        $scout = User::factory()->create(['role' => 'scout']);

        Sanctum::actingAs($scout, $scout->tokenAbilities());

        $this->getJson('/api/transfers')
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    public function test_scout_cannot_create_transfer_record(): void
    {
        $scout = User::factory()->create(['role' => 'scout']);
        $player = User::factory()->create(['role' => 'player']);
        $club = User::factory()->create(['role' => 'team']);

        Sanctum::actingAs($scout, $scout->tokenAbilities());

        $this->postJson('/api/transfers', [
            'player_id' => $player->id,
            'to_club_id' => $club->id,
            'transfer_date' => '2026-04-12',
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/transfer',
        ])
            ->assertForbidden()
            ->assertJsonPath('ok', false);

        $this->assertDatabaseCount('player_transfers', 0);
    }

    public function test_player_cannot_create_career_timeline_entry(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $club = User::factory()->create(['role' => 'team']);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->postJson('/api/career', [
            'player_id' => $player->id,
            'club_id' => $club->id,
            'start_date' => '2025-08-01',
            'season_start' => '25/26',
            'contract_type' => 'professional',
            'source_url' => 'https://example.com/career',
        ])
            ->assertForbidden()
            ->assertJsonPath('ok', false);

        $this->assertDatabaseCount('player_career_timeline', 0);
    }

    public function test_non_club_user_cannot_view_club_promos_workspace(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $club = User::factory()->create(['role' => 'club']);

        ClubPromo::query()->create([
            'club_user_id' => $club->id,
            'club_name' => 'Alpha Club',
            'notes' => 'Private promo',
            'video_url' => 'https://example.com/promo.mp4',
            'images' => ['https://example.com/promo.jpg'],
            'paid' => true,
        ]);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->getJson('/api/club/promos')
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    public function test_club_promos_workspace_returns_only_current_club_items(): void
    {
        $club = User::factory()->create(['role' => 'club']);
        $otherClub = User::factory()->create(['role' => 'club']);

        ClubPromo::query()->create([
            'club_user_id' => $club->id,
            'club_name' => 'Alpha Club',
            'notes' => 'Visible promo',
            'video_url' => 'https://example.com/alpha.mp4',
            'images' => [],
            'paid' => true,
        ]);

        ClubPromo::query()->create([
            'club_user_id' => $otherClub->id,
            'club_name' => 'Beta Club',
            'notes' => 'Hidden promo',
            'video_url' => 'https://example.com/beta.mp4',
            'images' => [],
            'paid' => false,
        ]);

        Sanctum::actingAs($club, $club->tokenAbilities());

        $this->getJson('/api/club/promos')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.club_name', 'Alpha Club');
    }
}
