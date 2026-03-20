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

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scout.ai_analysis.mode', 'mock');
    }

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

    public function test_worker_callback_can_complete_external_video_analysis(): void
    {
        config()->set('scout.ai_analysis.callback_secret', 'local-secret');

        $player = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::create([
            'user_id' => $player->id,
            'title' => 'External Match Video',
            'video_url' => 'https://example.com/external-match-video',
            'thumbnail_url' => 'https://example.com/external-thumb.jpg',
            'platform' => 'custom',
        ]);

        $analysis = VideoAnalysis::create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'status' => 'processing',
            'worker_status' => 'submitted',
            'analysis_version' => 'external-worker',
        ]);

        $response = $this->withHeaders([
            'X-Analysis-Callback-Secret' => 'local-secret',
        ])->postJson('/api/video-analyses/'.$analysis->id.'/callback', [
            'status' => 'completed',
            'analysis_version' => 'external-worker-v1',
            'summary' => [
                'successful_passes' => 14,
                'successful_crosses' => 3,
                'speed_score' => 80,
            ],
            'targets' => [[
                'player_id' => $player->id,
                'label' => $player->name,
                'jersey_number' => '7',
            ]],
            'events' => [[
                'target_player_id' => $player->id,
                'event_type' => 'cross',
                'start_second' => 32,
                'end_second' => 37,
                'confidence' => 88.5,
                'payload' => ['successful' => true],
                'clips' => [[
                    'clip_url' => 'https://example.com/external-match-video#t=32,37',
                    'thumbnail_url' => 'https://example.com/external-thumb.jpg',
                    'clip_start_second' => 32,
                    'clip_end_second' => 37,
                ]],
            ]],
            'metrics' => [[
                'player_id' => $player->id,
                'passes' => 20,
                'successful_passes' => 14,
                'cross_attempts' => 5,
                'successful_crosses' => 3,
                'shots' => 2,
                'dribbles' => 4,
                'ball_recoveries' => 1,
                'movement_score' => 82,
                'speed_score' => 80,
                'cross_quality_score' => 86,
            ]],
            'raw_output' => [
                'engine' => 'external-worker',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.summary.successful_crosses', 3);

        $this->assertDatabaseHas('video_analysis_events', [
            'video_analysis_id' => $analysis->id,
            'event_type' => 'cross',
        ]);
        $this->assertDatabaseHas('player_video_metrics', [
            'video_analysis_id' => $analysis->id,
            'successful_crosses' => 3,
        ]);
    }

    public function test_ai_discovery_and_rankings_endpoints_return_metric_based_players(): void
    {
        $player = User::factory()->create([
            'role' => 'player',
            'name' => 'Ahmet Yildiz',
            'city' => 'Istanbul',
            'position' => 'Forvet',
        ]);

        $clip = VideoClip::create([
            'user_id' => $player->id,
            'title' => 'Discovery Video',
            'video_url' => 'https://example.com/discovery-video',
            'thumbnail_url' => 'https://example.com/discovery-thumb.jpg',
            'platform' => 'custom',
        ]);

        Sanctum::actingAs($player, ['profile:read', 'profile:write']);

        $this->postJson('/api/video-analyses/start', [
            'video_clip_id' => $clip->id,
            'target_player_id' => $player->id,
            'analysis_type' => 'scout_mvp',
        ])->assertStatus(201);

        $this->getJson('/api/scouting-search/discovery?search=Ahmet&city=Istanbul&sort=speed_score_desc')
            ->assertOk()
            ->assertJsonPath('data.0.player_id', $player->id)
            ->assertJsonPath('data.0.name', 'Ahmet Yildiz');

        $this->getJson('/api/scouting-search/rankings?limit=5')
            ->assertOk()
            ->assertJsonPath('data.best_speed.0.player_id', $player->id)
            ->assertJsonPath('data.best_crosses.0.player_id', $player->id);
    }
}
