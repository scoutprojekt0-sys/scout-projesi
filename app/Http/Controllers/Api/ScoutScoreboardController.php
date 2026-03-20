<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ScoutPointLedger;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoutScoreboardController extends Controller
{
    use ApiResponds;

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        $payload = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'scout_points' => $user->scout_points,
                'scout_tips_count' => $user->scout_tips_count,
                'successful_tips_count' => $user->successful_tips_count,
                'scout_accuracy_rate' => $user->scout_accuracy_rate,
                'scout_rank' => $user->scout_rank,
            ],
            'recent_points' => ScoutPointLedger::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(20)
                ->get(),
        ];

        return $this->successResponse($payload, 'Scout puan tablonuz hazir.');
    }

    public function leaderboard(): JsonResponse
    {
        $leaders = User::query()
            ->select(['id', 'name', 'role', 'city', 'scout_points', 'successful_tips_count', 'scout_rank'])
            ->where('scout_points', '>', 0)
            ->orderByDesc('scout_points')
            ->orderByDesc('successful_tips_count')
            ->limit(50)
            ->get();

        return $this->successResponse($leaders, 'Scout liderlik tablosu hazir.');
    }
}
