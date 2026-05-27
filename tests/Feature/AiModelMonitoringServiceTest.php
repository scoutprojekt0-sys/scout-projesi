<?php

namespace Tests\Feature;

use App\Models\AiActiveModel;
use App\Models\AiModelMonitoringSnapshot;
use App\Models\AiModelRollout;
use App\Models\Notification;
use App\Models\User;
use App\Models\VideoAnalysis;
use App\Models\VideoAnalysisEvent;
use App\Models\VideoClip;
use App\Services\AiModelMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_creates_drift_snapshot_for_unhealthy_runtime_metrics(): void
    {
        config()->set('scout.ai_training.monitoring.sport_thresholds.football', [
            'min_sample_size' => 3,
            'consecutive_windows_for_rollback' => 2,
            'max_failure_rate' => 0.20,
            'max_no_event_rate' => 0.20,
            'min_avg_event_confidence' => 0.70,
            'min_avg_events_per_analysis' => 1.50,
            'max_failure_rate_increase' => 0.20,
            'max_no_event_rate_increase' => 0.25,
            'max_confidence_drop' => 0.15,
            'max_events_per_analysis_drop' => 0.80,
        ]);

        AiActiveModel::query()->create([
            'sport' => 'football',
            'model_version' => 'football-v-runtime',
            'model_path' => 'E:/models/football-v-runtime.pt',
            'activated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::create([
            'user_id' => $player->id,
            'title' => 'Runtime Clip',
            'video_url' => 'https://example.com/runtime-clip',
            'thumbnail_url' => 'https://example.com/runtime-clip.jpg',
            'platform' => 'custom',
            'tags' => ['football'],
        ]);

        $failed = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'status' => 'failed',
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'analysis_version' => 'vision-pipeline-v1',
            'inference_sport' => 'football',
            'inference_model_version' => 'football-v-runtime',
            'inference_model_path' => 'E:/models/football-v-runtime.pt',
            'failed_at' => now()->subMinutes(5),
        ]);

        $completedWithoutEvents = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'status' => 'completed',
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'analysis_version' => 'vision-pipeline-v1',
            'inference_sport' => 'football',
            'inference_model_version' => 'football-v-runtime',
            'inference_model_path' => 'E:/models/football-v-runtime.pt',
            'completed_at' => now()->subMinutes(4),
        ]);

        $completedWithWeakEvents = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'status' => 'completed',
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'analysis_version' => 'vision-pipeline-v1',
            'inference_sport' => 'football',
            'inference_model_version' => 'football-v-runtime',
            'inference_model_path' => 'E:/models/football-v-runtime.pt',
            'completed_at' => now()->subMinutes(3),
        ]);

        VideoAnalysisEvent::query()->create([
            'video_analysis_id' => $completedWithWeakEvents->id,
            'target_player_id' => $player->id,
            'event_type' => 'pass',
            'start_second' => 10,
            'end_second' => 12,
            'confidence' => 0.40,
            'payload' => ['source' => 'test'],
        ]);

        $service = app(AiModelMonitoringService::class);
        $result = $service->monitorSport('football', 24, false);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['snapshot']->drift_detected);
        $this->assertSame('football-v-runtime', $result['snapshot']->model_version);
        $this->assertFalse($result['snapshot']->auto_rollback_executed);
        $this->assertDatabaseHas('ai_model_monitoring_snapshots', [
            'sport' => 'football',
            'model_version' => 'football-v-runtime',
            'drift_detected' => true,
        ]);
        $this->assertFalse($result['snapshot']->drift_summary['checks']['failure_rate']['passed']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'ai_model_drift_detected',
            'priority' => 'medium',
        ]);
        $driftNotification = Notification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'ai_model_drift_detected')
            ->firstOrFail();
        $this->assertSame('ai_model_monitoring', $driftNotification->payload['category']);
        $this->assertSame('/ai-model-monitoring', $driftNotification->payload['route']);
        $this->assertSame('ai_model_monitoring', $driftNotification->payload['screen']);
        $this->assertSame('football', $driftNotification->payload['sport']);
        $this->assertSame('football-v-runtime', $driftNotification->payload['model_version']);
        $this->assertNull($driftNotification->payload['rollback_target_model_version']);
        $this->assertTrue($driftNotification->payload['drift_detected']);
        $this->assertFalse($driftNotification->payload['auto_rollback_executed']);
    }

    public function test_monitoring_can_auto_rollback_after_consecutive_drift_windows(): void
    {
        config()->set('scout.ai_training.monitoring.sport_thresholds.football', [
            'min_sample_size' => 2,
            'consecutive_windows_for_rollback' => 2,
            'max_failure_rate' => 0.10,
            'max_no_event_rate' => 0.10,
            'min_avg_event_confidence' => 0.75,
            'min_avg_events_per_analysis' => 1.00,
            'max_failure_rate_increase' => 0.20,
            'max_no_event_rate_increase' => 0.25,
            'max_confidence_drop' => 0.15,
            'max_events_per_analysis_drop' => 0.80,
        ]);

        AiActiveModel::query()->create([
            'sport' => 'football',
            'model_version' => 'football-v2',
            'model_path' => 'E:/models/football-v2.pt',
            'activated_at' => now(),
        ]);

        AiModelRollout::query()->create([
            'sport' => 'football',
            'from_model_version' => null,
            'to_model_version' => 'football-v1',
            'action' => 'publish',
            'model_path' => 'E:/models/football-v1.pt',
            'rolled_out_at' => now()->subDays(2),
        ]);

        AiModelRollout::query()->create([
            'sport' => 'football',
            'from_model_version' => 'football-v1',
            'to_model_version' => 'football-v2',
            'action' => 'publish',
            'model_path' => 'E:/models/football-v2.pt',
            'rolled_out_at' => now()->subDay(),
        ]);

        AiModelMonitoringSnapshot::query()->create([
            'sport' => 'football',
            'model_version' => 'football-v2',
            'sample_count' => 3,
            'window_started_at' => now()->subHours(48),
            'window_ended_at' => now()->subHours(24),
            'metric_summary' => [
                'sample_count' => 3,
                'completed_count' => 2,
                'failed_count' => 1,
                'failure_rate' => 0.3333,
                'no_event_rate' => 0.5000,
                'avg_events_per_analysis' => 0.5000,
                'avg_event_confidence' => 0.4000,
            ],
            'drift_summary' => ['drift_detected' => true],
            'drift_detected' => true,
            'captured_at' => now()->subHours(24),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $scout = User::factory()->create(['role' => 'scout']);
        $player = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::create([
            'user_id' => $player->id,
            'title' => 'Rollback Clip',
            'video_url' => 'https://example.com/rollback-clip',
            'thumbnail_url' => 'https://example.com/rollback-clip.jpg',
            'platform' => 'custom',
            'tags' => ['football'],
        ]);

        VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'status' => 'failed',
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'analysis_version' => 'vision-pipeline-v1',
            'inference_sport' => 'football',
            'inference_model_version' => 'football-v2',
            'inference_model_path' => 'E:/models/football-v2.pt',
            'failed_at' => now()->subMinutes(5),
        ]);

        $completed = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'status' => 'completed',
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'analysis_version' => 'vision-pipeline-v1',
            'inference_sport' => 'football',
            'inference_model_version' => 'football-v2',
            'inference_model_path' => 'E:/models/football-v2.pt',
            'completed_at' => now()->subMinutes(4),
        ]);

        VideoAnalysisEvent::query()->create([
            'video_analysis_id' => $completed->id,
            'target_player_id' => $player->id,
            'event_type' => 'pass',
            'start_second' => 10,
            'end_second' => 11,
            'confidence' => 0.30,
            'payload' => ['source' => 'test'],
        ]);

        $service = app(AiModelMonitoringService::class);
        $result = $service->monitorSport('football', 24, true);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['snapshot']->drift_detected);
        $this->assertTrue($result['snapshot']->fresh()->auto_rollback_executed);
        $this->assertSame('football-v1', $result['snapshot']->fresh()->rollback_target_model_version);
        $this->assertSame('football-v1', AiActiveModel::query()->where('sport', 'football')->value('model_version'));
        $this->assertDatabaseHas('ai_model_rollouts', [
            'sport' => 'football',
            'to_model_version' => 'football-v1',
            'action' => 'rollback',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'ai_model_drift_detected',
            'priority' => 'medium',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $scout->id,
            'type' => 'ai_model_drift_detected',
            'priority' => 'medium',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'ai_model_auto_rollback',
            'priority' => 'high',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $scout->id,
            'type' => 'ai_model_auto_rollback',
            'priority' => 'high',
        ]);
        $this->assertCount(2, Notification::query()->where('type', 'ai_model_auto_rollback')->get());

        $rollbackNotification = Notification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'ai_model_auto_rollback')
            ->firstOrFail();
        $this->assertSame('ai_model_monitoring', $rollbackNotification->payload['category']);
        $this->assertSame('/ai-model-monitoring', $rollbackNotification->payload['route']);
        $this->assertSame('ai_model_monitoring', $rollbackNotification->payload['screen']);
        $this->assertSame('ai_model_monitoring', $rollbackNotification->payload['target']);
        $this->assertSame('football', $rollbackNotification->payload['sport']);
        $this->assertSame('football-v2', $rollbackNotification->payload['model_version']);
        $this->assertSame('football-v1', $rollbackNotification->payload['rollback_target_model_version']);
        $this->assertTrue($rollbackNotification->payload['drift_detected']);
        $this->assertTrue($rollbackNotification->payload['auto_rollback_executed']);

        $driftNotification = Notification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'ai_model_drift_detected')
            ->firstOrFail();
        $this->assertSame('ai_model_monitoring', $driftNotification->payload['category']);
        $this->assertSame('/ai-model-monitoring', $driftNotification->payload['route']);
        $this->assertSame('open_monitoring_drift', $driftNotification->payload['action']);
        $this->assertSame('football', $driftNotification->payload['sport']);
        $this->assertSame('football-v2', $driftNotification->payload['model_version']);
        $this->assertNull($driftNotification->payload['rollback_target_model_version']);
        $this->assertTrue($driftNotification->payload['drift_detected']);
        $this->assertFalse($driftNotification->payload['auto_rollback_executed']);
    }

    public function test_monitoring_uses_sport_specific_threshold_profile(): void
    {
        config()->set('scout.ai_training.monitoring.max_failure_rate', 0.50);
        config()->set('scout.ai_training.monitoring.sport_thresholds.basketball', [
            'min_sample_size' => 2,
            'max_failure_rate' => 0.10,
            'max_no_event_rate' => 0.30,
            'min_avg_event_confidence' => 0.55,
            'min_avg_events_per_analysis' => 1.00,
            'max_failure_rate_increase' => 0.10,
            'max_no_event_rate_increase' => 0.10,
            'max_confidence_drop' => 0.05,
            'max_events_per_analysis_drop' => 0.40,
        ]);

        AiActiveModel::query()->create([
            'sport' => 'basketball',
            'model_version' => 'basketball-v-runtime',
            'model_path' => 'E:/models/basketball-v-runtime.pt',
            'activated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::create([
            'user_id' => $player->id,
            'title' => 'Basketball Runtime Clip',
            'video_url' => 'https://example.com/basketball-runtime-clip',
            'thumbnail_url' => 'https://example.com/basketball-runtime-clip.jpg',
            'platform' => 'custom',
            'tags' => ['basketball'],
        ]);

        VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'status' => 'failed',
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'analysis_version' => 'vision-pipeline-v1',
            'inference_sport' => 'basketball',
            'inference_model_version' => 'basketball-v-runtime',
            'inference_model_path' => 'E:/models/basketball-v-runtime.pt',
            'failed_at' => now()->subMinutes(5),
        ]);

        $completed = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $player->id,
            'target_player_id' => $player->id,
            'status' => 'completed',
            'analysis_type' => 'scout_mvp',
            'provider' => 'external',
            'analysis_version' => 'vision-pipeline-v1',
            'inference_sport' => 'basketball',
            'inference_model_version' => 'basketball-v-runtime',
            'inference_model_path' => 'E:/models/basketball-v-runtime.pt',
            'completed_at' => now()->subMinutes(4),
        ]);

        VideoAnalysisEvent::query()->create([
            'video_analysis_id' => $completed->id,
            'target_player_id' => $player->id,
            'event_type' => 'shot',
            'start_second' => 8,
            'end_second' => 9,
            'confidence' => 0.70,
            'payload' => ['source' => 'test'],
        ]);

        $service = app(AiModelMonitoringService::class);
        $result = $service->monitorSport('basketball', 24, false);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['snapshot']->drift_detected);
        $this->assertSame(0.10, $result['snapshot']->drift_summary['threshold_profile']['max_failure_rate']);
        $this->assertSame(2, $result['snapshot']->drift_summary['threshold_profile']['min_sample_size']);
        $this->assertFalse($result['snapshot']->drift_summary['checks']['failure_rate']['passed']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'ai_model_drift_detected',
            'priority' => 'medium',
        ]);
    }
}
