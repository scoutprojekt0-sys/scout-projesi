<?php

namespace Tests\Feature;

use App\Models\PlayerCareerTimeline;
use App\Models\PlayerMarketValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week6PlayerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_compare_players(): void
    {
        $playerA = User::factory()->create([
            'role' => 'player',
            'name' => 'Player A',
            'position' => 'FW',
            'age' => 23,
        ]);

        $playerB = User::factory()->create([
            'role' => 'player',
            'name' => 'Player B',
            'position' => 'MF',
            'age' => 25,
        ]);

        $team = User::factory()->create(['role' => 'team']);

        PlayerMarketValue::create([
            'player_id' => $playerA->id,
            'value' => 900000,
            'currency' => 'EUR',
            'valuation_date' => now()->subDays(5)->toDateString(),
            'verification_status' => 'verified',
        ]);

        PlayerMarketValue::create([
            'player_id' => $playerB->id,
            'value' => 650000,
            'currency' => 'EUR',
            'valuation_date' => now()->subDays(5)->toDateString(),
            'verification_status' => 'verified',
        ]);

        PlayerCareerTimeline::create([
            'player_id' => $playerA->id,
            'club_id' => $team->id,
            'start_date' => now()->subMonths(5)->toDateString(),
            'season_start' => '2025-26',
            'is_current' => true,
            'position' => 'FW',
            'contract_type' => 'professional',
            'appearances' => 10,
            'goals' => 6,
            'assists' => 2,
            'minutes_played' => 780,
            'verification_status' => 'verified',
        ]);

        PlayerCareerTimeline::create([
            'player_id' => $playerB->id,
            'club_id' => $team->id,
            'start_date' => now()->subMonths(5)->toDateString(),
            'season_start' => '2025-26',
            'is_current' => true,
            'position' => 'MF',
            'contract_type' => 'professional',
            'appearances' => 11,
            'goals' => 2,
            'assists' => 5,
            'minutes_played' => 890,
            'verification_status' => 'verified',
        ]);

        $response = $this->postJson('/api/players/compare', [
            'player_ids' => [$playerA->id, $playerB->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'data.players')
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'players' => [
                        [
                            'player_id',
                            'player_name',
                            'market_value',
                            'stats' => ['goal_contribution'],
                        ],
                    ],
                    'best_market_value',
                    'best_goal_contribution',
                ],
            ]);
    }

    public function test_can_get_player_trend_summary(): void
    {
        $player = User::factory()->create([
            'role' => 'player',
            'position' => 'FW',
            'age' => 22,
        ]);

        $team = User::factory()->create(['role' => 'team']);

        PlayerMarketValue::create([
            'player_id' => $player->id,
            'value' => 500000,
            'currency' => 'EUR',
            'valuation_date' => now()->subMonths(2)->toDateString(),
            'verification_status' => 'verified',
        ]);

        PlayerMarketValue::create([
            'player_id' => $player->id,
            'value' => 700000,
            'currency' => 'EUR',
            'valuation_date' => now()->subMonth()->toDateString(),
            'verification_status' => 'verified',
        ]);

        PlayerCareerTimeline::create([
            'player_id' => $player->id,
            'club_id' => $team->id,
            'start_date' => now()->subMonths(6)->toDateString(),
            'season_start' => '2025-26',
            'is_current' => true,
            'position' => 'FW',
            'contract_type' => 'professional',
            'appearances' => 14,
            'goals' => 9,
            'assists' => 4,
            'minutes_played' => 1120,
            'verification_status' => 'verified',
        ]);

        $response = $this->getJson('/api/players/'.$player->id.'/trend-summary');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player.id', $player->id)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'player' => ['id', 'name'],
                    'value_series',
                    'form_series',
                    'summary' => ['latest_value', 'overall_growth_percent', 'series_points'],
                ],
            ]);
    }

    public function test_can_get_similar_players(): void
    {
        $target = User::factory()->create([
            'role' => 'player',
            'name' => 'Target Player',
            'position' => 'FW',
            'age' => 23,
            'city' => 'Istanbul',
        ]);

        $similar = User::factory()->create([
            'role' => 'player',
            'name' => 'Similar Player',
            'position' => 'FW',
            'age' => 24,
            'city' => 'Ankara',
        ]);

        $different = User::factory()->create([
            'role' => 'player',
            'name' => 'Different Player',
            'position' => 'GK',
            'age' => 31,
            'city' => 'Izmir',
        ]);

        \Illuminate\Support\Facades\DB::table('player_profiles')->insert([
            [
                'user_id' => $target->id,
                'birth_year' => now()->year - 23,
                'position' => 'FW',
                'current_team' => 'Target Club',
                'updated_at' => now(),
            ],
            [
                'user_id' => $similar->id,
                'birth_year' => now()->year - 24,
                'position' => 'FW',
                'current_team' => 'Similar Club',
                'updated_at' => now(),
            ],
            [
                'user_id' => $different->id,
                'birth_year' => now()->year - 31,
                'position' => 'GK',
                'current_team' => 'Different Club',
                'updated_at' => now(),
            ],
        ]);

        PlayerMarketValue::create([
            'player_id' => $target->id,
            'value' => 1000000,
            'currency' => 'EUR',
            'valuation_date' => now()->subDays(3)->toDateString(),
            'verification_status' => 'verified',
        ]);

        PlayerMarketValue::create([
            'player_id' => $similar->id,
            'value' => 950000,
            'currency' => 'EUR',
            'valuation_date' => now()->subDays(2)->toDateString(),
            'verification_status' => 'verified',
        ]);

        PlayerMarketValue::create([
            'player_id' => $different->id,
            'value' => 300000,
            'currency' => 'EUR',
            'valuation_date' => now()->subDay()->toDateString(),
            'verification_status' => 'verified',
        ]);

        $response = $this->getJson('/api/players/'.$target->id.'/similar?limit=5');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.target_player.player_id', $target->id)
            ->assertJsonPath('data.similar_players.0.player_name', 'Similar Player')
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'target_player' => ['player_id', 'player_name', 'market_value'],
                    'similar_players' => [
                        [
                            'player_id',
                            'player_name',
                            'position',
                            'market_value',
                            'similarity_score',
                        ],
                    ],
                ],
            ]);
    }
}
