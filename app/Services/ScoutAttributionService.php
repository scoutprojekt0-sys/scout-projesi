<?php

namespace App\Services;

use App\Models\PlayerTransfer;
use App\Models\ScoutReward;
use App\Models\ScoutTip;

class ScoutAttributionService
{
    public function __construct(
        private readonly ScoutRewardService $rewardService,
    ) {
    }

    public function attachTransferRewardCandidate(PlayerTransfer $transfer): ?ScoutReward
    {
        $tip = ScoutTip::query()
            ->where('player_id', $transfer->player_id)
            ->whereIn('status', ['approved', 'trial', 'signed', 'rewarded'])
            ->orderByRaw("case when status = 'signed' then 1 when status = 'trial' then 2 else 3 end")
            ->orderByDesc('final_score')
            ->oldest('created_at')
            ->first();

        if (! $tip) {
            return null;
        }

        return ScoutReward::firstOrCreate(
            [
                'user_id' => $tip->submitted_by,
                'scout_tip_id' => $tip->id,
                'basis' => 'verified_transfer',
            ],
            [
                'reward_type' => 'commission_share',
                'status' => $transfer->verification_status === 'verified' ? 'approved' : 'pending',
                'amount' => $this->calculateTransferReward($transfer),
                'currency' => $transfer->currency,
                'metadata' => [
                    'player_transfer_id' => $transfer->id,
                    'transfer_status' => $transfer->verification_status,
                    'transfer_fee' => $transfer->fee,
                ],
            ]
        );
    }

    public function syncTransferVerification(PlayerTransfer $transfer, bool $verified): void
    {
        $this->rewardService->syncForTransfer($transfer, $verified);
    }

    private function calculateTransferReward(PlayerTransfer $transfer): float
    {
        if ($transfer->fee === null) {
            return 150.00;
        }

        return round(min(max(((float) $transfer->fee) * 0.005, 150), 2500), 2);
    }
}
