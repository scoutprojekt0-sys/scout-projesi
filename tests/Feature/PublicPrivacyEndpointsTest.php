<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicPrivacyEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_videos_endpoint_returns_minimized_payload(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        $clip = VideoClip::query()->create([
            'user_id' => $player->id,
            'title' => 'Public Highlight',
            'description' => 'Public clip description',
            'video_url' => 'https://example.com/highlight.mp4',
            'thumbnail_url' => 'https://example.com/highlight.jpg',
            'platform' => 'custom',
            'platform_video_id' => 'secret-platform-id',
            'duration_seconds' => 90,
            'match_date' => '2026-04-01',
            'tags' => ['football'],
            'metadata' => ['ai_dataset_candidate' => true, 'internal_note' => 'hidden'],
        ]);

        $this->getJson('/api/users/'.$player->id.'/videos')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.id', $clip->id)
            ->assertJsonPath('data.0.title', 'Public Highlight')
            ->assertJsonMissingPath('data.0.platform_video_id')
            ->assertJsonMissingPath('data.0.metadata');

        Sanctum::actingAs($player, ['profile:read']);

        $this->getJson('/api/videos/'.$clip->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $clip->id)
            ->assertJsonMissingPath('data.platform_video_id')
            ->assertJsonMissingPath('data.metadata');
    }

    public function test_public_player_media_endpoint_returns_minimized_payload(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        $mediaId = DB::table('media')->insertGetId([
            'user_id' => $player->id,
            'type' => 'image',
            'url' => 'https://example.com/media.jpg',
            'thumb_url' => null,
            'title' => 'Profile Shot',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/public/players/'.$player->id.'/media')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.id', $mediaId)
            ->assertJsonPath('data.data.0.title', 'Profile Shot')
            ->assertJsonMissingPath('data.data.0.user_id');
    }

    public function test_public_discovery_shortlists_do_not_expose_email_or_phone_fields(): void
    {
        User::factory()->create([
            'role' => 'player',
            'name' => 'Privacy Player',
            'email' => 'privacy@example.com',
            'phone' => '+90 555 123 45 67',
            'age' => 20,
            'position' => 'Forward',
            'rating' => 9,
            'views_count' => 50,
            'created_at' => now()->subDay(),
        ]);

        $this->getJson('/api/player-of-week')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Privacy Player')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.phone');

        $this->getJson('/api/trending/week')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.name', 'Privacy Player')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.phone');

        $this->getJson('/api/rising-stars')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.name', 'Privacy Player')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.phone');
    }

    public function test_public_global_search_does_not_match_private_email_queries(): void
    {
        User::factory()->create([
            'role' => 'player',
            'name' => 'Search Privacy Player',
            'email' => 'private-search@example.com',
            'city' => 'Istanbul',
        ]);

        $this->getJson('/api/public/search?q=private-search@example.com')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(0, 'data');
    }
}
