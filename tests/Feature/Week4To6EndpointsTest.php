<?php

namespace Tests\Feature;

use App\Models\PlayerCareerTimeline;
use App\Models\PlayerMarketValue;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Week4To6EndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_quality_report_endpoint_returns_ok(): void
    {
        User::factory()->create(['role' => 'player', 'has_source' => true, 'verification_status' => 'verified']);

        $response = $this->getJson('/api/data-quality/report');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'kpi' => [
                        'source_coverage_percent',
                        'verification_percent',
                        'conflict_percent',
                        'transfer_verification_percent',
                    ],
                    'raw',
                    'generated_at',
                ],
            ]);
    }

    public function test_team_overview_returns_squad_and_transfers(): void
    {
        $team = User::factory()->create(['role' => 'team', 'name' => 'Demo Team']);
        $player = User::factory()->create(['role' => 'player', 'position' => 'FW']);

        DB::table('team_profiles')->insert([
            'user_id' => $team->id,
            'team_name' => 'Demo Team FC',
            'league_level' => '1. Lig',
            'city' => 'Istanbul',
            'founded_year' => 2001,
            'needs_text' => 'Forvet ariyoruz',
            'updated_at' => now(),
        ]);

        PlayerCareerTimeline::create([
            'player_id' => $player->id,
            'club_id' => $team->id,
            'start_date' => now()->subMonths(4)->toDateString(),
            'season_start' => '2025-26',
            'is_current' => true,
            'position' => 'FW',
            'contract_type' => 'professional',
            'appearances' => 12,
            'goals' => 7,
            'assists' => 2,
            'minutes_played' => 980,
            'verification_status' => 'verified',
        ]);

        PlayerTransfer::create([
            'player_id' => $player->id,
            'from_club_id' => null,
            'to_club_id' => $team->id,
            'fee' => 150000,
            'currency' => 'EUR',
            'transfer_date' => now()->subMonths(5)->toDateString(),
            'transfer_type' => 'permanent',
            'season' => '2025-26',
            'window' => 'summer',
            'verification_status' => 'verified',
        ]);

        $response = $this->getJson('/api/teams/'.$team->id.'/overview');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonPath('data.squad_count', 1);
    }

    public function test_team_transfer_summary_requires_authentication(): void
    {
        $team = User::factory()->create(['role' => 'team']);

        $this->getJson('/api/teams/'.$team->id.'/transfer-summary')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create(['role' => 'team']));

        $this->getJson('/api/teams/'.$team->id.'/transfer-summary')->assertStatus(200);
    }

    public function test_market_value_trends_and_leaderboard_endpoints(): void
    {
        $player = User::factory()->create(['role' => 'player', 'position' => 'FW', 'age' => 23]);

        PlayerMarketValue::create([
            'player_id' => $player->id,
            'value' => 500000,
            'currency' => 'EUR',
            'valuation_date' => now()->subMonths(2)->toDateString(),
            'verification_status' => 'verified',
        ]);

        PlayerMarketValue::create([
            'player_id' => $player->id,
            'value' => 650000,
            'currency' => 'EUR',
            'valuation_date' => now()->subMonth()->toDateString(),
            'value_change_percent' => 30,
            'verification_status' => 'verified',
        ]);

        PlayerCareerTimeline::create([
            'player_id' => $player->id,
            'club_id' => User::factory()->create(['role' => 'team'])->id,
            'start_date' => now()->subMonths(6)->toDateString(),
            'season_start' => '2025-26',
            'is_current' => true,
            'position' => 'FW',
            'contract_type' => 'professional',
            'appearances' => 14,
            'goals' => 9,
            'assists' => 3,
            'minutes_played' => 1120,
            'verification_status' => 'verified',
        ]);

        $this->getJson('/api/market-values/player/'.$player->id.'/trends')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/market-values/leaderboard?limit=5')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_contracts_live_returns_suspended_players(): void
    {
        $suspendedPlayer = User::factory()->create([
            'role' => 'player',
            'name' => 'Suspended Player',
        ]);

        $club = User::factory()->create(['role' => 'team', 'name' => 'Demo Club']);

        DB::table('player_profiles')->insert([
            'user_id' => $suspendedPlayer->id,
            'position' => 'MF',
            'current_team' => 'Demo Club',
            'updated_at' => now(),
        ]);

        DB::table('contracts')->insert([
            'player_id' => $suspendedPlayer->id,
            'club_id' => $club->id,
            'contract_type' => 'permanent',
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(6),
            'salary' => 100000,
            'currency' => 'EUR',
            'status' => 'terminated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/contracts/live?statuses=suspended')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.player_name', 'Suspended Player')
            ->assertJsonPath('data.0.status', 'suspended');
    }
}
