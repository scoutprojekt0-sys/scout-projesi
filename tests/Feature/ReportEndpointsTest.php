<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_routes_require_authentication(): void
    {
        $this->postJson('/api/reports')->assertStatus(401);
        $this->getJson('/api/reports/my-reports')->assertStatus(401);
        $this->getJson('/api/reports/1')->assertStatus(401);
    }

    public function test_user_can_create_and_list_own_reports(): void
    {
        $reporter = User::factory()->create(['role' => 'manager']);
        $reported = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($reporter, ['profile:read', 'profile:write']);

        $create = $this->postJson('/api/reports', [
            'reported_user_id' => $reported->id,
            'reason' => 'spam',
            'description' => 'Repeated spam behavior.',
        ]);

        $create
            ->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.reporter_user_id', $reporter->id)
            ->assertJsonPath('data.reported_user_id', $reported->id);

        $reportId = (int) $create->json('data.id');

        $this->getJson('/api/reports/my-reports')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $reportId);

        $this->getJson('/api/reports/'.$reportId)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $reportId)
            ->assertJsonPath('data.reported_user.name', $reported->name)
            ->assertJsonMissingPath('data.reported_user.email');
    }

    public function test_user_cannot_view_another_users_report(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $other = User::factory()->create(['role' => 'manager']);
        $reported = User::factory()->create(['role' => 'player']);

        Sanctum::actingAs($owner, ['profile:write']);
        $reportId = (int) $this->postJson('/api/reports', [
            'reported_user_id' => $reported->id,
            'reason' => 'other',
        ])->json('data.id');

        Sanctum::actingAs($other, ['profile:read']);

        $this->getJson('/api/reports/'.$reportId)->assertStatus(404);
    }
}
