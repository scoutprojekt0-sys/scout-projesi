<?php

namespace App\Services;

use App\Models\PlayerTransfer;
use App\Models\ScoutReward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ScoutRewardService
{
    public function listForAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return ScoutReward::query()
            ->with(['user:id,name,email', 'scoutTip:id,player_name,status'])
            ->latest('id')
            ->paginate($perPage);
    }

    public function approve(ScoutReward $reward): ScoutReward
    {
        $reward->update(['status' => 'approved']);

        return $reward->fresh(['user', 'scoutTip']);
    }

    public function markPaid(ScoutReward $reward): ScoutReward
    {
        $reward->update(['status' => 'paid']);

        return $reward->fresh(['user', 'scoutTip']);
    }

    public function syncForTransfer(PlayerTransfer $transfer, bool $verified): void
    {
        ScoutReward::query()
            ->where('basis', 'verified_transfer')
            ->whereJsonContains('metadata->player_transfer_id', $transfer->id)
            ->get()
            ->each(function (ScoutReward $reward) use ($verified): void {
                $reward->update([
                    'status' => $verified ? 'approved' : 'cancelled',
                ]);
            });
    }
}
