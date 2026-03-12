<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileViewEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_get_profile_view_count(): void
    {
        $owner = User::factory()->create(['role' => 'player']);

        $this->getJson('/api/profiles/'.$owner->id.'/views/count')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.view_count', 0);
    }

    public function test_authenticated_user_can_track_profile_view_and_list_my_views(): void
    {
        $viewer = User::factory()->create(['role' => 'scout', 'name' => 'Scout Viewer']);
        $owner = User::factory()->create(['role' => 'player', 'name' => 'Player Owner']);

        Sanctum::actingAs($viewer, ['profile:read', 'profile:write']);

        $this->postJson('/api/profiles/'.$owner->id.'/view')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/profiles/'.$owner->id.'/views/count')
            ->assertOk()
            ->assertJsonPath('data.view_count', 1);

        Sanctum::actingAs($owner, ['profile:read']);

        $this->getJson('/api/profiles/my-views')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.viewer.name', 'Scout Viewer');
    }

    public function test_tracking_own_profile_is_noop(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:write']);

        $this->postJson('/api/profiles/'.$user->id.'/view')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/profiles/'.$user->id.'/views/count')
            ->assertOk()
            ->assertJsonPath('data.view_count', 0);
    }
}
