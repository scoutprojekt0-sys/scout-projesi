<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Week7AnalyticsController extends Controller
{
    use ApiResponds;
    public function adminOverview(): JsonResponse
    {
        $usersByRole = DB::table('users')
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        $opportunityStats = DB::table('opportunities')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $applicationStats = DB::table('applications')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $transferStats = DB::table('player_transfers')
            ->where('verification_status', 'verified')
            ->where('transfer_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(fee), 0) as total_fee')
            ->first();

        return $this->successResponse([
                'users' => [
                    'total' => DB::table('users')->count(),
                    'by_role' => $usersByRole,
                ],
                'opportunities' => [
                    'total' => DB::table('opportunities')->count(),
                    'open' => (int) ($opportunityStats['open'] ?? 0),
                    'closed' => (int) ($opportunityStats['closed'] ?? 0),
                ],
                'applications' => [
                    'total' => DB::table('applications')->count(),
                    'pending' => (int) ($applicationStats['pending'] ?? 0),
                    'accepted' => (int) ($applicationStats['accepted'] ?? 0),
                    'rejected' => (int) ($applicationStats['rejected'] ?? 0),
                ],
                'transfer_activity_last_30_days' => [
                    'count' => (int) ($transferStats->total ?? 0),
                    'total_fee' => (float) ($transferStats->total_fee ?? 0),
                ],
                'generated_at' => now()->toIso8601String(),
            ], 'Admin ozet hazir.');
    }

    public function teamScoutingFunnel(int $teamId): JsonResponse
    {
        $team = DB::table('users')
            ->where('id', $teamId)
            ->where('role', 'team')
            ->first();

        if (! $team) {
            return $this->errorResponse('Takim bulunamadi.', 404, 'team_not_found');
        }

        $opportunityIds = DB::table('opportunities')
            ->where('team_user_id', $teamId)
            ->pluck('id');

        $opportunityCount = $opportunityIds->count();

        $applicationStats = DB::table('applications')
            ->whereIn('opportunity_id', $opportunityIds)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $avgApplicantRating = DB::table('applications')
            ->join('users', 'users.id', '=', 'applications.player_user_id')
            ->whereIn('applications.opportunity_id', $opportunityIds)
            ->avg('users.rating');

        $latestApplications = DB::table('applications')
            ->join('users', 'users.id', '=', 'applications.player_user_id')
            ->whereIn('applications.opportunity_id', $opportunityIds)
            ->orderByDesc('applications.created_at')
            ->limit(5)
            ->get([
                'applications.id',
                'applications.status',
                'applications.created_at',
                'users.id as player_id',
                'users.name as player_name',
                'users.position',
                'users.rating',
            ]);

        $transferSummary = DB::table('player_transfers')
            ->where(function ($q) use ($teamId) {
                $q->where('to_club_id', $teamId)
                    ->orWhere('from_club_id', $teamId);
            })
            ->where('verification_status', 'verified')
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(fee), 0) as total_fee')
            ->first();

        return $this->successResponse([
                'team_id' => $teamId,
                'team_name' => $team->name,
                'opportunities' => [
                    'total' => $opportunityCount,
                    'open' => DB::table('opportunities')->where('team_user_id', $teamId)->where('status', 'open')->count(),
                    'closed' => DB::table('opportunities')->where('team_user_id', $teamId)->where('status', 'closed')->count(),
                ],
                'application_funnel' => [
                    'pending' => (int) ($applicationStats['pending'] ?? 0),
                    'accepted' => (int) ($applicationStats['accepted'] ?? 0),
                    'rejected' => (int) ($applicationStats['rejected'] ?? 0),
                    'total' => (int) array_sum($applicationStats->toArray()),
                ],
                'avg_applicant_rating' => round((float) ($avgApplicantRating ?? 0), 2),
                'latest_applications' => $latestApplications,
                'verified_transfer_volume' => [
                    'count' => (int) ($transferSummary->total ?? 0),
                    'total_fee' => (float) ($transferSummary->total_fee ?? 0),
                ],
                'generated_at' => now()->toIso8601String(),
            ], 'Takim scouting funnel hazir.');
    }
}
