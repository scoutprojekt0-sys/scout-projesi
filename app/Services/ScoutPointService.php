<?php

namespace App\Services;

use App\Models\ScoutPointLedger;
use App\Models\ScoutTip;
use App\Models\User;

class ScoutPointService
{
    public function award(User $user, ?ScoutTip $scoutTip, string $eventType, int $points, ?string $notes = null, array $metadata = []): ScoutPointLedger
    {
        $entry = ScoutPointLedger::create([
            'user_id' => $user->id,
            'scout_tip_id' => $scoutTip?->id,
            'event_type' => $eventType,
            'points' => $points,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);

        $user->increment('scout_points', $points);
        $this->refreshScoutProfile($user->fresh());

        return $entry;
    }

    public function refreshScoutProfile(User $user): void
    {
        $tips = (int) $user->scout_tips_count;
        $successes = (int) $user->successful_tips_count;
        $accuracy = $tips > 0 ? round(($successes / $tips) * 100, 2) : 0;
        $points = (int) $user->scout_points;

        $rank = match (true) {
            $points >= 2000 => 'legend',
            $points >= 1000 => 'elite',
            $points >= 400 => 'proven',
            $points >= 150 => 'trusted',
            default => 'rookie',
        };

        $user->forceFill([
            'scout_accuracy_rate' => $accuracy,
            'scout_rank' => $rank,
        ])->save();
    }
}
