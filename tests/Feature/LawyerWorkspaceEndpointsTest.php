<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LawyerWorkspaceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lawyer_workspace_returns_minimized_items(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        Sanctum::actingAs($lawyer, ['profile:read', 'profile:write']);

        $this->getJson('/api/lawyer/workspace')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('data.contracts.0.user_id');
    }
}
