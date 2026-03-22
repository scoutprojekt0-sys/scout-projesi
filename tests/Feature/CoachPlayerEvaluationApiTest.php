<?php

namespace Tests\Feature;

use App\Models\CoachPlayerEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachPlayerEvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_store_and_list_evaluations(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $player = User::factory()->create(['role' => 'player', 'name' => 'Yusuf B.']);

        Sanctum::actingAs($coach, ['profile:read', 'profile:write']);

        $payload = [
            'player_user_id' => $player->id,
            'player_name' => $player->name,
            'position' => 'Bek',
            'decision_note' => 'Gecis savunmasinda dikkat cekiyor.',
            'scores' => [
                ['label' => 'Ilk Kontrol', 'value' => 78, 'note' => 'iyi'],
                ['label' => 'Karar Hizi', 'value' => 82, 'note' => 'hizli'],
            ],
        ];

        $this->postJson('/api/coach/player-evaluations', $payload)
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player_name', $player->name);

        $this->getJson('/api/coach/player-evaluations?player_user_id='.$player->id)
            ->assertOk()
            ->assertJsonPath('data.data.0.player_name', $player->name);

        $this->assertDatabaseHas('coach_player_evaluations', [
            'coach_user_id' => $coach->id,
            'player_user_id' => $player->id,
            'player_name' => $player->name,
        ]);
    }

    public function test_player_cannot_store_evaluation(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($player, ['profile:write']);

        $this->postJson('/api/coach/player-evaluations', [
            'player_name' => 'Deneme Oyuncu',
            'scores' => [['label' => 'Ilk Kontrol', 'value' => 70]],
        ])->assertStatus(403);
    }

    public function test_coach_only_sees_own_evaluations(): void
    {
        $coachA = User::factory()->create(['role' => 'coach']);
        $coachB = User::factory()->create(['role' => 'coach']);

        CoachPlayerEvaluation::query()->create([
            'coach_user_id' => $coachA->id,
            'player_name' => 'Oyuncu A',
            'scores' => [['label' => 'Ilk Kontrol', 'value' => 75]],
            'average_score' => 75,
        ]);

        CoachPlayerEvaluation::query()->create([
            'coach_user_id' => $coachB->id,
            'player_name' => 'Oyuncu B',
            'scores' => [['label' => 'Ilk Kontrol', 'value' => 81]],
            'average_score' => 81,
        ]);

        Sanctum::actingAs($coachA, ['profile:read']);

        $this->getJson('/api/coach/player-evaluations')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.player_name', 'Oyuncu A');
    }
}
