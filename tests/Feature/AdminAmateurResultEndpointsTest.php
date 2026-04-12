<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAmateurResultEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_amateur_result_endpoints_return_minimized_payloads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $create = $this->postJson('/api/admin/amateur-results', [
            'league' => 'Istanbul Super Amateur',
            'season' => '2025-26',
            'home_team' => 'Team A',
            'away_team' => 'Team B',
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.league', 'Istanbul Super Amateur')
            ->assertJsonMissingPath('data.source');

        $resultId = (int) $create->json('data.id');

        $this->patchJson('/api/admin/amateur-results/'.$resultId.'/status', [
            'status' => 'verified',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonMissingPath('data.source');

        $this->getJson('/api/admin/amateur-results')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $resultId)
            ->assertJsonMissingPath('data.data.0.source');
    }
}
