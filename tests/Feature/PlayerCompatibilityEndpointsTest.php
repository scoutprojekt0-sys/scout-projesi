<?php

namespace Tests\Feature;

use App\Models\ProfileView;
use App\Models\User;
use App\Models\Media;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlayerCompatibilityEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_compatibility_stats_endpoint_returns_likes_and_views(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        ProfileView::query()->create([
            'viewer_user_id' => null,
            'viewed_user_id' => $player->id,
            'ip_address' => '127.0.0.1',
            'viewed_at' => now(),
        ]);

        Cache::put('legacy_profile_like_player_'.$player->id, 4, now()->addDay());

        $this->getJson('/api/profile-cards/player/'.$player->id.'/stats')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.card_type', 'player')
            ->assertJsonPath('data.card_owner_id', $player->id)
            ->assertJsonPath('data.likes', 4)
            ->assertJsonPath('data.total_views', 1);
    }

    public function test_player_video_portfolio_endpoint_returns_player_videos(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        $clip = VideoClip::query()->create([
            'user_id' => $player->id,
            'title' => 'Scout Highlight',
            'description' => 'Latest highlight reel',
            'video_url' => 'https://example.com/highlight.mp4',
            'thumbnail_url' => 'https://example.com/highlight.jpg',
            'platform' => 'custom',
            'duration_seconds' => 95,
            'match_date' => '2026-04-01',
            'tags' => ['football'],
        ]);

        $this->getJson('/api/video-portfolio/player/'.$player->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.id', $clip->id)
            ->assertJsonPath('data.0.title', 'Scout Highlight')
            ->assertJsonPath('data.0.video_url', 'https://example.com/highlight.mp4')
            ->assertJsonPath('data.0.video_type', 'custom');
    }

    public function test_public_player_media_endpoint_returns_media_without_auth(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        Media::query()->create([
            'user_id' => $player->id,
            'type' => 'image',
            'url' => 'https://example.com/player-photo.jpg',
            'thumb_url' => 'https://example.com/player-photo-thumb.jpg',
            'title' => 'Player Photo',
        ]);

        $this->getJson('/api/public/players/'.$player->id.'/media')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.title', 'Player Photo')
            ->assertJsonPath('data.data.0.url', 'https://example.com/player-photo.jpg');
    }
}
