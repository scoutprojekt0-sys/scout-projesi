<?php

namespace App\Services;

use App\Models\PlayerVideoMetric;
use App\Models\VideoAnalysis;
use App\Models\VideoAnalysisClip;
use App\Models\VideoAnalysisEvent;
use App\Models\VideoAnalysisTarget;
use Illuminate\Support\Facades\DB;

class VideoAnalysisResultService
{
    public function complete(VideoAnalysis $analysis, array $payload): VideoAnalysis
    {
        return DB::transaction(function () use ($analysis, $payload) {
            $analysis->events()->delete();
            $analysis->metrics()->delete();
            $analysis->targets()->delete();

            $targets = collect($payload['targets'] ?? []);
            $events = collect($payload['events'] ?? []);
            $metrics = collect($payload['metrics'] ?? []);
            $summary = (array) ($payload['summary'] ?? []);

            $targets->each(function (array $targetData) use ($analysis) {
                VideoAnalysisTarget::create([
                    'video_analysis_id' => $analysis->id,
                    'player_id' => $targetData['player_id'] ?? $analysis->target_player_id,
                    'label' => $targetData['label'] ?? null,
                    'jersey_number' => $targetData['jersey_number'] ?? null,
                    'reference_data' => $targetData['reference_data'] ?? null,
                ]);
            });

            $events->each(function (array $eventData) use ($analysis) {
                $event = VideoAnalysisEvent::create([
                    'video_analysis_id' => $analysis->id,
                    'target_player_id' => $eventData['target_player_id'] ?? $analysis->target_player_id,
                    'event_type' => $eventData['event_type'],
                    'start_second' => (int) ($eventData['start_second'] ?? 0),
                    'end_second' => (int) ($eventData['end_second'] ?? 0),
                    'confidence' => $eventData['confidence'] ?? 0,
                    'payload' => $eventData['payload'] ?? null,
                ]);

                collect($eventData['clips'] ?? [])->each(function (array $clipData) use ($event) {
                    VideoAnalysisClip::create([
                        'video_analysis_event_id' => $event->id,
                        'clip_url' => $clipData['clip_url'],
                        'thumbnail_url' => $clipData['thumbnail_url'] ?? null,
                        'clip_start_second' => $clipData['clip_start_second'] ?? null,
                        'clip_end_second' => $clipData['clip_end_second'] ?? null,
                        'metadata' => $clipData['metadata'] ?? null,
                    ]);
                });
            });

            $metrics->each(function (array $metricData) use ($analysis) {
                PlayerVideoMetric::create([
                    'player_id' => $metricData['player_id'] ?? $analysis->target_player_id,
                    'video_analysis_id' => $analysis->id,
                    'passes' => (int) ($metricData['passes'] ?? 0),
                    'successful_passes' => (int) ($metricData['successful_passes'] ?? 0),
                    'cross_attempts' => (int) ($metricData['cross_attempts'] ?? 0),
                    'successful_crosses' => (int) ($metricData['successful_crosses'] ?? 0),
                    'shots' => (int) ($metricData['shots'] ?? 0),
                    'dribbles' => (int) ($metricData['dribbles'] ?? 0),
                    'ball_recoveries' => (int) ($metricData['ball_recoveries'] ?? 0),
                    'movement_score' => (int) ($metricData['movement_score'] ?? 0),
                    'speed_score' => (int) ($metricData['speed_score'] ?? 0),
                    'cross_quality_score' => (int) ($metricData['cross_quality_score'] ?? 0),
                    'metadata' => $metricData['metadata'] ?? null,
                ]);
            });

            $analysis->update([
                'status' => 'completed',
                'analysis_version' => $payload['analysis_version'] ?? $analysis->analysis_version,
                'worker_status' => 'completed',
                'summary' => $summary,
                'raw_output' => $payload['raw_output'] ?? null,
                'failure_reason' => null,
                'completed_at' => now(),
                'failed_at' => null,
            ]);

            return $analysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']);
        });
    }

    public function fail(VideoAnalysis $analysis, string $reason, ?array $rawOutput = null): VideoAnalysis
    {
        $analysis->update([
            'status' => 'failed',
            'worker_status' => 'failed',
            'failure_reason' => $reason,
            'raw_output' => $rawOutput,
            'failed_at' => now(),
        ]);

        return $analysis->fresh();
    }
}
