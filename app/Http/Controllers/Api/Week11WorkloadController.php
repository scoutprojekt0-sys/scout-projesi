<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ModerationQueue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Week11WorkloadController extends Controller
{
    use ApiResponds;
    public function reviewerWorkload(): JsonResponse
    {
        $reviewers = User::query()
            ->where('role', 'manager')
            ->orWhere('editor_role', 'in', ['reviewer', 'senior_reviewer'])
            ->with(['reviews' => function ($q) {
                $q->where('status', 'pending')->orWhere('status', 'flagged');
            }])
            ->get()
            ->map(fn ($user) => [
                'reviewer_id' => $user->id,
                'reviewer_name' => $user->name,
                'pending_items' => $user->reviews->count(),
                'avg_review_time_hours' => round((float) ($user->avg_review_time_hours ?? 0), 1),
                'reviews_this_week' => ModerationQueue::where('reviewed_by', $user->id)
                    ->where('reviewed_at', '>=', now()->subWeek())
                    ->count(),
            ]);

        return $this->successResponse([
            'reviewers' => $reviewers,
            'total_pending' => ModerationQueue::where('status', 'pending')->count(),
            'avg_pending_per_reviewer' => $reviewers->isEmpty() ? 0 : round($reviewers->sum('pending_items') / $reviewers->count(), 1),
        ], 'Reviewer is yuku hazir.');
    }

    public function slaDashboard(): JsonResponse
    {
        $slaTargetHours = 24;

        $breaches = ModerationQueue::where('status', 'pending')
            ->where('submitted_at', '<=', now()->subHours($slaTargetHours))
            ->count();

        $total = ModerationQueue::where('status', 'pending')->count();
        $breachRate = $total > 0 ? round(($breaches / $total) * 100, 1) : 0.0;

        $avgReviewTime = ModerationQueue::whereNotNull('reviewed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (reviewed_at - submitted_at))/3600) as avg_hours')
            ->value('avg_hours');

        return $this->successResponse([
            'sla_target_hours' => $slaTargetHours,
            'pending_total' => $total,
            'sla_breached' => $breaches,
            'breach_rate_percent' => $breachRate,
            'avg_review_time_hours' => round((float) ($avgReviewTime ?? 0), 1),
            'sla_status' => $breachRate <= 10 ? 'HEALTHY' : ($breachRate <= 25 ? 'WARNING' : 'CRITICAL'),
        ], 'SLA dashboard hazir.');
    }
}
