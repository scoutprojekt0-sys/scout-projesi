<?php

namespace Database\Seeders;

use App\Models\PlayerVideoMetric;
use App\Models\User;
use App\Models\VideoAnalysis;
use App\Models\VideoAnalysisClip;
use App\Models\VideoAnalysisEvent;
use App\Models\VideoAnalysisTarget;
use App\Models\VideoClip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AiDiscoveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $players = [
            [
                'email' => 'berat.akyildiz@nextscout.demo',
                'name' => 'Berat Akyildiz',
                'city' => 'Istanbul',
                'position' => 'Forvet',
                'age' => 17,
                'rating' => 8.4,
                'photo_url' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=600&q=80',
                'video' => [
                    'title' => 'Berat Akyildiz U17 Mac Kolaji',
                    'video_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                    'thumbnail_url' => 'https://images.unsplash.com/photo-1547347298-4074fc3086f0?auto=format&fit=crop&w=600&q=80',
                    'duration_seconds' => 30,
                ],
                'summary' => [
                    'passes' => 18,
                    'successful_passes' => 14,
                    'cross_attempts' => 4,
                    'successful_crosses' => 3,
                    'shots' => 4,
                    'dribbles' => 5,
                    'ball_recoveries' => 2,
                    'movement_score' => 84,
                    'speed_score' => 88,
                    'cross_quality_score' => 81,
                ],
            ],
            [
                'email' => 'emirhan.kaya@nextscout.demo',
                'name' => 'Emirhan Kaya',
                'city' => 'Izmir',
                'position' => 'Orta Saha',
                'age' => 18,
                'rating' => 8.1,
                'photo_url' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=600&q=80',
                'video' => [
                    'title' => 'Emirhan Kaya Orta Saha Analiz Videosu',
                    'video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                    'thumbnail_url' => 'https://images.unsplash.com/photo-1518604666860-9ed391f76460?auto=format&fit=crop&w=600&q=80',
                    'duration_seconds' => 10,
                ],
                'summary' => [
                    'passes' => 26,
                    'successful_passes' => 21,
                    'cross_attempts' => 3,
                    'successful_crosses' => 2,
                    'shots' => 2,
                    'dribbles' => 4,
                    'ball_recoveries' => 5,
                    'movement_score' => 90,
                    'speed_score' => 79,
                    'cross_quality_score' => 76,
                ],
            ],
            [
                'email' => 'yusuf.demir@nextscout.demo',
                'name' => 'Yusuf Demir',
                'city' => 'Ankara',
                'position' => 'Defans',
                'age' => 19,
                'rating' => 7.9,
                'photo_url' => 'https://images.unsplash.com/photo-1521412644187-c49fa049e84d?auto=format&fit=crop&w=600&q=80',
                'video' => [
                    'title' => 'Yusuf Demir Savunma Klipleri',
                    'video_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                    'thumbnail_url' => 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=600&q=80',
                    'duration_seconds' => 30,
                ],
                'summary' => [
                    'passes' => 16,
                    'successful_passes' => 13,
                    'cross_attempts' => 2,
                    'successful_crosses' => 1,
                    'shots' => 1,
                    'dribbles' => 2,
                    'ball_recoveries' => 7,
                    'movement_score' => 82,
                    'speed_score' => 74,
                    'cross_quality_score' => 68,
                ],
            ],
        ];

        foreach ($players as $row) {
            $player = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make('Password123!'),
                    'role' => 'player',
                    'city' => $row['city'],
                    'position' => $row['position'],
                    'age' => $row['age'],
                    'rating' => $row['rating'],
                    'photo_url' => $row['photo_url'],
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'is_public' => true,
                ]
            );

            $clip = VideoClip::query()->updateOrCreate(
                [
                    'user_id' => $player->id,
                    'title' => $row['video']['title'],
                ],
                [
                    'description' => $row['name'].' icin AI discovery demo videosu',
                    'video_url' => $row['video']['video_url'],
                    'thumbnail_url' => $row['video']['thumbnail_url'],
                    'platform' => 'custom',
                    'duration_seconds' => $row['video']['duration_seconds'],
                    'match_date' => now()->subDays(7)->toDateString(),
                    'tags' => ['demo', 'ai-discovery', 'football', strtolower($row['position'])],
                    'metadata' => [
                        'seed' => 'ai_discovery_demo',
                        'sport' => 'football',
                        'ai_dataset_candidate' => true,
                    ],
                ]
            );

            $analysis = VideoAnalysis::query()->updateOrCreate(
                [
                    'video_clip_id' => $clip->id,
                    'target_player_id' => $player->id,
                    'analysis_type' => 'scout_mvp',
                ],
                [
                    'requested_by' => $player->id,
                    'provider' => 'mock',
                    'status' => 'completed',
                    'worker_status' => 'completed',
                    'analysis_version' => 'seed-demo-v1',
                    'summary' => $row['summary'],
                    'raw_output' => [
                        'engine' => 'seed-demo',
                        'seed' => 'AiDiscoveryDemoSeeder',
                    ],
                    'started_at' => now()->subDays(6),
                    'submitted_at' => now()->subDays(6),
                    'completed_at' => now()->subDays(6),
                ]
            );

            $analysis->events()->delete();
            $analysis->metrics()->delete();
            $analysis->targets()->delete();

            $target = VideoAnalysisTarget::create([
                'video_analysis_id' => $analysis->id,
                'player_id' => $player->id,
                'label' => $player->name,
                'jersey_number' => '11',
                'reference_data' => ['source' => 'seed-demo'],
            ]);

            $events = [
                ['event_type' => 'pass', 'start_second' => 12, 'end_second' => 16, 'confidence' => 89.4, 'payload' => ['successful' => true]],
                ['event_type' => 'cross', 'start_second' => 34, 'end_second' => 39, 'confidence' => 84.2, 'payload' => ['successful' => true]],
                ['event_type' => 'dribble', 'start_second' => 51, 'end_second' => 55, 'confidence' => 80.8, 'payload' => ['successful' => true]],
            ];

            foreach ($events as $eventRow) {
                $event = VideoAnalysisEvent::create([
                    'video_analysis_id' => $analysis->id,
                    'target_player_id' => $player->id,
                    'event_type' => $eventRow['event_type'],
                    'start_second' => $eventRow['start_second'],
                    'end_second' => $eventRow['end_second'],
                    'confidence' => $eventRow['confidence'],
                    'payload' => $eventRow['payload'],
                ]);

                VideoAnalysisClip::create([
                    'video_analysis_event_id' => $event->id,
                    'clip_url' => $clip->video_url.'#t='.$eventRow['start_second'].','.$eventRow['end_second'],
                    'thumbnail_url' => $clip->thumbnail_url,
                    'clip_start_second' => $eventRow['start_second'],
                    'clip_end_second' => $eventRow['end_second'],
                    'metadata' => [
                        'generated_by' => 'seed-demo',
                        'target_id' => $target->id,
                    ],
                ]);
            }

            PlayerVideoMetric::create([
                'player_id' => $player->id,
                'video_analysis_id' => $analysis->id,
                'passes' => $row['summary']['passes'],
                'successful_passes' => $row['summary']['successful_passes'],
                'cross_attempts' => $row['summary']['cross_attempts'],
                'successful_crosses' => $row['summary']['successful_crosses'],
                'shots' => $row['summary']['shots'],
                'dribbles' => $row['summary']['dribbles'],
                'ball_recoveries' => $row['summary']['ball_recoveries'],
                'movement_score' => $row['summary']['movement_score'],
                'speed_score' => $row['summary']['speed_score'],
                'cross_quality_score' => $row['summary']['cross_quality_score'],
                'metadata' => [
                    'seed' => 'ai_discovery_demo',
                    'analysis_version' => 'seed-demo-v1',
                ],
            ]);
        }
    }
}
