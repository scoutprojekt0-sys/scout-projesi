<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_routes_require_authentication_for_read_access(): void
    {
        $this->getJson('/api/contracts')->assertStatus(401);
        $this->getJson('/api/contracts/1')->assertStatus(401);
    }

    public function test_authenticated_user_only_sees_own_contracts_without_email_fields(): void
    {
        $player = User::factory()->create(['role' => 'player', 'name' => 'Player One', 'email' => 'player@example.com']);
        $club = User::factory()->create(['role' => 'team', 'name' => 'Club One', 'email' => 'club@example.com']);
        $otherPlayer = User::factory()->create(['role' => 'player']);
        $otherClub = User::factory()->create(['role' => 'team']);

        $ownedContract = Contract::query()->create([
            'player_id' => $player->id,
            'club_id' => $club->id,
            'contract_type' => 'permanent',
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        Contract::query()->create([
            'player_id' => $otherPlayer->id,
            'club_id' => $otherClub->id,
            'contract_type' => 'permanent',
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->getJson('/api/contracts')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $ownedContract->id)
            ->assertJsonMissingPath('data.0.player.email')
            ->assertJsonMissingPath('data.0.club.email');

        $this->getJson('/api/contracts/'.$ownedContract->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $ownedContract->id)
            ->assertJsonMissingPath('data.player.email')
            ->assertJsonMissingPath('data.club.email');
    }

    public function test_authenticated_user_cannot_view_other_users_contract(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $club = User::factory()->create(['role' => 'team']);
        $attacker = User::factory()->create(['role' => 'player']);

        $contract = Contract::query()->create([
            'player_id' => $player->id,
            'club_id' => $club->id,
            'contract_type' => 'permanent',
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        Sanctum::actingAs($attacker, $attacker->tokenAbilities());

        $this->getJson('/api/contracts/'.$contract->id)
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    public function test_player_can_create_contract_with_free_text_club_name(): void
    {
        $player = User::factory()->create(['role' => 'player', 'name' => 'Street Player']);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->postJson('/api/contracts', [
            'club_name' => 'Mahalle SK',
            'contract_type' => 'trial',
            'start_date' => '2026-05-01',
            'end_date' => '2026-12-31',
            'terms' => 'Serbest metin takim adi ile olusturuldu.',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player_id', $player->id)
            ->assertJsonPath('data.club_id', null)
            ->assertJsonPath('data.club_name', 'Mahalle SK');

        $this->assertDatabaseHas('contracts', [
            'player_id' => $player->id,
            'club_id' => null,
            'club_name' => 'Mahalle SK',
            'contract_type' => 'trial',
        ]);
    }

    public function test_player_can_update_contract_club_name_without_club_id(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        $contract = Contract::query()->create([
            'player_id' => $player->id,
            'club_id' => null,
            'club_name' => 'Eski Takim',
            'contract_type' => 'trial',
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-01',
            'salary' => 2001,
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        Sanctum::actingAs($player, $player->tokenAbilities());

        $this->patchJson('/api/contracts/'.$contract->id, [
            'club_name' => 'Yeni Mahalle Takimi',
            'end_date' => '2026-08-01',
            'salary' => 200,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonPath('data.club_id', null)
            ->assertJsonPath('data.club_name', 'Yeni Mahalle Takimi')
            ->assertJsonPath('data.salary', '200.00');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'club_id' => null,
            'club_name' => 'Yeni Mahalle Takimi',
            'end_date' => '2026-08-01 00:00:00',
            'salary' => '200.00',
        ]);
    }
}
