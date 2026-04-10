<?php

namespace App\Services;

use App\Models\VideoAnalysis;

class MockVideoAnalysisService
{
    public function __construct(
        private readonly VideoAnalysisResultService $resultService,
    ) {
    }

    public function run(VideoAnalysis $analysis): VideoAnalysis
    {
        $analysis->update([
            'status' => 'processing',
            'provider' => 'mock',
            'worker_status' => 'running',
            'started_at' => now(),
        ]);

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

        return $this->resultService->complete($analysis, [
            'analysis_version' => 'mock-v1',
            'summary' => $summary,
            'raw_output' => [
                'engine' => 'mock-video-analysis',
                'event_count' => 5,
                'target_reference' => [
                    'selection_mode' => $analysis->target_player_id ? 'linked_player' : 'manual',
                ],
            ],
            'targets' => [[
                'player_id' => $analysis->target_player_id,
                'label' => $analysis->targetPlayer?->name ?: 'Target Player',
                'jersey_number' => '11',
                'reference_data' => [
                    'selection_mode' => $analysis->target_player_id ? 'linked_player' : 'manual',
                ],
            ]],
            'events' => [
                [
                    'target_player_id' => $analysis->target_player_id,
                    'event_type' => 'pass',
                    'start_second' => 18,
                    'end_second' => 22,
                    'confidence' => 91.4,
                    'payload' => ['successful' => true, 'distance_m' => 14],
                    'clips' => [[
                        'clip_url' => $analysis->videoClip->video_url.'#t=18,22',
                        'thumbnail_url' => $analysis->videoClip->thumbnail_url,
                        'clip_start_second' => 18,
                        'clip_end_second' => 22,
                        'metadata' => ['event_type' => 'pass', 'generated_by' => 'mock-video-analysis'],
                    ]],
                ],
                [
                    'target_player_id' => $analysis->target_player_id,
                    'event_type' => 'cross',
                    'start_second' => 43,
                    'end_second' => 48,
                    'confidence' => 87.2,
                    'payload' => ['successful' => true, 'target_zone' => 'back_post'],
                    'clips' => [[
                        'clip_url' => $analysis->videoClip->video_url.'#t=43,48',
                        'thumbnail_url' => $analysis->videoClip->thumbnail_url,
                        'clip_start_second' => 43,
                        'clip_end_second' => 48,
                        'metadata' => ['event_type' => 'cross', 'generated_by' => 'mock-video-analysis'],
                    ]],
                ],
                [
                    'target_player_id' => $analysis->target_player_id,
                    'event_type' => 'dribble',
                    'start_second' => 65,
                    'end_second' => 70,
                    'confidence' => 82.6,
                    'payload' => ['successful' => true, 'opponents_beaten' => 1],
                    'clips' => [[
                        'clip_url' => $analysis->videoClip->video_url.'#t=65,70',
                        'thumbnail_url' => $analysis->videoClip->thumbnail_url,
                        'clip_start_second' => 65,
                        'clip_end_second' => 70,
                        'metadata' => ['event_type' => 'dribble', 'generated_by' => 'mock-video-analysis'],
                    ]],
                ],
                [
                    'target_player_id' => $analysis->target_player_id,
                    'event_type' => 'shot',
                    'start_second' => 91,
                    'end_second' => 95,
                    'confidence' => 79.8,
                    'payload' => ['on_target' => true],
                    'clips' => [[
                        'clip_url' => $analysis->videoClip->video_url.'#t=91,95',
                        'thumbnail_url' => $analysis->videoClip->thumbnail_url,
                        'clip_start_second' => 91,
                        'clip_end_second' => 95,
                        'metadata' => ['event_type' => 'shot', 'generated_by' => 'mock-video-analysis'],
                    ]],
                ],
                [
                    'target_player_id' => $analysis->target_player_id,
                    'event_type' => 'ball_recovery',
                    'start_second' => 109,
                    'end_second' => 112,
                    'confidence' => 76.1,
                    'payload' => ['zone' => 'middle_third'],
                    'clips' => [[
                        'clip_url' => $analysis->videoClip->video_url.'#t=109,112',
                        'thumbnail_url' => $analysis->videoClip->thumbnail_url,
                        'clip_start_second' => 109,
                        'clip_end_second' => 112,
                        'metadata' => ['event_type' => 'ball_recovery', 'generated_by' => 'mock-video-analysis'],
                    ]],
                ],
            ],
            'metrics' => [[
                'player_id' => $analysis->target_player_id,
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
                'assist_vision_score' => (int) ($summary['assist_vision_score'] ?? 0),
                'drive_efficiency_score' => (int) ($summary['drive_efficiency_score'] ?? 0),
                'spike_quality_score' => (int) ($summary['spike_quality_score'] ?? 0),
                'block_timing_score' => (int) ($summary['block_timing_score'] ?? 0),
                'metadata' => [
                    'analysis_version' => 'mock-v1',
                ],
            ]],
        ]);
    }
}
