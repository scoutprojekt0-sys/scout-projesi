<?php

namespace Tests\Feature;

use App\Models\ClubOffer;
use App\Models\PlayerTransfer;
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
            ->assertJsonPath('data.to_club_name', 'Yeni Spor')
            ->assertJsonPath('data.verification_status', 'verified');

        $this->assertDatabaseHas('player_transfers', [
            'player_id' => $player->id,
            'from_club_name' => 'Mahalle SK',
            'to_club_name' => 'Yeni Spor',
            'to_club_id' => null,
            'verification_status' => 'verified',
        ]);

        $this->assertDatabaseMissing('moderation_queue', [
            'model_type' => 'PlayerTransfer',
            'status' => 'pending',
        ]);

        $this->getJson("/api/transfers/player/{$player->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.0.from_club_name', 'Mahalle SK')
            ->assertJsonPath('data.0.to_club_name', 'Yeni Spor');
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

    public function test_club_can_counter_and_accept_player_counter_offer(): void
    {
        $club = User::factory()->club()->create([
            'name' => 'Restore SK',
            'sport' => 'football',
        ]);
        $player = User::factory()->player()->create([
            'name' => 'Ali Kanat',
            'sport' => 'football',
        ]);

        Sanctum::actingAs($club, $club->tokenAbilities());

        $offerResponse = $this->postJson('/api/club/offers', [
            'target_player_user_id' => $player->id,
            'player_name' => $player->name,
            'sport' => 'football',
            'offer_type' => 'permanent',
            'amount_eur' => 100000,
            'currency' => 'EUR',
            'season' => '2026-27',
            'note' => 'Ilk kulup teklifi.',
        ]);

        $offerResponse
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.negotiation_status', 'open');

        $transferId = (int) $offerResponse->json('data.transfer_id');
        $this->assertGreaterThan(0, $transferId);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->postJson("/api/transfers/{$transferId}/room-action", [
            'action' => 'counter',
            'counter_fee' => 125000,
            'note' => 'Oyuncu karsi teklifi.',
        ])
            ->assertOk()
            ->assertJsonPath('data.negotiation_status', 'countered');

        Sanctum::actingAs($club, $club->tokenAbilities());

        $this->postJson("/api/transfers/{$transferId}/room-action", [
            'action' => 'counter',
            'counter_fee' => 130000,
            'note' => 'Kulup yeni karsi teklifi.',
        ])
            ->assertOk()
            ->assertJsonPath('data.negotiation_status', 'countered');

        $transfer = PlayerTransfer::query()->findOrFail($transferId);
        $this->assertSame('countered', $transfer->negotiation_status);
        $this->assertSame('130000.00', (string) $transfer->counter_fee);

        $offer = ClubOffer::query()->where('transfer_id', $transferId)->firstOrFail();
        $this->assertSame('countered', $offer->status);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->postJson("/api/transfers/{$transferId}/room-action", [
            'action' => 'counter',
            'counter_fee' => 128000,
            'note' => 'Oyuncu son rakam.',
        ])
            ->assertOk()
            ->assertJsonPath('data.negotiation_status', 'countered');

        Sanctum::actingAs($club, $club->tokenAbilities());

        $this->postJson("/api/transfers/{$transferId}/room-action", [
            'action' => 'accept',
            'note' => 'Kulup son rakami kabul etti.',
        ])
            ->assertOk()
            ->assertJsonPath('data.negotiation_status', 'accepted');

        $transfer->refresh();
        $offer->refresh();

        $this->assertSame('accepted', $transfer->negotiation_status);
        $this->assertSame('128000.00', (string) $transfer->fee);
        $this->assertSame('128000.00', (string) $transfer->counter_fee);
        $this->assertSame('accepted', $offer->status);
        $this->assertStringContainsString('Kulup son rakami kabul etti.', (string) $transfer->notes);
    }
}
