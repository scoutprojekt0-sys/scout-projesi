<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_create_own_transfer_with_club_names_only(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        \App\Models\PlayerProfile::query()->create([
            'user_id' => $player->id,
            'current_team' => 'Mahalle SK',
        ]);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->postJson('/api/transfers', [
            'player_id' => $player->id,
            'to_club_name' => 'Yeni Spor',
            'transfer_date' => '2026-04-27',
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/transfer',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.from_club_name', 'Mahalle SK')
            ->assertJsonPath('data.to_club_name', 'Yeni Spor');

        $this->assertDatabaseHas('player_transfers', [
            'player_id' => $player->id,
            'from_club_name' => 'Mahalle SK',
            'to_club_name' => 'Yeni Spor',
            'to_club_id' => null,
        ]);
    }

    public function test_player_cannot_create_transfer_for_another_player(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $otherPlayer = User::factory()->create(['role' => 'player']);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->postJson('/api/transfers', [
            'player_id' => $otherPlayer->id,
            'to_club_name' => 'Yeni Spor',
            'transfer_date' => '2026-04-27',
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/transfer',
        ])
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }
}
