<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialMediaEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_social_media_accounts_without_metadata_leak(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:read', 'profile:write']);

        $create = $this->postJson('/api/social-media', [
            'platform' => 'instagram',
            'username' => 'player.handle',
            'url' => 'https://instagram.com/player.handle',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.platform', 'instagram')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.metadata');

        $accountId = (int) $create->json('data.id');

        $this->patchJson('/api/social-media/'.$accountId, [
            'follower_count' => 1200,
            'verified' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.follower_count', 1200)
            ->assertJsonPath('data.verified', true)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.metadata');

        $this->getJson('/api/users/'.$user->id.'/social-media')
            ->assertOk()
            ->assertJsonPath('data.0.platform', 'instagram')
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.metadata');
    }
}
