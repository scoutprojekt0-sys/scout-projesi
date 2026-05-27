<?php

namespace Tests\Feature;

use App\Models\AiDatasetCandidate;
use App\Models\User;
use App\Models\VideoClip;
use App\Services\AiDatasetExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AiDatasetLabelSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_labeled_candidates_marks_candidate_as_labeled_when_all_labels_are_filled(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::query()->create([
            'user_id' => $user->id,
            'title' => 'Football Candidate',
            'video_url' => 'https://example.com/video.mp4',
            'platform' => 'custom',
            'metadata' => ['sport' => 'football', 'ai_dataset_candidate' => true],
        ]);

        $rawVideoPath = str_replace('\\', '/', base_path('raw_videos/football/candidate_1_clip_'.$clip->id.'_football_candidate.mp4'));
        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $user->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_LABELING,
            'split' => 'train',
            'metadata' => [
                'source_key' => 'candidate_1_clip_'.$clip->id.'_football_candidate',
                'export' => [
                    'source_key' => 'candidate_1_clip_'.$clip->id.'_football_candidate',
                    'raw_video_path' => $rawVideoPath,
                ],
            ],
        ]);

        $datasetDir = base_path('ai-worker/datasets/football');
        File::ensureDirectoryExists($datasetDir.'/images/train');
        File::ensureDirectoryExists($datasetDir.'/labels/train');

        $imageOne = str_replace('\\', '/', $datasetDir.'/images/train/frame1.jpg');
        $imageTwo = str_replace('\\', '/', $datasetDir.'/images/train/frame2.jpg');
        $labelOne = str_replace('\\', '/', $datasetDir.'/labels/train/frame1.txt');
        $labelTwo = str_replace('\\', '/', $datasetDir.'/labels/train/frame2.txt');

        File::put($imageOne, 'img');
        File::put($imageTwo, 'img');
        File::put($labelOne, "0 0.5 0.5 0.3 0.3\n");
        File::put($labelTwo, "0 0.4 0.4 0.2 0.2\n");

        $manifestPath = $datasetDir.'/manifest.csv';
        File::put(
            $manifestPath,
            implode(PHP_EOL, [
                'source_video,source_key,frame_path,split,labels_path,needs_labeling,notes',
                $rawVideoPath.',candidate_1_clip_'.$clip->id.'_football_candidate,'.$imageOne.',train,'.$labelOne.',yes,',
                $rawVideoPath.',candidate_1_clip_'.$clip->id.'_football_candidate,'.$imageTwo.',train,'.$labelTwo.',yes,',
            ]).PHP_EOL
        );

        $result = app(AiDatasetExportService::class)->syncLabeledCandidates('football');

        $candidate->refresh();
        $this->assertSame(1, $result['updated']);
        $this->assertSame(AiDatasetCandidate::STATUS_LABELED, $candidate->status);
        $this->assertNotNull($candidate->labeled_at);
    }
}
