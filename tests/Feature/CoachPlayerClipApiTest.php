<?php

namespace Tests\Feature;

use App\Models\CoachPlayerClip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachPlayerClipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_store_and_list_own_clips(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $player = User::factory()->create(['role' => 'player', 'name' => 'Mert A.']);

        Sanctum::actingAs($coach, ['profile:read', 'profile:write']);

        $this->postJson('/api/coach/player-clips', [
            'player_user_id' => $player->id,
            'player_name' => $player->name,
            'video_url' => 'https://example.org/video/1',
            'minute_mark' => 12,
            'second_mark' => 34,
            'body' => 'Dar alanda ilk kontrol ve yon degistirme guclu.',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player', $player->name)
            ->assertJsonPath('data.stamp', '12:34');

        $this->getJson('/api/coach/player-clips')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.player', $player->name);

        $this->assertDatabaseHas('coach_player_clips', [
            'coach_user_id' => $coach->id,
            'player_name' => $player->name,
            'stamp' => '12:34',
        ]);
    }

    public function test_player_cannot_store_clip_note(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($player, ['profile:write']);

        $this->postJson('/api/coach/player-clips', [
            'player_name' => 'Deneme Oyuncu',
            'video_url' => 'https://example.org/video/2',
            'body' => 'Bu islem oyuncu rolu ile yapilamaz.',
        ])->assertStatus(403);
    }

    public function test_coach_only_sees_own_clips(): void
    {
        $coachA = User::factory()->create(['role' => 'coach']);
        $coachB = User::factory()->create(['role' => 'coach']);

        CoachPlayerClip::query()->create([
            'coach_user_id' => $coachA->id,
            'player_name' => 'Oyuncu A',
            'video_url' => 'https://example.org/a',
            'stamp' => '00:10',
            'body' => 'Coach A klibi',
        ]);

        CoachPlayerClip::query()->create([
            'coach_user_id' => $coachB->id,
            'player_name' => 'Oyuncu B',
            'video_url' => 'https://example.org/b',
            'stamp' => '00:20',
            'body' => 'Coach B klibi',
        ]);

        Sanctum::actingAs($coachA, ['profile:read']);

        $this->getJson('/api/coach/player-clips')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.player', 'Oyuncu A');
    }
}
