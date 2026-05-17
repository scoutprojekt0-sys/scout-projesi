<?php

namespace Tests\Feature;

use App\Models\ScoutPlayerReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScoutPlayerReportManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_list_reports_shared_with_club_or_team(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $scout = User::factory()->create(['role' => 'scout']);

        ScoutPlayerReport::query()->create([
            'scout_user_id' => $scout->id,
            'player_name' => 'Aydin',
            'position' => 'Forvet',
            'rating' => 8.1,
            'status' => 'review',
            'scout_name' => 'Scout One',
            'shared_roles' => ['club'],
            'note' => 'Manager review note for shared report.',
        ]);

        ScoutPlayerReport::query()->create([
            'scout_user_id' => $scout->id,
            'player_name' => 'Hidden Player',
            'position' => 'Orta Saha',
            'rating' => 7.4,
            'status' => 'observe',
            'scout_name' => 'Scout One',
            'shared_roles' => ['coach'],
            'note' => 'This report should stay hidden from managers.',
        ]);

        Sanctum::actingAs($manager, ['staff', 'profile:read']);

        $this->getJson('/api/scout/player-reports')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.player_name', 'Aydin');
    }
}
