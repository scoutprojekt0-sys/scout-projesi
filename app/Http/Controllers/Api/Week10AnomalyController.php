<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ModerationQueue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Week10AnomalyController extends Controller
{
    use ApiResponds;

    public function scoreQueue(Request $request, int $queueId): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess($request)) {
            return $denied;
        }

        $item = ModerationQueue::findOrFail($queueId);

        $anomalyScore = $this->calculateAnomalyScore($item);
        $riskScore = $this->calculateRiskScore($item);
        $overallScore = ($anomalyScore + $riskScore) / 2;

        $item->update([
            'anomaly_score' => $anomalyScore,
            'risk_score' => $riskScore,
        ]);

        return $this->successResponse([
            'queue_id' => $item->id,
            'anomaly_score' => round($anomalyScore, 2),
            'risk_score' => round($riskScore, 2),
            'overall_score' => round($overallScore, 2),
            'recommendation' => $this->getRecommendation($overallScore),
        ], 'Anomali skoru hesaplandi.');
    }

    public function highRiskQueue(Request $request): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess($request)) {
            return $denied;
        }

        $riskThreshold = 0.70;

        $items = ModerationQueue::query()
            ->where('status', 'pending')
            ->whereRaw('(anomaly_score + risk_score) / 2 >= ?', [$riskThreshold])
            ->with(['submitter:id,name', 'reviewer:id,name'])
            ->orderByRaw('(anomaly_score + risk_score) / 2 DESC')
            ->limit(20)
            ->get([
                'id',
                'model_type',
                'model_id',
                'priority',
                'anomaly_score',
                'risk_score',
                'submitted_by',
                'submitted_at',
            ])
            ->map(fn ($item) => [
                'id' => $item->id,
                'model_type' => $item->model_type,
                'priority' => $item->priority,
                'anomaly_score' => round((float) $item->anomaly_score, 2),
                'risk_score' => round((float) $item->risk_score, 2),
                'overall_score' => round(((float) $item->anomaly_score + (float) $item->risk_score) / 2, 2),
                'submitter_name' => $item->submitter?->name,
                'submitted_at' => $item->submitted_at,
            ]);

        return $this->successResponse([
            'threshold' => $riskThreshold,
            'count' => $items->count(),
            'items' => $items,
        ], 'Yuksek riskli kuyruk hazir.');
    }

    private function calculateAnomalyScore(ModerationQueue $item): float
    {
        $score = 0.0;

        // Check if submitter is new/unverified
        $submitter = User::find($item->submitted_by);
        if ($submitter) {
            if ($submitter->verified_at === null) {
                $score += 0.25;
            }
            if ((float) ($submitter->trust_score ?? 0) < 0.50) {
                $score += 0.20;
            }
        }

        // Check if model type is high-volume (market value changes are common)
        if ($item->model_type === 'PlayerMarketValue') {
            $recent = ModerationQueue::query()
                ->where('model_type', 'PlayerMarketValue')
                ->where('submitted_by', $item->submitted_by)
                ->where('submitted_at', '>=', now()->subHours(24))
                ->count();

            if ($recent > 5) {
                $score += 0.15;
            }
        }

        // Check for conflicting data
        if ((bool) ($item->has_conflicts ?? false)) {
            $score += 0.30;
        }

        // Check confidence score of proposed change
        if ((float) ($item->confidence_score ?? 1.0) < 0.60) {
            $score += 0.15;
        }

        return min($score, 1.0);
    }

    private function calculateRiskScore(ModerationQueue $item): float
    {
        $score = 0.0;

        // Priority multiplier
        match ($item->priority) {
            'critical' => $score += 0.40,
            'high' => $score += 0.25,
            'medium' => $score += 0.15,
            default => $score += 0.05,
        };

        // Dual approval flag
        if ((bool) ($item->requires_dual_approval ?? false)) {
            $score += 0.15;
        }

        // Check if same submitter has been rejected before
        $rejectedCount = ModerationQueue::query()
            ->where('submitted_by', $item->submitted_by)
            ->where('status', 'rejected')
            ->count();

        if ($rejectedCount > 3) {
            $score += 0.20;
        }

        // Check age of queue item (older items might have stale context)
        if ($item->submitted_at && $item->submitted_at->diffInDays() > 7) {
            $score += 0.10;
        }

        return min($score, 1.0);
    }

    private function getRecommendation(float $overallScore): string
    {
        return match (true) {
            $overallScore >= 0.80 => 'ESCALATE_TO_ADMIN',
            $overallScore >= 0.60 => 'REQUIRE_SENIOR_REVIEW',
            $overallScore >= 0.40 => 'FLAG_FOR_ATTENTION',
            default => 'STANDARD_REVIEW',
        };
    }

    private function ensureModerationAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Bu islem icin giris yapmaniz gerekiyor.', 401, 'unauthenticated');
        }

        $editorRole = strtolower((string) ($user->editor_role ?? 'none'));
        $legacyStaffRole = in_array(strtolower((string) $user->role), ['manager', 'coach', 'scout'], true);

        if (in_array($editorRole, ['reviewer', 'senior_reviewer', 'admin'], true) || $legacyStaffRole) {
            return null;
        }

        return $this->errorResponse('Moderasyon yetkiniz yok.', 403, 'forbidden_moderation_only');
    }
}
