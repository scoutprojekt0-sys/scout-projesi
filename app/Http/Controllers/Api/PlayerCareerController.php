<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\PlayerCareerTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PlayerCareerController extends Controller
{
    public function timeline(int $playerId): JsonResponse
    {
        $timeline = PlayerCareerTimeline::where('player_id', $playerId)
            ->with('club:id,name')
            ->where('verification_status', 'verified')
            ->orderBy('start_date', 'desc')
            ->get();

        $current = $timeline->where('is_current', true)->first();
        $history = $timeline->where('is_current', false);

        return response()->json([
            'ok' => true,
            'data' => [
                'current' => $current,
                'history' => $history->values(),
                'total_clubs' => $timeline->unique('club_id')->count(),
                'career_goals' => $timeline->sum('goals'),
                'career_appearances' => $timeline->sum('appearances'),
                'career_assists' => $timeline->sum('assists'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_id' => ['required', Rule::exists('users', 'id')->where('role', 'player')],
            'club_id' => ['required', Rule::exists('users', 'id')->where('role', 'team')],
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'season_start' => 'required|string|max:10',
            'season_end' => 'nullable|string|max:10',
            'is_current' => 'nullable|boolean',
            'position' => 'nullable|string|max:50',
            'contract_type' => 'required|in:professional,youth,amateur,loan',
            'appearances' => 'nullable|integer|min:0',
            'goals' => 'nullable|integer|min:0',
            'assists' => 'nullable|integer|min:0',
            'minutes_played' => 'nullable|integer|min:0',
            'yellow_cards' => 'nullable|integer|min:0',
            'red_cards' => 'nullable|integer|min:0',
            'source_url' => 'required|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // If setting as current, unset other current entries for this player
        if ($request->is_current) {
            PlayerCareerTimeline::where('player_id', $request->player_id)
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }

        $career = PlayerCareerTimeline::create(array_merge(
            $validator->validated(),
            [
                'created_by' => auth()->id(),
                'verification_status' => 'pending',
                'confidence_score' => 0.7,
            ]
        ));

        // Add to moderation queue
        ModerationQueue::create([
            'model_type' => 'PlayerCareerTimeline',
            'model_id' => $career->id,
            'status' => 'pending',
            'priority' => 'medium',
            'reason' => 'new_entry',
            'proposed_changes' => $career->toArray(),
            'source_url' => $request->source_url,
            'confidence_score' => 0.7,
            'submitted_by' => auth()->id(),
        ]);

        DataAuditLog::logChange(
            'PlayerCareerTimeline',
            $career->id,
            'created',
            null,
            $career->toArray(),
            auth()->id(),
            'New career timeline entry'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Career entry created successfully. Awaiting verification.',
            'data' => $career->load('club'),
        ], 201);
    }

    public function statistics(int $playerId): JsonResponse
    {
        $timeline = PlayerCareerTimeline::where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->get();

        $player = \App\Models\User::query()->find($playerId);
        $rating = (float) ($player?->rating ?? 0);

        $byClub = $timeline->groupBy('club_id')->map(function ($entries) {
            return [
                'club_id' => $entries->first()->club_id,
                'club_name' => $entries->first()->club->name ?? 'Unknown',
                'total_appearances' => $entries->sum('appearances'),
                'total_goals' => $entries->sum('goals'),
                'total_assists' => $entries->sum('assists'),
                'total_minutes' => $entries->sum('minutes_played'),
                'seasons' => $entries->count(),
            ];
        })->values();

        $bySeason = $timeline->groupBy('season_start')->map(function ($entries, $season) {
            return [
                'season' => $season,
                'appearances' => $entries->sum('appearances'),
                'goals' => $entries->sum('goals'),
                'assists' => $entries->sum('assists'),
                'minutes_played' => $entries->sum('minutes_played'),
            ];
        });

        $careerTotals = [
            'appearances' => $timeline->sum('appearances'),
            'goals' => $timeline->sum('goals'),
            'assists' => $timeline->sum('assists'),
            'minutes_played' => $timeline->sum('minutes_played'),
            'yellow_cards' => $timeline->sum('yellow_cards'),
            'red_cards' => $timeline->sum('red_cards'),
        ];
        $achievementItems = $this->buildAchievementItems(
            $careerTotals,
            $timeline,
            $rating
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'by_club' => $byClub,
                'by_season' => $bySeason,
                'career_totals' => $careerTotals,
                'achievement_items' => $achievementItems,
            ],
        ]);
    }

    private function buildAchievementItems(array $totals, $timeline, float $rating): array
    {
        $items = [];
        $currentClub = optional($timeline->where('is_current', true)->first()?->club)->name ?? '-';

        if (($totals['goals'] ?? 0) > 0 || ($totals['assists'] ?? 0) > 0) {
            $items[] = [
                'category' => 'Bireysel',
                'icon' => 'BG',
                'title' => 'Gol Katkisi Lideri',
                'description' => sprintf(
                    'Toplam %d gol ve %d asist ile hucum katkinda one ciktin.',
                    (int) ($totals['goals'] ?? 0),
                    (int) ($totals['assists'] ?? 0)
                ),
                'meta' => 'Son donem performansi',
            ];
        }

        if (($totals['appearances'] ?? 0) >= 20) {
            $items[] = [
                'category' => 'Takim',
                'icon' => 'FI',
                'title' => 'Form Istikrari',
                'description' => sprintf(
                    '%d resmi macla duzenli forma giyerek takim ritmini korudun.',
                    (int) ($totals['appearances'] ?? 0)
                ),
                'meta' => 'Sezon geneli',
            ];
        }

        if ($rating >= 8.0) {
            $items[] = [
                'category' => 'Bireysel',
                'icon' => 'YP',
                'title' => 'Yuksek Performans Seviyesi',
                'description' => 'Genel oyuncu puanin '.number_format($rating, 1).' seviyesinde.',
                'meta' => 'Guncel oyuncu puani',
            ];
        }

        $clubCount = $timeline->pluck('club_id')->filter()->unique()->count();
        if ($clubCount > 0) {
            $items[] = [
                'category' => 'Kariyer',
                'icon' => 'KY',
                'title' => 'Kariyer Yolculugu',
                'description' => sprintf(
                    '%s dahil %d farkli kulup deneyimiyle kariyer cizgini genislettin.',
                    $currentClub,
                    $clubCount
                ),
                'meta' => sprintf(
                    '%d mac, %d gol',
                    (int) ($totals['appearances'] ?? 0),
                    (int) ($totals['goals'] ?? 0)
                ),
            ];
        }

        return $items;
    }
}
