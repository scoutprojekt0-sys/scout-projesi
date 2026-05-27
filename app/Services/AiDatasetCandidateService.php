<?php

namespace App\Services;

use App\Models\AiDatasetCandidate;
use App\Models\User;
use App\Models\VideoClip;

class AiDatasetCandidateService
{
    public function syncFromVideoClip(VideoClip $clip): ?AiDatasetCandidate
    {
        $metadata = is_array($clip->metadata) ? $clip->metadata : [];
        $sport = $this->resolveSport($clip);
        if ($sport === null) {
            return null;
        }

        $isExplicitCandidate = array_key_exists('ai_dataset_candidate_requested', $metadata)
            ? (bool) $metadata['ai_dataset_candidate_requested']
            : null;
        $isAutoCandidate = $this->shouldAutoQueue($clip, $sport);
        $isCandidate = $isExplicitCandidate ?? $isAutoCandidate;
        if (! $isCandidate) {
            return null;
        }

        $qualityProfile = $this->buildQualityProfile($clip);
        $learningMetadata = [
            'sport' => $sport,
            'auto_learning_enabled' => true,
            'candidate_origin' => $isExplicitCandidate === true ? 'manual_opt_in' : 'auto_ingest',
            'candidate_reason' => $isExplicitCandidate === true
                ? 'upload_marked_for_training'
                : 'upload_matches_supported_sport_profile',
            'quality_profile' => $qualityProfile,
        ];

        return AiDatasetCandidate::query()->updateOrCreate(
            ['video_clip_id' => $clip->id],
            [
                'user_id' => $clip->user_id,
                'sport' => $sport,
                'status' => AiDatasetCandidate::STATUS_QUEUED,
                'source_type' => 'user_upload',
                'metadata' => [
                    ...array_filter($metadata, static fn ($value, $key) => ! in_array($key, ['ai_dataset_candidate', 'ai_dataset_candidate_requested'], true), ARRAY_FILTER_USE_BOTH),
                    'title' => $clip->title,
                    'platform' => $clip->platform,
                    'match_date' => optional($clip->match_date)?->toDateString(),
                    'tags' => is_array($clip->tags) ? array_values($clip->tags) : [],
                    'video_url' => $clip->video_url,
                    'thumbnail_url' => $clip->thumbnail_url,
                    ...$learningMetadata,
                ],
                'queued_at' => now(),
            ]
        );
    }

    public function resolveSport(VideoClip $clip): ?string
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

        $userSport = $this->normalizeSport($clip->player?->sport);
        if ($userSport !== null) {
            return $userSport;
        }

        return null;
    }

    private function shouldAutoQueue(VideoClip $clip, string $sport): bool
    {
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            return false;
        }

        if (($clip->duration_seconds ?? 0) > 0 && (int) $clip->duration_seconds < 10) {
            return false;
        }

        return in_array((string) $clip->player?->role, [
            'player',
            'scout',
            'coach',
            'team',
            'club',
            'manager',
        ], true);
    }

    private function buildQualityProfile(VideoClip $clip): array
    {
        $durationSeconds = (int) ($clip->duration_seconds ?? 0);
        $tags = is_array($clip->tags) ? $clip->tags : [];
        $flags = [];

        if ($durationSeconds > 0 && $durationSeconds < 30) {
            $flags[] = 'short_clip';
        }
        if (count($tags) < 1) {
            $flags[] = 'missing_tags';
        }
        if (blank($clip->thumbnail_url)) {
            $flags[] = 'missing_thumbnail';
        }

        return [
            'duration_seconds' => $durationSeconds > 0 ? $durationSeconds : null,
            'has_thumbnail' => ! blank($clip->thumbnail_url),
            'tag_count' => count($tags),
            'flags' => $flags,
        ];
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
