<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerSearchEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_search_routes_require_authentication(): void
    {
        $this->postJson('/api/search/players')->assertStatus(401);
        $this->getJson('/api/search/saved')->assertStatus(401);
        $this->getJson('/api/search/1/results')->assertStatus(401);
    }

    public function test_user_can_search_players_and_save_search(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $matchingPlayer = User::factory()->create([
            'role' => 'player',
            'name' => 'Matching Player',
            'city' => 'Istanbul',
            'rating' => 8.4,
        ]);
        $nonMatchingPlayer = User::factory()->create([
            'role' => 'player',
            'name' => 'Other Player',
            'city' => 'Ankara',
            'rating' => 5.2,
        ]);

        DB::table('player_profiles')->insert([
            [
                'user_id' => $matchingPlayer->id,
                'birth_year' => now()->year - 22,
                'position' => 'FW',
                'height_cm' => 182,
                'current_team' => 'Alpha',
                'updated_at' => now(),
            ],
            [
                'user_id' => $nonMatchingPlayer->id,
                'birth_year' => now()->year - 31,
                'position' => 'GK',
                'height_cm' => 195,
                'current_team' => 'Beta',
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($manager, ['profile:read', 'profile:write']);

        $response = $this->postJson('/api/search/players', [
            'position' => 'FW',
            'city' => 'Istanbul',
            'min_age' => 20,
            'max_age' => 25,
            'min_height_cm' => 175,
            'max_height_cm' => 190,
            'min_rating' => 7.5,
            'save_search' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total_results', 1)
            ->assertJsonPath('data.data.0.player.name', 'Matching Player');

        $searchId = (int) $response->json('search_id');

        $this->getJson('/api/search/saved')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.id', $searchId)
            ->assertJsonPath('data.data.0.results_count', 1);

        $this->getJson('/api/search/'.$searchId.'/results')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.player.name', 'Matching Player')
            ->assertJsonMissingPath('data.data.0.player.email')
            ->assertJsonPath('data.data.0.match_details.0', 'Pozisyon uyumlu');
    }

    public function test_user_cannot_read_other_users_search_results(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $other = User::factory()->create(['role' => 'manager']);
        $player = User::factory()->create([
            'role' => 'player',
            'city' => 'Istanbul',
            'rating' => 8.0,
        ]);

        DB::table('player_profiles')->insert([
            'user_id' => $player->id,
            'birth_year' => now()->year - 22,
            'position' => 'FW',
            'height_cm' => 180,
            'current_team' => 'Alpha',
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner, ['profile:read', 'profile:write']);
        $searchId = (int) $this->postJson('/api/search/players', [
            'position' => 'FW',
            'save_search' => true,
        ])->json('search_id');

        Sanctum::actingAs($other, ['profile:read']);

        $this->getJson('/api/search/'.$searchId.'/results')
            ->assertStatus(403)
            ->assertJsonPath('ok', false);
    }
}
