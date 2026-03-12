<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class Week12PublicTransparencyController extends Controller
{
    use ApiResponds;

    public function platformTrustReport(): JsonResponse
    {
        $totalPlayers    = User::where('role', 'player')->count();
        $verifiedPlayers = User::where('role', 'player')->where('verification_status', 'verified')->count();
        $withSource      = User::where('role', 'player')->where('has_source', true)->count();
        $avgTrustScore   = User::where('role', 'player')->avg('trust_score') ?? 0.0;
        $topContributors = User::where('editor_role', '!=', 'none')
            ->select('id', 'name', 'contributions_count', 'approved_contributions', 'trust_score')
            ->orderByDesc('contributions_count')->limit(10)->get();

        return $this->successResponse([
            'report_date'              => now()->toDateString(),
            'total_players'            => $totalPlayers,
            'verified_players'         => $verifiedPlayers,
            'verification_rate_percent'=> $totalPlayers > 0 ? round(($verifiedPlayers / $totalPlayers) * 100, 1) : 0.0,
            'with_source_percent'      => $totalPlayers > 0 ? round(($withSource / $totalPlayers) * 100, 1) : 0.0,
            'avg_trust_score'          => round((float) $avgTrustScore, 2),
            'top_contributors'         => $topContributors,
        ], 'Platform guven raporu hazir.');
    }
}
