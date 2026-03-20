<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VideoAnalysis;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoAnalysisEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_video_analysis_and_read_outputs(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::create([
            'user_id' => $player->id,
            'title' => 'U17 Match Video',
            'video_url' => 'https://example.com/match-video',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'platform' => 'custom',
        ]);

        Sanctum::actingAs($player, ['profile:read', 'profile:write']);

        $start = $this->postJson('/api/video-analyses/start', [
            'video_clip_id' => $clip->id,
            'target_player_id' => $player->id,
            'analysis_type' => 'scout_mvp',
        ]);

        $start
            ->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('meta.analysis_source', 'fresh')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.summary.successful_crosses', 2);

        $analysisId = $start->json('data.id');

        $this->getJson('/api/video-analyses/'.$analysisId)
            ->assertOk()
            ->assertJsonPath('data.id', $analysisId)
            ->assertJsonPath('data.status', 'completed');

        $this->getJson('/api/video-analyses/'.$analysisId.'/events')
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'pass');

        $this->getJson('/api/video-analyses/'.$analysisId.'/clips')
            ->assertOk()
            ->assertJsonPath('data.0.event.event_type', 'pass');

        $this->getJson('/api/players/'.$player->id.'/video-metrics')
            ->assertOk()
            ->assertJsonPath('data.0.successful_crosses', 2);

        $this->getJson('/api/scouting-search/video-metrics?min_successful_crosses=2&sort=successful_crosses_desc')
            ->assertOk()
            ->assertJsonPath('data.0.player_id', $player->id);
    }

    public function test_existing_video_analysis_is_reused_for_same_video_and_target(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::create([
            'user_id' => $player->id,
            'title' => 'Repeat Match Video',
            'video_url' => 'https://example.com/repeat-match-video',
            'thumbnail_url' => 'https://example.com/repeat-thumb.jpg',
            'platform' => 'custom',
        ]);

        Sanctum::actingAs($player, ['profile:read', 'profile:write']);

        $first = $this->postJson('/api/video-analyses/start', [
            'video_clip_id' => $clip->id,
            'target_player_id' => $player->id,
            'analysis_type' => 'scout_mvp',
        ]);

        $first->assertStatus(201);
        $firstAnalysisId = $first->json('data.id');

        $second = $this->postJson('/api/video-analyses/start', [
            'video_clip_id' => $clip->id,
            'target_player_id' => $player->id,
            'analysis_type' => 'scout_mvp',
        ]);

        $second
            ->assertStatus(200)
            ->assertJsonPath('meta.analysis_source', 'cached')
            ->assertJsonPath('data.id', $firstAnalysisId)
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(1, VideoAnalysis::query()->count());
    }
}
