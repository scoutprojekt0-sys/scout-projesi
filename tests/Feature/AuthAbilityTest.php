<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_create_opportunity(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['player']);

        $response = $this->postJson('/api/opportunities', [
            'title' => 'Test Opportunity',
        ]);

        $response->assertStatus(403);
    }

    public function test_team_can_access_opportunity_create_route_with_team_ability(): void
    {
        $user = User::factory()->create(['role' => 'team']);
        Sanctum::actingAs($user, ['team']);

        $response = $this->postJson('/api/opportunities', []);

        $response->assertStatus(422);
    }

    public function test_user_without_contact_write_ability_cannot_send_contact(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['player']);

        $response = $this->postJson('/api/contacts', [
            'to_user_id' => 2,
            'message' => 'hello',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_without_media_read_ability_cannot_list_media(): void
    {
        $user = User::factory()->create(['role' => 'team']);
        Sanctum::actingAs($user, ['team']);

        $response = $this->getJson('/api/users/1/media');

        $response->assertStatus(403);
    }

    public function test_legacy_wildcard_token_is_revoked_and_forced_to_relogin(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $token = $user->createToken('legacy-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
