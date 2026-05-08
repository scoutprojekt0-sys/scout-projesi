<?php

namespace Tests\Feature;

use App\Models\AiDatasetCandidate;
use App\Models\User;
use App\Models\VideoClip;
use App\Services\AiTrainingRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTrainingRunServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_run_persists_candidate_links_and_completion(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::query()->create([
            'user_id' => $user->id,
            'title' => 'Candidate Clip',
            'video_url' => 'https://example.com/clip.mp4',
            'platform' => 'custom',
        ]);

        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $user->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_LABELED,
        ]);

        $service = app(AiTrainingRunService::class);
        $run = $service->createRun(
            'football',
            'football-v-test',
            'cpu',
            10,
            640,
            4,
            false,
            [$candidate->id]
        );

        $this->assertSame('running', $run->status);
        $this->assertSame(1, $run->candidates()->count());

        $run = $service->markCompleted($run, 'training output');

        $this->assertSame('completed', $run->status);
        $this->assertNotNull($run->completed_at);
        $this->assertStringContainsString('training output', (string) $run->output_log);
    }
}
