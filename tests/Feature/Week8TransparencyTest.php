<?php

namespace Tests\Feature;

use App\Models\PlayerMarketValue;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week8TransparencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_health_returns_kpi_summary(): void
    {
        User::factory()->create([
            'role' => 'player',
            'has_source' => true,
            'confidence_score' => 0.88,
            'verification_status' => 'verified',
        ]);

        User::factory()->create([
            'role' => 'player',
            'has_source' => false,
            'confidence_score' => 0.45,
            'verification_status' => 'needs_review',
        ]);

        $response = $this->getJson('/api/data-quality/source-health');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.players_total', 2)
            ->assertJsonPath('data.with_source', 1)
            ->assertJsonPath('data.missing_source', 1)
            ->assertJsonPath('data.low_confidence', 1)
            ->assertJsonPath('data.needs_review', 1);
    }

    public function test_transparency_players_list_supports_filters(): void
    {
        User::factory()->create([
            'role' => 'player',
            'position' => 'Forvet',
            'has_source' => true,
            'confidence_score' => 0.80,
            'verification_status' => 'verified',
        ]);

        User::factory()->create([
            'role' => 'player',
            'position' => 'Defans',
            'has_source' => false,
            'confidence_score' => 0.40,
            'verification_status' => 'needs_review',
        ]);

        $response = $this->getJson('/api/data-quality/transparency/players?missing_source=1&max_confidence=0.5');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.has_source', false)
            ->assertJsonPath('data.data.0.verification_status', 'needs_review')
            ->assertJsonMissingPath('data.data.0.email')
            ->assertJsonMissingPath('data.data.0.source_url');
    }

    public function test_player_detail_returns_market_values_and_transfers(): void
    {
        $player = User::factory()->create([
            'role' => 'player',
            'has_source' => true,
            'confidence_score' => 0.76,
            'verification_status' => 'verified',
        ]);

        $fromClub = User::factory()->create(['role' => 'team']);
        $toClub = User::factory()->create(['role' => 'team']);

        PlayerMarketValue::create([
            'player_id' => $player->id,
            'value' => 620000,
            'currency' => 'EUR',
            'valuation_date' => now()->subDays(7)->toDateString(),
            'source_url' => 'https://example.com/value',
            'confidence_score' => 0.72,
            'verification_status' => 'verified',
        ]);

        PlayerTransfer::create([
            'player_id' => $player->id,
            'from_club_id' => $fromClub->id,
            'to_club_id' => $toClub->id,
            'fee' => 250000,
            'currency' => 'EUR',
            'transfer_date' => now()->subDays(30)->toDateString(),
            'transfer_type' => 'permanent',
            'season' => '2025-26',
            'window' => 'summer',
            'source_url' => 'https://example.com/transfer',
            'confidence_score' => 0.70,
            'verification_status' => 'verified',
        ]);

        $response = $this->getJson('/api/data-quality/transparency/players/'.$player->id);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player.id', $player->id)
            ->assertJsonCount(1, 'data.market_values')
            ->assertJsonCount(1, 'data.transfers')
            ->assertJsonMissingPath('data.player.source_url')
            ->assertJsonMissingPath('data.market_values.0.source_url')
            ->assertJsonMissingPath('data.transfers.0.source_url');
    }

    public function test_player_detail_returns_404_for_non_player(): void
    {
        $team = User::factory()->create(['role' => 'team']);

        $this->getJson('/api/data-quality/transparency/players/'.$team->id)
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }
}
