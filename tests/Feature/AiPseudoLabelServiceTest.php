<?php

namespace Tests\Feature;

use App\Models\AiDatasetCandidate;
use App\Models\User;
use App\Models\VideoAnalysis;
use App\Models\VideoAnalysisEvent;
use App\Models\VideoClip;
use App\Services\AiPseudoLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AiPseudoLabelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_writes_pseudo_label_for_frame_matching_high_confidence_event(): void
    {
        $user = User::factory()->create(['role' => 'player', 'sport' => 'football']);
        $clip = VideoClip::query()->create([
            'user_id' => $user->id,
            'title' => 'Pseudo Clip',
            'video_url' => 'https://example.com/pseudo.mp4',
            'platform' => 'custom',
            'tags' => ['football'],
            'metadata' => ['sport' => 'football', 'ai_dataset_candidate' => true],
        ]);
        $sourceKey = 'candidate_1_clip_'.$clip->id.'_pseudo_clip';
        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $user->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_APPROVED,
            'metadata' => [
                'source_key' => $sourceKey,
                'export' => [
                    'source_key' => $sourceKey,
                    'raw_video_path' => str_replace('\\', '/', base_path('raw_videos/football/'.$sourceKey.'.mp4')),
                ],
            ],
        ]);
        $analysis = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $user->id,
            'target_player_id' => $user->id,
            'analysis_type' => 'continuous_learning',
            'provider' => 'external',
            'status' => 'completed',
            'worker_status' => 'completed',
            'analysis_version' => 'external-worker',
        ]);
        VideoAnalysisEvent::query()->create([
            'video_analysis_id' => $analysis->id,
            'target_player_id' => $user->id,
            'event_type' => 'dribble',
            'start_second' => 4,
            'end_second' => 6,
            'confidence' => 82,
        ]);

        $datasetDir = base_path('ai-worker/datasets/football');
        File::ensureDirectoryExists($datasetDir.'/images/train');
        File::ensureDirectoryExists($datasetDir.'/labels/train');
        $imagePath = str_replace('\\', '/', $datasetDir.'/images/train/'.$sourceKey.'_s5.jpg');
        $labelPath = str_replace('\\', '/', $datasetDir.'/labels/train/'.$sourceKey.'_s5.txt');
        File::put($imagePath, 'img');
        File::put($labelPath, '');
        File::put(
            $datasetDir.'/manifest.csv',
            implode(PHP_EOL, [
                'source_video,source_key,frame_path,split,labels_path,needs_labeling,notes',
                '/app/raw_videos/football/'.$sourceKey.'.mp4,'.$sourceKey.','.$imagePath.',train,/app/datasets/football/labels/train/'.$sourceKey.'_s5.txt,yes,',
            ]).PHP_EOL
        );

        $result = app(AiPseudoLabelService::class)->writeLabelsForSport('football');

        $candidate->refresh();
        $this->assertSame(1, $result['updated']);
        $this->assertSame("0 0.500000 0.500000 0.350000 0.550000\n", File::get($labelPath));
        $this->assertSame($analysis->id, data_get($candidate->metadata, 'pseudo_label_policy.analysis_id'));
        $this->assertTrue((bool) data_get($candidate->metadata, 'pseudo_label_policy.requires_manual_review'));
    }
}
