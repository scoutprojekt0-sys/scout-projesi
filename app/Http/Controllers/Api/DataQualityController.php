<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataQualityController extends Controller
{
    use ApiResponds;
    public function dashboard(Request $request): JsonResponse
    {
        $stats = [
            'players' => [
                'total' => User::where('role', 'player')->count(),
                'with_source' => User::where('role', 'player')->where('has_source', true)->count(),
                'verified' => User::where('role', 'player')->where('verification_status', 'verified')->count(),
                'pending' => User::where('role', 'player')->where('verification_status', 'pending')->count(),
                'with_conflicts' => User::where('role', 'player')->where('has_conflicts', true)->count(),
                'avg_confidence' => User::where('role', 'player')->avg('confidence_score'),
            ],
            'transfers' => [
                'total' => PlayerTransfer::count(),
                'verified' => PlayerTransfer::where('verification_status', 'verified')->count(),
                'pending' => PlayerTransfer::where('verification_status', 'pending')->count(),
                'with_source' => PlayerTransfer::whereNotNull('source_url')->count(),
                'avg_confidence' => PlayerTransfer::avg('confidence_score'),
            ],
            'moderation' => [
                'pending' => ModerationQueue::where('status', 'pending')->count(),
                'high_priority' => ModerationQueue::where('priority', 'high')
                    ->orWhere('priority', 'critical')
                    ->where('status', 'pending')
                    ->count(),
                'flagged' => ModerationQueue::where('status', 'flagged')->count(),
                'avg_resolution_hours' => ModerationQueue::where('status', 'approved')
                    ->whereNotNull('reviewed_at')
                    ->get()
                    ->avg(function ($item) {
                        return $item->submitted_at->diffInHours($item->reviewed_at);
                    }),
            ],
            'audit' => [
                'changes_today' => DataAuditLog::whereDate('created_at', today())->count(),
                'changes_week' => DataAuditLog::where('created_at', '>=', now()->subWeek())->count(),
                'unique_editors_today' => DataAuditLog::whereDate('created_at', today())
                    ->distinct('user_id')
                    ->count('user_id'),
            ],
        ];

        // Calculate quality metrics
        $sourcedPlayerRate = $stats['players']['total'] > 0
            ? round(($stats['players']['with_source'] / $stats['players']['total']) * 100, 2)
            : 0;

        $verifiedPlayerRate = $stats['players']['total'] > 0
            ? round(($stats['players']['verified'] / $stats['players']['total']) * 100, 2)
            : 0;

        $conflictRate = $stats['players']['total'] > 0
            ? round(($stats['players']['with_conflicts'] / $stats['players']['total']) * 100, 2)
            : 0;

        return $this->successResponse([
                'stats' => $stats,
                'quality_metrics' => [
                    'sourced_player_rate' => $sourcedPlayerRate,
                    'verified_player_rate' => $verifiedPlayerRate,
                    'conflict_rate' => $conflictRate,
                    'overall_confidence' => round($stats['players']['avg_confidence'] ?? 0, 2),
                ],
                'goals' => [
                    'sourced_rate_target' => 90,
                    'conflict_rate_max' => 3,
                    'resolution_time_hours' => 24,
                    'verification_rate_target' => 80,
                ],
                'generated_at' => now()->toIso8601String(),
            ], 'Veri kalitesi ozeti hazir.');
    }

    public function auditLog(Request $request): JsonResponse
    {
        $query = DataAuditLog::query()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->has('model_id')) {
            $query->where('model_id', $request->model_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('days')) {
            $query->where('created_at', '>=', now()->subDays($request->days));
        }

        return $this->paginatedListResponse($query->paginate($request->per_page ?? 50), 'Denetim kayitlari hazir.');
    }

    public function conflictingData(Request $request): JsonResponse
    {
        $conflicts = User::where('role', 'player')
            ->where('has_conflicts', true)
            ->select(['id', 'name', 'position', 'city', 'confidence_score', 'verification_status', 'updated_at'])
            ->paginate($request->per_page ?? 20);

        return $this->paginatedListResponse($conflicts, 'Catismalı veriler hazir.');
    }

    public function missingSource(Request $request): JsonResponse
    {
        $missing = User::where('role', 'player')
            ->where('has_source', false)
            ->where('verification_status', '!=', 'rejected')
            ->select(['id', 'name', 'position', 'city', 'verification_status', 'updated_at'])
            ->paginate($request->per_page ?? 20);

        return $this->paginatedListResponse($missing, 'Kaynak eksik oyuncular hazir.');
    }

    public function report(): JsonResponse
    {
        $playersTotal = User::where('role', 'player')->count();
        $playersWithSource = User::where('role', 'player')->where('has_source', true)->count();
        $playersVerified = User::where('role', 'player')->where('verification_status', 'verified')->count();
        $playersConflicts = User::where('role', 'player')->where('has_conflicts', true)->count();

        $transfersTotal = PlayerTransfer::count();
        $transfersVerified = PlayerTransfer::where('verification_status', 'verified')->count();
        $transfersPending = PlayerTransfer::where('verification_status', 'pending')->count();

        return $this->successResponse([
            'kpi' => [
                'source_coverage_percent' => $playersTotal > 0 ? round(($playersWithSource / $playersTotal) * 100, 2) : 0,
                'verification_percent' => $playersTotal > 0 ? round(($playersVerified / $playersTotal) * 100, 2) : 0,
                'conflict_percent' => $playersTotal > 0 ? round(($playersConflicts / $playersTotal) * 100, 2) : 0,
                'transfer_verification_percent' => $transfersTotal > 0 ? round(($transfersVerified / $transfersTotal) * 100, 2) : 0,
            ],
            'raw' => [
                'players_total' => $playersTotal,
                'players_with_source' => $playersWithSource,
                'players_verified' => $playersVerified,
                'players_conflicts' => $playersConflicts,
                'transfers_total' => $transfersTotal,
                'transfers_verified' => $transfersVerified,
                'transfers_pending' => $transfersPending,
                'moderation_pending' => ModerationQueue::where('status', 'pending')->count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ], 'Veri kalitesi raporu hazir.');
    }
}
