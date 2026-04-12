<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminConsistencyEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_reviewer_workload_or_sla_dashboard(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        Sanctum::actingAs($manager, $manager->tokenAbilities());

        $this->getJson('/api/analytics/reviewer-workload')
            ->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'forbidden_admin_only');

        $this->getJson('/api/analytics/sla-dashboard')
            ->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'forbidden_admin_only');
    }

    public function test_admin_can_access_reviewer_workload_and_sla_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->getJson('/api/analytics/reviewer-workload')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/analytics/sla-dashboard')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
