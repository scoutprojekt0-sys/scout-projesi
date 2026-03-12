<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataAuditLog;
use App\Models\PlayerMarketValue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlayerMarketValueController extends Controller
{
    public function leaderboard(Request $request): JsonResponse
    {
        $limit = (int) min(max((int) $request->query('limit', 20), 1), 100);

        $rows = PlayerMarketValue::query()
            ->join('users', 'users.id', '=', 'player_market_values.player_id')
            ->where('users.role', 'player')
            ->where('player_market_values.verification_status', 'verified')
            ->whereRaw('player_market_values.valuation_date = (
                select max(pmv2.valuation_date)
                from player_market_values as pmv2
                where pmv2.player_id = player_market_values.player_id
                    and pmv2.verification_status = "verified"
            )')
            ->orderByDesc('player_market_values.value')
            ->limit($limit)
            ->get([
                'users.id as player_id',
                'users.name as player_name',
                'users.position',
                'users.age',
                'player_market_values.value',
                'player_market_values.currency',
                'player_market_values.valuation_date',
                'player_market_values.value_change_percent',
            ]);

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = PlayerMarketValue::query()
            ->with('player:id,name')
            ->where('verification_status', 'verified')
            ->orderBy('valuation_date', 'desc');

        if ($request->has('player_id')) {
            $query->where('player_id', $request->player_id);
        }

        if ($request->has('min_value')) {
            $query->where('value', '>=', $request->min_value);
        }

        if ($request->has('max_value')) {
            $query->where('value', '<=', $request->max_value);
        }

        $values = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'ok' => true,
            'data' => $values,
        ]);
    }

    public function history(int $playerId): JsonResponse
    {
        $history = PlayerMarketValue::where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->orderBy('valuation_date', 'asc')
            ->get();

        $latest = $history->last();
        $peak = $history->sortByDesc('value')->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'history' => $history,
                'latest' => $latest,
                'peak' => $peak,
                'total_valuations' => $history->count(),
            ],
        ]);
    }

    public function calculate(int $playerId): JsonResponse
    {
        $player = User::where('role', 'player')->findOrFail($playerId);

        $calculation = PlayerMarketValue::calculateValue($player);

        return response()->json([
            'ok' => true,
            'data' => [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'calculated_value' => $calculation['value'],
                'currency' => 'EUR',
                'factors' => $calculation['factors'],
                'explanation' => $calculation['explanation'],
                'model_version' => 'v1.0',
                'calculated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => ['required', Rule::exists('users', 'id')->where('role', 'player')],
            'value' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'valuation_date' => 'required|date',
            'explanation' => 'required|string|min:20|max:2000',
            'source_url' => 'required|url|max:500',
            'calculation_factors' => 'nullable|array',
        ]);

        // Get previous value
        $previous = PlayerMarketValue::where('player_id', $request->player_id)
            ->where('verification_status', 'verified')
            ->orderBy('valuation_date', 'desc')
            ->first();

        $valueChange = null;
        $valueChangePercent = null;

        if ($previous) {
            $valueChange = $request->value - $previous->value;
            $valueChangePercent = ($valueChange / $previous->value) * 100;
        }

        // Check for peak value
        $peak = PlayerMarketValue::where('player_id', $request->player_id)
            ->max('value');

        $marketValue = PlayerMarketValue::create(array_merge(
            $validated,
            [
                'currency' => $request->currency ?? 'EUR',
                'previous_value' => $previous?->value,
                'value_change' => $valueChange,
                'value_change_percent' => $valueChangePercent,
                'peak_value' => $request->value > $peak ? $request->value : $peak,
                'peak_value_date' => $request->value > $peak ? $request->valuation_date : null,
                'model_version' => 'v1.0',
                'verification_status' => 'pending',
                'confidence_score' => 0.7,
                'created_by' => auth()->id(),
            ]
        ));

        DataAuditLog::logChange(
            'PlayerMarketValue',
            $marketValue->id,
            'created',
            null,
            $marketValue->toArray(),
            auth()->id(),
            'New market valuation'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Market value created successfully',
            'data' => $marketValue->load('player'),
        ], 201);
    }

    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_ids' => 'required|array|min:2|max:5',
            'player_ids.*' => [Rule::exists('users', 'id')->where('role', 'player')],
        ]);

        $comparisons = [];

        foreach ($validated['player_ids'] as $playerId) {
            $player = User::find($playerId);
            $latestValue = PlayerMarketValue::where('player_id', $playerId)
                ->where('verification_status', 'verified')
                ->orderBy('valuation_date', 'desc')
                ->first();

            $comparisons[] = [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'position' => $player->position,
                'age' => $player->age,
                'current_value' => $latestValue?->value,
                'currency' => $latestValue?->currency ?? 'EUR',
                'value_trend' => $latestValue?->value_trend,
                'last_updated' => $latestValue?->valuation_date?->toDateString(),
            ];
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'players' => $comparisons,
                'highest_value' => collect($comparisons)->sortByDesc('current_value')->first(),
                'lowest_value' => collect($comparisons)->sortBy('current_value')->first(),
            ],
        ]);
    }

    public function trends(int $playerId): JsonResponse
    {
        $history = PlayerMarketValue::where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->orderBy('valuation_date')
            ->get(['valuation_date', 'value', 'value_change_percent']);

        $formTrend = DB::table('player_career_timeline')
            ->where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->orderBy('start_date')
            ->get(['season_start', 'appearances', 'goals', 'assists', 'minutes_played']);

        return response()->json([
            'ok' => true,
            'data' => [
                'value_trend' => $history,
                'form_trend' => $formTrend,
                'latest_value' => $history->last(),
                'series_points' => $history->count(),
            ],
        ]);
    }
}
