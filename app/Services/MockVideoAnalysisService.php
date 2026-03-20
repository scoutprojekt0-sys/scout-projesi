<?php

namespace App\Services;

use App\Models\PlayerVideoMetric;
use App\Models\VideoAnalysis;
use App\Models\VideoAnalysisClip;
use App\Models\VideoAnalysisEvent;
use App\Models\VideoAnalysisTarget;
use Illuminate\Support\Facades\DB;

class MockVideoAnalysisService
{
    public function run(VideoAnalysis $analysis): VideoAnalysis
    {
        $analysis->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        return DB::transaction(function () use ($analysis) {
            $analysis->events()->delete();
            $analysis->metrics()->delete();
            $analysis->targets()->delete();

            $target = VideoAnalysisTarget::create([
                'video_analysis_id' => $analysis->id,
                'player_id' => $analysis->target_player_id,
                'label' => $analysis->targetPlayer?->name ?: 'Target Player',
                'jersey_number' => '11',
                'reference_data' => [
                    'selection_mode' => $analysis->target_player_id ? 'linked_player' : 'manual',
                ],
            ]);

            $events = collect([
                ['event_type' => 'pass', 'start_second' => 18, 'end_second' => 22, 'confidence' => 91.4, 'payload' => ['successful' => true, 'distance_m' => 14]],
                ['event_type' => 'cross', 'start_second' => 43, 'end_second' => 48, 'confidence' => 87.2, 'payload' => ['successful' => true, 'target_zone' => 'back_post']],
                ['event_type' => 'dribble', 'start_second' => 65, 'end_second' => 70, 'confidence' => 82.6, 'payload' => ['successful' => true, 'opponents_beaten' => 1]],
                ['event_type' => 'shot', 'start_second' => 91, 'end_second' => 95, 'confidence' => 79.8, 'payload' => ['on_target' => true]],
                ['event_type' => 'ball_recovery', 'start_second' => 109, 'end_second' => 112, 'confidence' => 76.1, 'payload' => ['zone' => 'middle_third']],
            ])->map(function (array $eventData) use ($analysis) {
                return VideoAnalysisEvent::create($eventData + [
                    'video_analysis_id' => $analysis->id,
                    'target_player_id' => $analysis->target_player_id,
                ]);
            });

            foreach ($events as $event) {
                VideoAnalysisClip::create([
                    'video_analysis_event_id' => $event->id,
                    'clip_url' => $analysis->videoClip->video_url.'#t='.$event->start_second.','.$event->end_second,
                    'thumbnail_url' => $analysis->videoClip->thumbnail_url,
                    'clip_start_second' => $event->start_second,
                    'clip_end_second' => $event->end_second,
                    'metadata' => [
                        'event_type' => $event->event_type,
                        'generated_by' => 'mock-video-analysis',
                    ],
                ]);
            }

            $summary = [
                'passes' => 12,
                'successful_passes' => 9,
                'cross_attempts' => 4,
                'successful_crosses' => 2,
                'shots' => 2,
                'dribbles' => 3,
                'ball_recoveries' => 2,
                'movement_score' => 81,
                'speed_score' => 77,
                'cross_quality_score' => 84,
            ];

            PlayerVideoMetric::create([
                'player_id' => $analysis->target_player_id,
                'video_analysis_id' => $analysis->id,
                'passes' => $summary['passes'],
                'successful_passes' => $summary['successful_passes'],
                'cross_attempts' => $summary['cross_attempts'],
                'successful_crosses' => $summary['successful_crosses'],
                'shots' => $summary['shots'],
                'dribbles' => $summary['dribbles'],
                'ball_recoveries' => $summary['ball_recoveries'],
                'movement_score' => $summary['movement_score'],
                'speed_score' => $summary['speed_score'],
                'cross_quality_score' => $summary['cross_quality_score'],
                'metadata' => [
                    'target_id' => $target->id,
                    'analysis_version' => 'mock-v1',
                ],
            ]);

            $analysis->update([
                'status' => 'completed',
                'summary' => $summary,
                'raw_output' => [
                    'engine' => 'mock-video-analysis',
                    'event_count' => $events->count(),
                    'target_reference' => $target->reference_data,
                ],
                'completed_at' => now(),
            ]);

            return $analysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']);
        });
    }
}
