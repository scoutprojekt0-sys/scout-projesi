<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_routes_require_authentication(): void
    {
        $this->getJson('/api/favorites')->assertStatus(401);
        $this->postJson('/api/favorites/2/toggle')->assertStatus(401);
        $this->getJson('/api/favorites/2/check')->assertStatus(401);
    }

    public function test_user_can_toggle_check_and_list_favorites(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $target = User::factory()->create([
            'role' => 'team',
            'name' => 'Target Team',
            'city' => 'Istanbul',
        ]);

        Sanctum::actingAs($user, ['profile:read', 'profile:write']);

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 0);

        $this->getJson('/api/favorites/'.$target->id.'/check')
            ->assertOk()
            ->assertJsonPath('data.is_favorited', false);

        $this->postJson('/api/favorites/'.$target->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.is_favorited', true);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'target_user_id' => $target->id,
        ]);

        $this->getJson('/api/favorites/'.$target->id.'/check')
            ->assertOk()
            ->assertJsonPath('data.is_favorited', true);

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.target_user.name', 'Target Team');

        $this->postJson('/api/favorites/'.$target->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_favorited', false);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'target_user_id' => $target->id,
        ]);
    }

    public function test_user_cannot_favorite_self(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:write']);

        $this->postJson('/api/favorites/'.$user->id.'/toggle')
            ->assertStatus(400)
            ->assertJsonPath('ok', false);
    }
}
