<?php

namespace Tests\Feature;

use App\Models\PlayerCareerTimeline;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_and_rank_clubs(): void
    {
        $clubA = User::factory()->create(['role' => 'team', 'name' => 'Club A', 'city' => 'Istanbul']);
        $clubB = User::factory()->create(['role' => 'team', 'name' => 'Club B', 'city' => 'Ankara']);

        DB::table('team_profiles')->insert([
            ['user_id' => $clubA->id, 'team_name' => 'Club A', 'league_level' => 'Super Lig', 'city' => 'Istanbul', 'updated_at' => now()],
            ['user_id' => $clubB->id, 'team_name' => 'Club B', 'league_level' => '1. Lig', 'city' => 'Ankara', 'updated_at' => now()],
        ]);

        PlayerTransfer::query()->create([
            'player_id' => User::factory()->create(['role' => 'player'])->id,
            'to_club_id' => $clubA->id,
            'fee' => 1000000,
            'currency' => 'EUR',
            'transfer_date' => now()->subDays(3),
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/a',
            'verification_status' => 'verified',
            'confidence_score' => 0.9,
        ]);

        $this->getJson('/api/clubs?search=Club&sort_by=total_market_value')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.name', 'Club A');

        $this->getJson('/api/clubs/most-valuable?limit=1')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.name', 'Club A');
    }

    public function test_public_can_view_club_squad_and_transfers(): void
    {
        $club = User::factory()->create(['role' => 'team', 'name' => 'Besiktas']);
        DB::table('team_profiles')->insert([
            'user_id' => $club->id,
            'team_name' => 'Besiktas',
            'league_level' => 'Super Lig',
            'city' => 'Istanbul',
            'updated_at' => now(),
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'name' => 'Player One',
            'position' => 'Forward',
            'age' => 22,
        ]);

        PlayerCareerTimeline::query()->create([
            'player_id' => $player->id,
            'club_id' => $club->id,
            'start_date' => now()->subMonths(6)->toDateString(),
            'season_start' => '25/26',
            'appearances' => 10,
            'goals' => 5,
            'assists' => 2,
            'is_current' => true,
            'verification_status' => 'verified',
        ]);

        PlayerTransfer::query()->create([
            'player_id' => $player->id,
            'to_club_id' => $club->id,
            'fee' => 500000,
            'currency' => 'EUR',
            'transfer_date' => now()->subMonth(),
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/b',
            'verification_status' => 'verified',
            'confidence_score' => 0.85,
        ]);

        $this->getJson('/api/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.club.name', 'Besiktas')
            ->assertJsonPath('data.recent_transfers.0.player_name', 'Player One');

        $this->getJson('/api/clubs/'.$club->id.'/squad')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.summary.total_players', 1)
            ->assertJsonPath('data.squad.0.name', 'Player One');

        $this->getJson('/api/clubs/'.$club->id.'/transfers')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.summary.total_spent', 500000)
            ->assertJsonPath('data.incoming.0.player_name', 'Player One');
    }
}
