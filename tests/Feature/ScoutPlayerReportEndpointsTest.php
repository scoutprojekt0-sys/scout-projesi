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

    public function test_club_and_coach_see_reports_shared_with_their_roles(): void
    {
        $scout = User::factory()->create(['role' => 'scout']);
        $club = User::factory()->create(['role' => 'club']);
        $coach = User::factory()->create(['role' => 'coach']);

        $sharedReport = ScoutPlayerReport::query()->create([
            'scout_user_id' => $scout->id,
            'player_name' => 'Shared Player',
            'position' => 'RW',
            'rating' => 8.4,
            'status' => 'shortlist',
            'scout_name' => 'Sharing Scout',
            'shared_roles' => ['club', 'coach'],
            'note' => 'Kulup ve antrenor tarafina acik rapor.',
        ]);

        ScoutPlayerReport::query()->create([
            'scout_user_id' => $scout->id,
            'player_name' => 'Private Player',
            'position' => 'CM',
            'rating' => 7.1,
            'status' => 'review',
            'scout_name' => 'Sharing Scout',
            'shared_roles' => [],
            'note' => 'Sadece scout tarafinda kalmali.',
        ]);

        Sanctum::actingAs($club, ['profile:read']);

        $this->getJson('/api/scout-player-reports')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $sharedReport->id)
            ->assertJsonPath('data.data.0.shared_roles.0', 'club')
            ->assertJsonPath('data.data.0.shared_roles.1', 'coach');

        Sanctum::actingAs($coach, ['profile:read']);

        $this->getJson('/api/scout-player-reports')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $sharedReport->id);
    }

    public function test_shared_report_can_be_marked_observe_and_adds_player_to_follow_list(): void
    {
        $scout = User::factory()->create(['role' => 'scout']);
        $club = User::factory()->create(['role' => 'club']);
        $coach = User::factory()->create(['role' => 'coach']);
        $player = User::factory()->create(['role' => 'player']);

        $sharedReport = ScoutPlayerReport::query()->create([
            'scout_user_id' => $scout->id,
            'player_user_id' => $player->id,
            'player_name' => 'Tracked Player',
            'position' => 'RW',
            'rating' => 8.4,
            'status' => 'review',
            'scout_name' => 'Sharing Scout',
            'shared_roles' => ['club', 'coach'],
            'note' => 'Kulup ve antrenor tarafindan takip edilebilir rapor.',
        ]);

        Sanctum::actingAs($club, ['profile:write']);

        $this->postJson('/api/scout-player-reports/'.$sharedReport->id.'/status', [
            'status' => 'observe',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'observe');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $club->id,
            'target_user_id' => $player->id,
        ]);

        Sanctum::actingAs($coach, ['profile:write']);

        $this->postJson('/api/scout-player-reports/'.$sharedReport->id.'/status', [
            'status' => 'reject',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reject');
    }

    public function test_role_cannot_update_report_that_is_not_shared_with_them(): void
    {
        $scout = User::factory()->create(['role' => 'scout']);
        $coach = User::factory()->create(['role' => 'coach']);

        $clubOnlyReport = ScoutPlayerReport::query()->create([
            'scout_user_id' => $scout->id,
            'player_name' => 'Club Only Player',
            'position' => 'CM',
            'rating' => 7.4,
            'status' => 'review',
            'scout_name' => 'Sharing Scout',
            'shared_roles' => ['club'],
            'note' => 'Sadece kulup tarafina acik rapor.',
        ]);

        Sanctum::actingAs($coach, ['profile:write']);

        $this->postJson('/api/scout-player-reports/'.$clubOnlyReport->id.'/status', [
            'status' => 'observe',
        ])->assertStatus(404);
    }

    public function test_scout_report_store_auto_binds_unique_player_without_manual_id(): void
    {
        $scout = User::factory()->create(['role' => 'scout']);
        $player = User::factory()->create([
            'role' => 'player',
            'name' => 'Figen Altas',
            'age' => 19,
            'position' => 'Kanat',
        ]);

        Sanctum::actingAs($scout, ['profile:write']);

        $this->postJson('/api/scout-player-reports', [
            'player_name' => 'Figen Altas',
            'position' => 'Kanat',
            'age' => 19,
            'overall_rating' => 84,
            'status' => 'shortlist',
            'club' => 'Demo Club',
            'shared_roles' => ['club', 'coach'],
            'note' => 'Teknik ve hizli oyuncu.',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player_id', $player->id)
            ->assertJsonPath('data.shared_roles.0', 'club')
            ->assertJsonPath('data.shared_roles.1', 'coach');
    }
}
