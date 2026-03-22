<?php

namespace Tests\Feature;

use App\Models\CoachPlayerNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachPlayerNoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_store_and_list_own_notes(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $player = User::factory()->create(['role' => 'player', 'name' => 'Arda K.']);

        Sanctum::actingAs($coach, ['profile:read', 'profile:write']);

        $this->postJson('/api/coach/player-notes', [
            'player_user_id' => $player->id,
            'player_name' => $player->name,
            'position' => 'Forvet',
            'tag' => 'Hazir',
            'focus' => 'Son vurus',
            'body' => 'Ceza sahasi kosulari guclu ve karar hizi yuksek.',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player_user_id', $player->id)
            ->assertJsonPath('data.player', $player->name);

        $this->getJson('/api/coach/player-notes')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.player', $player->name);

        $this->assertDatabaseHas('coach_player_notes', [
            'coach_user_id' => $coach->id,
            'player_user_id' => $player->id,
            'player_name' => $player->name,
        ]);
    }

    public function test_player_cannot_store_coach_note(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($player, ['profile:write']);

        $this->postJson('/api/coach/player-notes', [
            'player_name' => 'Deneme Oyuncu',
            'body' => 'Bu islem oyuncu rolu ile yapilamaz.',
        ])->assertStatus(403);
    }

    public function test_coach_only_sees_own_notes(): void
    {
        $coachA = User::factory()->create(['role' => 'coach']);
        $coachB = User::factory()->create(['role' => 'coach']);

        CoachPlayerNote::query()->create([
            'coach_user_id' => $coachA->id,
            'player_name' => 'Oyuncu A',
            'body' => 'Coach A notu',
        ]);

        CoachPlayerNote::query()->create([
            'coach_user_id' => $coachB->id,
            'player_name' => 'Oyuncu B',
            'body' => 'Coach B notu',
        ]);

        Sanctum::actingAs($coachA, ['profile:read']);

        $this->getJson('/api/coach/player-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.player', 'Oyuncu A');
    }
}
