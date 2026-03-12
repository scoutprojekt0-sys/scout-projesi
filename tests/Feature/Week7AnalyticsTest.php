<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Opportunity;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Week7AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_requires_authentication(): void
    {
        $this->getJson('/api/analytics/admin-overview')->assertStatus(401);
    }

    public function test_admin_overview_returns_aggregated_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'manager']);
        $team = User::factory()->create(['role' => 'team']);
        $player = User::factory()->create(['role' => 'player', 'rating' => 8.2]);

        $opportunity = Opportunity::factory()->create([
            'team_user_id' => $team->id,
            'status' => 'open',
        ]);

        Application::create([
            'opportunity_id' => $opportunity->id,
            'player_user_id' => $player->id,
            'status' => 'pending',
        ]);

        PlayerTransfer::create([
            'player_id' => $player->id,
            'to_club_id' => $team->id,
            'fee' => 300000,
            'currency' => 'EUR',
            'transfer_date' => now()->subDays(5)->toDateString(),
            'transfer_type' => 'permanent',
            'season' => '2025-26',
            'window' => 'summer',
            'verification_status' => 'verified',
        ]);

        Sanctum::actingAs($admin, ['profile:read']);

        $response = $this->getJson('/api/analytics/admin-overview');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.users.total', 3)
            ->assertJsonPath('data.opportunities.total', 1)
            ->assertJsonPath('data.applications.total', 1)
            ->assertJsonPath('data.transfer_activity_last_30_days.count', 1);
    }

    public function test_team_scouting_funnel_returns_funnel_stats(): void
    {
        $authUser = User::factory()->create(['role' => 'manager']);
        $team = User::factory()->create(['role' => 'team', 'name' => 'Demo Team']);
        $playerA = User::factory()->create(['role' => 'player', 'rating' => 7.8]);
        $playerB = User::factory()->create(['role' => 'player', 'rating' => 8.4]);

        $oppOpen = Opportunity::factory()->create([
            'team_user_id' => $team->id,
            'status' => 'open',
        ]);

        $oppClosed = Opportunity::factory()->closed()->create([
            'team_user_id' => $team->id,
        ]);

        Application::create([
            'opportunity_id' => $oppOpen->id,
            'player_user_id' => $playerA->id,
            'status' => 'pending',
        ]);

        Application::create([
            'opportunity_id' => $oppClosed->id,
            'player_user_id' => $playerB->id,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($authUser, ['profile:read']);

        $response = $this->getJson('/api/analytics/team/'.$team->id);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.opportunities.total', 2)
            ->assertJsonPath('data.application_funnel.pending', 1)
            ->assertJsonPath('data.application_funnel.accepted', 1)
            ->assertJsonPath('data.application_funnel.total', 2);
    }

    public function test_team_scouting_funnel_returns_404_for_non_team_user(): void
    {
        $authUser = User::factory()->create(['role' => 'manager']);
        $notTeam = User::factory()->create(['role' => 'player']);

        Sanctum::actingAs($authUser, ['profile:read']);

        $this->getJson('/api/analytics/team/'.$notTeam->id)
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }
}
