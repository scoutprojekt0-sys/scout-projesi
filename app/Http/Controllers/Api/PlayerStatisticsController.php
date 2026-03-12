<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\PlayerStatistic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlayerStatisticsController extends Controller
{
    use ApiResponds;

    public function index(Request $request, int $playerId): JsonResponse
    {
        return $this->successResponse(
            PlayerStatistic::where('user_id', $playerId)->orderByDesc('season')->get(),
            'Oyuncu istatistikleri hazir.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'         => ['required', 'integer', 'exists:users,id'],
            'season'          => ['required', 'string', 'max:20'],
            'club_id'         => ['nullable', 'integer', 'exists:users,id'],
            'league'          => ['nullable', 'string', 'max:100'],
            'matches_played'  => ['required', 'integer', 'min:0'],
            'matches_started' => ['required', 'integer', 'min:0'],
            'matches_benched' => ['required', 'integer', 'min:0'],
            'goals'           => ['required', 'integer', 'min:0'],
            'assists'         => ['required', 'integer', 'min:0'],
            'yellow_cards'    => ['required', 'integer', 'min:0'],
            'red_cards'       => ['required', 'integer', 'min:0'],
            'minutes_played'  => ['required', 'integer', 'min:0'],
            'avg_rating'      => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        $stat = PlayerStatistic::updateOrCreate(
            ['user_id' => $validated['user_id'], 'season' => $validated['season'], 'club_id' => $validated['club_id'] ?? null],
            $validated
        );

        return $this->successResponse($stat, 'Istatistik kaydedildi.', Response::HTTP_CREATED);
    }

    public function topScorers(Request $request, string $season): JsonResponse
    {
        return $this->successResponse(
            PlayerStatistic::where('season', $season)->orderByDesc('goals')->with('player:id,name,email')->limit(50)->get(),
            'En cok gol atan oyuncular hazir.', 200,
            ['season' => $season]
        );
    }
}
