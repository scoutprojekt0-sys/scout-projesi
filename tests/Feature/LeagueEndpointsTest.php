<?php

namespace Tests\Feature;

use App\Models\PlayerCareerTimeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeagueEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_and_show_leagues(): void
    {
        $team = User::factory()->create(['role' => 'team', 'name' => 'Team One']);

        DB::table('team_profiles')->insert([
            'user_id' => $team->id,
            'team_name' => 'Team One',
            'league_level' => 'Super Lig',
            'city' => 'Istanbul',
            'updated_at' => now(),
        ]);

        $this->getJson('/api/leagues')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.name', 'Super Lig')
            ->assertJsonPath('data.0.team_count', 1);

        $this->getJson('/api/leagues/Super%20Lig')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Super Lig')
            ->assertJsonPath('data.clubs.0.name', 'Team One');
    }

    public function test_public_can_get_standings_and_player_leaders_for_league(): void
    {
        $teamA = User::factory()->create(['role' => 'team', 'name' => 'Alpha FC']);
        $teamB = User::factory()->create(['role' => 'team', 'name' => 'Beta FC']);

        DB::table('team_profiles')->insert([
            ['user_id' => $teamA->id, 'team_name' => 'Alpha FC', 'league_level' => '1. Lig', 'city' => 'Izmir', 'updated_at' => now()],
            ['user_id' => $teamB->id, 'team_name' => 'Beta FC', 'league_level' => '1. Lig', 'city' => 'Bursa', 'updated_at' => now()],
        ]);

        $scorer = User::factory()->create(['role' => 'player', 'name' => 'Scorer', 'position' => 'FW']);
        $playmaker = User::factory()->create(['role' => 'player', 'name' => 'Playmaker', 'position' => 'MF']);

        PlayerCareerTimeline::query()->create([
            'player_id' => $scorer->id,
            'club_id' => $teamA->id,
            'start_date' => now()->subMonths(5)->toDateString(),
            'season_start' => '25/26',
            'is_current' => true,
            'position' => 'FW',
            'contract_type' => 'professional',
            'appearances' => 12,
            'goals' => 8,
            'assists' => 1,
            'verification_status' => 'verified',
        ]);

        PlayerCareerTimeline::query()->create([
            'player_id' => $playmaker->id,
            'club_id' => $teamB->id,
            'start_date' => now()->subMonths(5)->toDateString(),
            'season_start' => '25/26',
            'is_current' => true,
            'position' => 'MF',
            'contract_type' => 'professional',
            'appearances' => 12,
            'goals' => 2,
            'assists' => 6,
            'verification_status' => 'verified',
        ]);

        $this->getJson('/api/leagues/1.%20Lig/standings')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.club_name', 'Alpha FC')
            ->assertJsonPath('meta.computed_from', 'current_verified_player_career_timeline');

        $this->getJson('/api/leagues/1.%20Lig/top-scorers')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.player_name', 'Scorer');

        $this->getJson('/api/leagues/1.%20Lig/top-assists')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.player_name', 'Playmaker');
    }
}
