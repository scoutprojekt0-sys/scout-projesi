<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ScoutReward;
use App\Models\ScoutTip;
use App\Services\ScoutRewardService;
use App\Services\ScoutTipWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminScoutController extends Controller
{
    use ApiResponds;

    public function __construct(
        private readonly ScoutTipWorkflowService $workflowService,
        private readonly ScoutRewardService $rewardService,
    ) {
    }

    public function queue(Request $request): JsonResponse
    {
        $query = ScoutTip::query()
            ->with(['submitter:id,name,email,scout_points,scout_rank', 'player:id,name', 'videoClip:id,title,video_url'])
            ->orderByRaw("case when status = 'pending' then 1 when status = 'screened' then 2 else 3 end")
            ->orderByDesc('final_score')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->whereIn('status', ['pending', 'screened', 'shortlisted']);
        }

        return $this->paginatedListResponse(
            $query->paginate((int) $request->input('per_page', 20)),
            'Admin scout queue hazir.'
        );
    }

    public function review(Request $request, int $tipId): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:screen,shortlist,approve,reject,mark-trial,mark-signed'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'review_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'player_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $status = match ($validated['action']) {
            'screen' => 'screened',
            'shortlist' => 'shortlisted',
            'approve' => 'approved',
            'reject' => 'rejected',
            'mark-trial' => 'trial',
            'mark-signed' => 'signed',
        };

        $tip = $this->workflowService->updateStatus(
            ScoutTip::findOrFail($tipId),
            $request->user(),
            $status,
            $validated
        );

        return $this->successResponse($tip, 'Scout review islemi tamamlandi.');
    }

    public function rewards(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->rewardService->listForAdmin((int) $request->input('per_page', 20)),
            'Scout odul listesi hazir.'
        );
    }

    public function approveReward(int $rewardId): JsonResponse
    {
        $reward = $this->rewardService->approve(ScoutReward::findOrFail($rewardId));

        return $this->successResponse($reward, 'Scout odulu onaylandi.');
    }

    public function markRewardPaid(int $rewardId): JsonResponse
    {
        $reward = $this->rewardService->markPaid(ScoutReward::findOrFail($rewardId));

        return $this->successResponse($reward, 'Scout odulu odendi olarak isaretlendi.');
    }
}
