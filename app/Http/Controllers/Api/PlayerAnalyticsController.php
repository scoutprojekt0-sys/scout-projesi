<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerCareerTimeline;
use App\Models\PlayerMarketValue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PlayerAnalyticsController extends Controller
{
    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_ids' => ['required', 'array', 'min:2', 'max:5'],
            'player_ids.*' => [Rule::exists('users', 'id')->where('role', 'player')],
        ]);

        $players = User::query()
            ->whereIn('id', $validated['player_ids'])
            ->get(['id', 'name', 'position', 'age', 'city']);

        $result = $players->map(function (User $player): array {
            $latestValue = PlayerMarketValue::query()
                ->where('player_id', $player->id)
                ->where('verification_status', 'verified')
                ->orderByDesc('valuation_date')
                ->first();

            $career = PlayerCareerTimeline::query()
                ->where('player_id', $player->id)
                ->where('verification_status', 'verified')
                ->get(['appearances', 'goals', 'assists', 'minutes_played']);

            $appearances = (int) $career->sum('appearances');
            $goals = (int) $career->sum('goals');
            $assists = (int) $career->sum('assists');
            $minutes = (int) $career->sum('minutes_played');

            return [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'position' => $player->position,
                'age' => $player->age,
                'city' => $player->city,
                'market_value' => $latestValue?->value,
                'currency' => $latestValue?->currency ?? 'EUR',
                'value_trend' => $latestValue?->value_trend,
                'valuation_date' => $latestValue?->valuation_date?->toDateString(),
                'stats' => [
                    'appearances' => $appearances,
                    'goals' => $goals,
                    'assists' => $assists,
                    'minutes_played' => $minutes,
                    'goal_contribution' => $goals + $assists,
                    'goal_contribution_per_game' => $appearances > 0
                        ? round(($goals + $assists) / $appearances, 2)
                        : 0,
                ],
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'players' => $result,
                'best_market_value' => $result->filter(fn ($p) => $p['market_value'] !== null)->sortByDesc('market_value')->first(),
                'best_goal_contribution' => $result->sortByDesc('stats.goal_contribution')->first(),
            ],
        ]);
    }

    public function trendSummary(int $playerId): JsonResponse
    {
        $player = User::query()
            ->where('id', $playerId)
            ->where('role', 'player')
            ->firstOrFail();

        $valueSeries = PlayerMarketValue::query()
            ->where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->orderBy('valuation_date')
            ->get(['valuation_date', 'value', 'value_change_percent']);

        $formSeries = PlayerCareerTimeline::query()
            ->where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->orderBy('start_date')
            ->get(['season_start', 'appearances', 'goals', 'assists', 'minutes_played']);

        $latestValue = $valueSeries->last();
        $firstValue = $valueSeries->first();

        $growthPercent = 0.0;
        if ($latestValue && $firstValue && (float) $firstValue->value > 0) {
            $growthPercent = round((((float) $latestValue->value - (float) $firstValue->value) / (float) $firstValue->value) * 100, 2);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'position' => $player->position,
                    'age' => $player->age,
                ],
                'value_series' => $valueSeries,
                'form_series' => $formSeries,
                'summary' => [
                    'latest_value' => $latestValue?->value,
                    'currency' => $latestValue?->currency ?? 'EUR',
                    'overall_growth_percent' => $growthPercent,
                    'series_points' => $valueSeries->count(),
                ],
            ],
        ]);
    }

    public function similar(int $playerId, Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 10), 30));

        $target = DB::table('users')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.id', $playerId)
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.city',
                'users.age',
                'player_profiles.position',
                'player_profiles.current_team',
            ])
            ->first();

        if (! $target) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $targetValue = PlayerMarketValue::query()
            ->where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->orderByDesc('valuation_date')
            ->value('value');

        $similarPlayers = DB::table('users')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->where('users.id', '!=', $playerId)
            ->when($target->position, fn ($query) => $query->where('player_profiles.position', $target->position))
            ->when($target->age, fn ($query) => $query->whereBetween('users.age', [max(10, (int) $target->age - 3), (int) $target->age + 3]))
            ->select([
                'users.id',
                'users.name',
                'users.city',
                'users.age',
                'player_profiles.position',
                'player_profiles.current_team',
            ])
            ->limit($limit * 3)
            ->get()
            ->map(function ($player) use ($targetValue) {
                $marketValue = PlayerMarketValue::query()
                    ->where('player_id', $player->id)
                    ->where('verification_status', 'verified')
                    ->orderByDesc('valuation_date')
                    ->first(['value', 'currency', 'valuation_date']);

                $career = PlayerCareerTimeline::query()
                    ->where('player_id', $player->id)
                    ->where('verification_status', 'verified')
                    ->get(['appearances', 'goals', 'assists']);

                $score = 0;
                if ($targetValue !== null && $marketValue?->value !== null && (float) $targetValue > 0) {
                    $differenceRatio = abs((float) $marketValue->value - (float) $targetValue) / (float) $targetValue;
                    $score += max(0, 40 - min(40, (int) round($differenceRatio * 100)));
                }

                return [
                    'player_id' => (int) $player->id,
                    'player_name' => (string) $player->name,
                    'position' => $player->position,
                    'age' => $player->age,
                    'city' => $player->city,
                    'current_team' => $player->current_team,
                    'market_value' => $marketValue?->value,
                    'currency' => $marketValue?->currency ?? 'EUR',
                    'valuation_date' => $marketValue?->valuation_date?->toDateString(),
                    'stats' => [
                        'appearances' => (int) $career->sum('appearances'),
                        'goals' => (int) $career->sum('goals'),
                        'assists' => (int) $career->sum('assists'),
                    ],
                    'similarity_score' => $score + 60,
                ];
            })
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'target_player' => [
                    'player_id' => (int) $target->id,
                    'player_name' => (string) $target->name,
                    'position' => $target->position,
                    'age' => $target->age,
                    'city' => $target->city,
                    'current_team' => $target->current_team,
                    'market_value' => $targetValue,
                ],
                'similar_players' => $similarPlayers,
            ],
        ]);
    }
}
