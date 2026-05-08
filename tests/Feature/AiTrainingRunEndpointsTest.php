<?php

namespace Tests\Feature;

use App\Models\AiDatasetCandidate;
use App\Models\AiTrainingRun;
use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiTrainingRunEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_training_runs(): void
    {
        $staff = User::factory()->create(['role' => 'scout']);

        AiTrainingRun::query()->create([
            'sport' => 'football',
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'model_version' => 'football-v1',
            'device' => 'cpu',
            'epochs' => 10,
            'imgsz' => 640,
            'batch' => 4,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($staff, ['staff']);

        $this->getJson('/api/ai-training-runs')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.model_version', 'football-v1');
    }

    public function test_staff_can_view_training_run_detail_with_candidates(): void
    {
        $staff = User::factory()->create(['role' => 'manager']);
        $player = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::query()->create([
            'user_id' => $player->id,
            'title' => 'Training Clip',
            'video_url' => 'https://example.com/training.mp4',
            'platform' => 'custom',
        ]);

        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $player->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_TRAINED,
            'split' => 'train',
            'model_version' => 'football-v2',
        ]);

        $run = AiTrainingRun::query()->create([
            'sport' => 'football',
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'model_version' => 'football-v2',
            'device' => 'cpu',
            'epochs' => 10,
            'imgsz' => 640,
            'batch' => 4,
            'candidate_count' => 1,
            'candidate_ids' => [$candidate->id],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
        $run->candidates()->attach($candidate->id);

        Sanctum::actingAs($staff, ['staff']);

        $this->getJson('/api/ai-training-runs/'.$run->id)
            ->assertOk()
            ->assertJsonPath('data.model_version', 'football-v2')
            ->assertJsonPath('data.candidates.0.id', $candidate->id)
            ->assertJsonPath('data.candidates.0.video_clip.title', 'Training Clip');
    }
}
