<?php

namespace Tests\Feature;

use App\Models\ScoutPlayerReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScoutPlayerReportEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scout_only_sees_own_reports_and_can_update_own_status(): void
    {
        $ownerScout = User::factory()->create(['role' => 'scout']);
        $otherScout = User::factory()->create(['role' => 'scout']);
        $player = User::factory()->create(['role' => 'player']);

        $ownedReport = ScoutPlayerReport::query()->create([
            'scout_user_id' => $ownerScout->id,
            'player_user_id' => $player->id,
            'player_name' => 'Target Player',
            'position' => 'FW',
            'rating' => 7.8,
            'status' => 'review',
            'scout_name' => 'Owner Scout',
            'note' => 'Takip edilmeli ve tekrar izlenmeli.',
        ]);

        ScoutPlayerReport::query()->create([
            'scout_user_id' => $otherScout->id,
            'player_user_id' => $player->id,
            'player_name' => 'Target Player',
            'position' => 'FW',
            'rating' => 8.1,
            'status' => 'shortlist',
            'scout_name' => 'Other Scout',
            'note' => 'Diger scout raporu.',
        ]);

        Sanctum::actingAs($ownerScout, ['profile:read', 'profile:write']);

        $this->getJson('/api/scout-player-reports')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $ownedReport->id);

        $this->postJson('/api/scout-player-reports/'.$ownedReport->id.'/status', [
            'status' => 'observe',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'observe');
    }

    public function test_scout_cannot_update_other_scout_report_and_non_scout_cannot_access_workspace(): void
    {
        $ownerScout = User::factory()->create(['role' => 'scout']);
        $otherScout = User::factory()->create(['role' => 'scout']);
        $manager = User::factory()->create(['role' => 'manager']);

        $report = ScoutPlayerReport::query()->create([
            'scout_user_id' => $ownerScout->id,
            'player_name' => 'Private Player',
            'position' => 'CM',
            'rating' => 6.9,
            'status' => 'review',
            'scout_name' => 'Owner Scout',
            'note' => 'Sadece olusturan scout tarafindan guncellenmeli.',
        ]);

        Sanctum::actingAs($otherScout, ['profile:write']);

        $this->postJson('/api/scout-player-reports/'.$report->id.'/status', [
            'status' => 'reject',
        ])->assertStatus(404);

        Sanctum::actingAs($manager, ['profile:read']);

        $this->getJson('/api/scout-player-reports')
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'forbidden_role');
    }
}
