<?php

namespace App\Services;

use App\Models\AiDatasetCandidate;
use App\Models\VideoClip;

class AiDatasetCandidateService
{
    public function syncFromVideoClip(VideoClip $clip): ?AiDatasetCandidate
    {
        $metadata = is_array($clip->metadata) ? $clip->metadata : [];
        $isCandidate = (bool) ($metadata['ai_dataset_candidate'] ?? false);
        if (! $isCandidate) {
            return null;
        }

        $sport = $this->resolveSport($clip);

        return AiDatasetCandidate::query()->updateOrCreate(
            ['video_clip_id' => $clip->id],
            [
                'user_id' => $clip->user_id,
                'sport' => $sport,
                'status' => AiDatasetCandidate::STATUS_QUEUED,
                'source_type' => 'user_upload',
                'metadata' => [
                    'title' => $clip->title,
                    'platform' => $clip->platform,
                    'match_date' => optional($clip->match_date)?->toDateString(),
                    'tags' => is_array($clip->tags) ? array_values($clip->tags) : [],
                    'video_url' => $clip->video_url,
                    'thumbnail_url' => $clip->thumbnail_url,
                ],
                'queued_at' => now(),
            ]
        );
    }

    public function resolveSport(VideoClip $clip): string
    {
        $metadata = is_array($clip->metadata) ? $clip->metadata : [];
        $sport = $this->normalizeSport($metadata['sport'] ?? null);
        if ($sport !== null) {
            return $sport;
        }

        foreach ((array) ($clip->tags ?? []) as $tag) {
            $sport = $this->normalizeSport($tag);
            if ($sport !== null) {
                return $sport;
            }
        }

        return 'football';
    }

    private function normalizeSport(mixed $sport): ?string
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
            default => null,
        };
    }
}
