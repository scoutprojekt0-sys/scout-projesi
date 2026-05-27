<?php

namespace Tests\Feature;

use App\Models\AiDatasetCandidate;
use App\Models\User;
use App\Models\VideoAnalysis;
use App\Models\VideoClip;
use App\Services\VideoAnalysisResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Jobs\MaybeAutoTrainSportJob;
use App\Jobs\PrepareAiDatasetForSportJob;
use App\Jobs\RunVideoAnalysisJob;

class AiDatasetCandidateEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_store_creates_dataset_candidate_when_flagged(): void
    {
        Queue::fake();

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
        Queue::assertPushed(PrepareAiDatasetForSportJob::class);
        Queue::assertPushed(MaybeAutoTrainSportJob::class);
        Queue::assertPushed(RunVideoAnalysisJob::class);
    }

    public function test_video_store_auto_enrolls_supported_player_uploads_for_continuous_learning(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'role' => 'player',
            'sport' => 'basketball',
        ]);
        Sanctum::actingAs($user, ['profile:write', 'profile:read']);

        $response = $this->postJson('/api/videos', [
            'title' => 'Player Development Clip',
            'video_url' => 'https://example.com/basketball-video.mp4',
            'platform' => 'custom',
        ]);

        $response->assertStatus(201)->assertJsonPath('ok', true);

        $clipId = (int) $response->json('data.id');
        $this->assertDatabaseHas('ai_dataset_candidates', [
            'video_clip_id' => $clipId,
            'user_id' => $user->id,
            'sport' => 'basketball',
            'status' => AiDatasetCandidate::STATUS_QUEUED,
        ]);

        $candidate = AiDatasetCandidate::query()->where('video_clip_id', $clipId)->firstOrFail();
        $this->assertTrue((bool) data_get($candidate->metadata, 'auto_learning_enabled'));
        $this->assertSame('auto_ingest', data_get($candidate->metadata, 'candidate_origin'));
        Queue::assertPushed(PrepareAiDatasetForSportJob::class);
        Queue::assertPushed(MaybeAutoTrainSportJob::class);
        Queue::assertPushed(RunVideoAnalysisJob::class);
    }

    public function test_media_video_upload_also_enrolls_supported_player_for_continuous_learning(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'player',
            'sport' => 'basketball',
        ]);
        Sanctum::actingAs($user, ['media:write', 'profile:read']);

        $response = $this->postJson('/api/media', [
            'title' => 'Media Workspace Clip',
            'file' => UploadedFile::fake()->create('workspace-clip.mp4', 2048, 'video/mp4'),
        ]);

        $response->assertStatus(201)->assertJsonPath('ok', true);

        $clip = VideoClip::query()->where('title', 'Media Workspace Clip')->firstOrFail();
        $this->assertSame($user->id, $clip->user_id);
        $this->assertSame('media_upload', data_get($clip->metadata, 'source'));
        $this->assertTrue((bool) data_get($clip->metadata, 'ai_dataset_candidate'));

        $this->assertDatabaseHas('ai_dataset_candidates', [
            'video_clip_id' => $clip->id,
            'user_id' => $user->id,
            'sport' => 'basketball',
            'status' => AiDatasetCandidate::STATUS_QUEUED,
        ]);
        Queue::assertPushed(PrepareAiDatasetForSportJob::class);
        Queue::assertPushed(MaybeAutoTrainSportJob::class);
        Queue::assertPushed(RunVideoAnalysisJob::class);
    }

    public function test_confident_background_analysis_auto_approves_candidate_and_triggers_training(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'role' => 'player',
            'sport' => 'football',
        ]);
        $clip = VideoClip::query()->create([
            'user_id' => $user->id,
            'title' => 'Confident Training Clip',
            'video_url' => 'https://example.com/confident.mp4',
            'platform' => 'custom',
            'duration_seconds' => 90,
            'tags' => ['football'],
            'metadata' => ['sport' => 'football', 'ai_dataset_candidate' => true],
        ]);
        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $user->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_LABELING,
            'queued_at' => now(),
            'metadata' => [
                'quality_profile' => [
                    'flags' => [],
                ],
            ],
        ]);
        $analysis = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $user->id,
            'target_player_id' => $user->id,
            'analysis_type' => 'continuous_learning',
            'provider' => 'external',
            'status' => 'processing',
            'worker_status' => 'processing',
            'analysis_version' => 'external-worker',
        ]);

        app(VideoAnalysisResultService::class)->complete($analysis, [
            'status' => 'completed',
            'analysis_version' => 'external-worker',
            'summary' => ['speed_score' => 82],
            'raw_output' => ['engine' => 'external-worker', 'sport' => 'football'],
            'events' => [
                ['event_type' => 'pass', 'start_second' => 4, 'end_second' => 6, 'confidence' => 0.82],
                ['event_type' => 'cross', 'start_second' => 18, 'end_second' => 20, 'confidence' => 0.78],
                ['event_type' => 'shot', 'start_second' => 44, 'end_second' => 46, 'confidence' => 0.74],
            ],
            'metrics' => [
                [
                    'player_id' => $user->id,
                    'successful_passes' => 8,
                    'successful_crosses' => 2,
                    'speed_score' => 82,
                    'movement_score' => 76,
                ],
            ],
        ]);

        $candidate->refresh();
        $this->assertSame(AiDatasetCandidate::STATUS_APPROVED, $candidate->status);
        $this->assertTrue((bool) data_get($candidate->metadata, 'continuous_learning.promotion_decision.promotion_eligible'));
        Queue::assertPushed(MaybeAutoTrainSportJob::class);
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
        Queue::fake();

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
        Queue::assertPushed(MaybeAutoTrainSportJob::class, function (MaybeAutoTrainSportJob $job): bool {
            return $job->sport === 'football';
        });
    }

    public function test_low_confidence_pseudo_label_requires_manual_override_before_approval(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => 'player']);
        $staff = User::factory()->create(['role' => 'scout']);

        $clip = VideoClip::query()->create([
            'user_id' => $owner->id,
            'title' => 'Pseudo Label Candidate',
            'video_url' => 'https://example.com/pseudo-label.mp4',
            'platform' => 'custom',
            'metadata' => ['sport' => 'football', 'ai_dataset_candidate' => true],
        ]);

        $candidate = AiDatasetCandidate::query()->create([
            'video_clip_id' => $clip->id,
            'user_id' => $owner->id,
            'sport' => 'football',
            'status' => AiDatasetCandidate::STATUS_QUEUED,
            'queued_at' => now(),
            'metadata' => [
                'pseudo_label_policy' => [
                    'confidence' => 0.42,
                    'threshold' => 0.65,
                    'promotion_eligible' => false,
                    'requires_manual_review' => true,
                ],
            ],
        ]);

        Sanctum::actingAs($staff, ['staff']);

        $this->postJson('/api/ai-dataset-candidates/'.$candidate->id.'/status', [
            'status' => AiDatasetCandidate::STATUS_APPROVED,
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->postJson('/api/ai-dataset-candidates/'.$candidate->id.'/status', [
            'status' => AiDatasetCandidate::STATUS_APPROVED,
            'manual_review_override' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', AiDatasetCandidate::STATUS_APPROVED);
    }
}
