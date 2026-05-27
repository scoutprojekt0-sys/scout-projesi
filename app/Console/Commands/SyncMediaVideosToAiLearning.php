<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\User;
use App\Models\VideoClip;
use App\Services\AiContinuousLearningService;
use App\Services\AiDatasetCandidateService;
use Illuminate\Console\Command;

class SyncMediaVideosToAiLearning extends Command
{
    protected $signature = 'media-videos:sync-ai-learning
        {--email= : Only sync videos for this user email}
        {--user-id= : Only sync videos for this user id}
        {--limit=100 : Maximum media videos to inspect}
        {--dry-run : Show what would be synced without writing}';

    protected $description = 'Backfill media table video uploads into VideoClip and AI continuous learning.';

    public function handle(
        AiDatasetCandidateService $datasetCandidateService,
        AiContinuousLearningService $continuousLearningService
    ): int {
        $user = $this->resolveUser();
        if ($user === false) {
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $query = Media::query()
            ->with('user')
            ->where('type', 'video')
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($user instanceof User) {
            $query->where('user_id', $user->id);
        }

        $synced = 0;
        $skipped = 0;

        foreach ($query->get() as $media) {
            $owner = $media->user;
            $sport = $this->normalizeSport($owner?->sport);

            if (! $owner || $owner->role !== 'player' || $sport === null) {
                $skipped++;
                $this->line("SKIP media={$media->id} reason=unsupported_user_or_sport");
                continue;
            }

            $alreadySynced = VideoClip::query()
                ->where('user_id', $owner->id)
                ->where('metadata->source', 'media_upload')
                ->where('metadata->media_id', (int) $media->id)
                ->exists();

            if ($alreadySynced) {
                $skipped++;
                $this->line("SKIP media={$media->id} reason=already_synced");
                continue;
            }

            if ($dryRun) {
                $synced++;
                $this->line("DRY media={$media->id} user={$owner->id} sport={$sport}");
                continue;
            }

            $clip = VideoClip::query()->create([
                'user_id' => (int) $owner->id,
                'title' => $media->title ?: 'Media video '.$media->id,
                'description' => null,
                'video_url' => $media->url,
                'thumbnail_url' => $media->thumb_url,
                'platform' => 'custom',
                'platform_video_id' => null,
                'duration_seconds' => null,
                'match_date' => null,
                'tags' => [$sport],
                'metadata' => [
                    'source' => 'media_upload',
                    'media_id' => (int) $media->id,
                    'sport' => $sport,
                    'ai_dataset_candidate' => true,
                    'ai_dataset_candidate_requested' => true,
                ],
            ]);

            $candidate = $datasetCandidateService->syncFromVideoClip($clip);
            if ($candidate !== null) {
                $continuousLearningService->onCandidateQueued($candidate);
                $continuousLearningService->onVideoUploaded($clip, $candidate);
            }

            $synced++;
            $this->line("SYNC media={$media->id} clip={$clip->id} user={$owner->id} sport={$sport}");
        }

        $this->info("Media video sync tamamlandi. synced={$synced} skipped={$skipped}");

        return self::SUCCESS;
    }

    private function resolveUser(): User|false|null
    {
        $email = trim((string) $this->option('email'));
        $userId = $this->option('user-id');

        if ($email !== '' && $userId !== null) {
            $this->error('email ve user-id ayni anda verilmemeli.');

            return false;
        }

        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $this->error('User bulunamadi: '.$email);

                return false;
            }

            return $user;
        }

        if ($userId !== null) {
            $user = User::query()->find((int) $userId);
            if (! $user) {
                $this->error('User bulunamadi: '.$userId);

                return false;
            }

            return $user;
        }

        return null;
    }

    private function normalizeSport(?string $sport): ?string
    {
        if (! is_string($sport)) {
            return null;
        }

        $normalized = strtolower(trim($sport));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'futbol', 'football', 'soccer' => 'football',
            'basketbol', 'basketball' => 'basketball',
            'voleybol', 'volleyball' => 'volleyball',
            default => $normalized,
        };
    }
}
