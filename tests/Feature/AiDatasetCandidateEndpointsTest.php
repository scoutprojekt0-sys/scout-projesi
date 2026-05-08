<?php

namespace Tests\Feature;

use App\Models\AiDatasetCandidate;
use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiDatasetCandidateEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_store_creates_dataset_candidate_when_flagged(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:write', 'profile:read']);

        $response = $this->postJson('/api/videos', [
            'title' => 'Training Match Clip',
            'video_url' => 'https://example.com/video.mp4',
            'platform' => 'custom',
            'sport' => 'football',
            'ai_dataset_candidate' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('ok', true);

        $clipId = (int) $response->json('data.id');
        $this->assertDatabaseHas('ai_dataset_candidates', [
            'video_clip_id' => $clipId,
            'user_id' => $user->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_QUEUED,
        ]);
    }

    public function test_owner_can_list_only_own_dataset_candidates(): void
    {
        $owner = User::factory()->create(['role' => 'player']);
        $other = User::factory()->create(['role' => 'player']);

        $ownerClip = VideoClip::query()->create([
            'user_id' => $owner->id,
            'title' => 'Owner Clip',
            'video_url' => 'https://example.com/owner.mp4',
            'platform' => 'custom',
            'metadata' => ['sport' => 'football', 'ai_dataset_candidate' => true],
        ]);
        $otherClip = VideoClip::query()->create([
            'user_id' => $other->id,
            'title' => 'Other Clip',
            'video_url' => 'https://example.com/other.mp4',
            'platform' => 'custom',
            'metadata' => ['sport' => 'basketball', 'ai_dataset_candidate' => true],
        ]);

        AiDatasetCandidate::query()->create([
            'video_clip_id' => $ownerClip->id,
            'user_id' => $owner->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
        AiDatasetCandidate::query()->create([
            'video_clip_id' => $otherClip->id,
            'user_id' => $other->id,
            'sport' => 'basketball',
            'status' => AiDatasetCandidate::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        Sanctum::actingAs($owner, ['profile:read']);

        $this->getJson('/api/ai-dataset-candidates')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.video_clip.title', 'Owner Clip');
    }

    public function test_staff_can_update_dataset_candidate_status(): void
    {
        $owner = User::factory()->create(['role' => 'player']);
        $staff = User::factory()->create(['role' => 'scout']);

        $clip = VideoClip::query()->create([
            'user_id' => $owner->id,
            'title' => 'Candidate Clip',
            'video_url' => 'https://example.com/candidate.mp4',
            'platform' => 'custom',
            'metadata' => ['sport' => 'football', 'ai_dataset_candidate' => true],
        ]);

        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $owner->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        Sanctum::actingAs($staff, ['staff']);

        $this->postJson('/api/ai-dataset-candidates/'.$candidate->id.'/status', [
            'status' => AiDatasetCandidate::STATUS_APPROVED,
            'split' => 'train',
            'notes' => 'Ready for football retraining batch.',
            'model_version' => 'football-v2',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', AiDatasetCandidate::STATUS_APPROVED)
            ->assertJsonPath('data.split', 'train');

        $this->assertDatabaseHas('ai_dataset_candidates', [
            'id' => $candidate->id,
            'status' => AiDatasetCandidate::STATUS_APPROVED,
            'split' => 'train',
            'reviewed_by' => $staff->id,
            'model_version' => 'football-v2',
        ]);
    }
}
