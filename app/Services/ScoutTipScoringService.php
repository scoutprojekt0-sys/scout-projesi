<?php

namespace App\Services;

use App\Models\ScoutTip;
use App\Models\User;

class ScoutTipScoringService
{
    public function calculateInitialScore(array $payload, ?User $submitter = null): float
    {
        $score = 20.0;

        if (! empty($payload['video_clip_id'])) {
            $score += 20;
        }

        if (! empty($payload['description']) && mb_strlen((string) $payload['description']) >= 80) {
            $score += 10;
        }

        if (! empty($payload['position'])) {
            $score += 10;
        }

        if (! empty($payload['team_name'])) {
            $score += 5;
        }

        if (! empty($payload['match_date'])) {
            $score += 10;
        }

        if (($payload['guardian_consent_status'] ?? 'pending') === 'received') {
            $score += 10;
        }

        if ($submitter) {
            $score += min(15, ((float) $submitter->trust_score) / 10);
        }

        return round(min(100, $score), 2);
    }

    public function applyReviewScore(ScoutTip $scoutTip, float $reviewScore): array
    {
        $reviewScore = max(0, min(100, $reviewScore));
        $finalScore = round((((float) $scoutTip->ai_quality_score) * 0.4) + ($reviewScore * 0.6), 2);

        return [
            'review_score' => $reviewScore,
            'final_score' => $finalScore,
        ];
    }
}
