<?php

namespace Tests\Feature;

use App\Models\AiDatasetCandidate;
use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiDatasetExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_command_copies_approved_candidate_video_and_writes_manifest(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'player']);
        Storage::disk('public')->put('videos/'.$user->id.'/clip.mp4', 'fake-video-content');

        $clip = VideoClip::query()->create([
            'user_id' => $user->id,
            'title' => 'Training Match Clip',
            'video_url' => Storage::disk('public')->url('videos/'.$user->id.'/clip.mp4'),
            'platform' => 'custom',
            'metadata' => ['sport' => 'football', 'ai_dataset_candidate' => true],
        ]);

        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $user->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_APPROVED,
            'queued_at' => now(),
        ]);

        $rawDirectory = base_path('raw_videos/football');
        $manifestPath = base_path('raw_videos/manifests/video_candidates_football.csv');
        File::ensureDirectoryExists($rawDirectory);
        File::delete($manifestPath);

        Artisan::call('ai-dataset:export-candidates', ['sport' => 'football']);

        $candidate->refresh();
        $this->assertSame('train', $candidate->split);
        $this->assertNotEmpty($candidate->metadata['export']['raw_video_path'] ?? null);
        $this->assertFileExists($candidate->metadata['export']['raw_video_path']);
        $this->assertFileExists($manifestPath);

        $manifest = File::get($manifestPath);
        $this->assertStringContainsString((string) $candidate->id, $manifest);
        $this->assertStringContainsString((string) $clip->id, $manifest);
    }
}
