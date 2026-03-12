<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerSearch;
use App\Models\PlayerSearchResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PlayerSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
            'min_age' => ['nullable', 'integer', 'min:10', 'max:60'],
            'max_age' => ['nullable', 'integer', 'min:10', 'max:60'],
            'min_height_cm' => ['nullable', 'integer', 'min:120', 'max:230'],
            'max_height_cm' => ['nullable', 'integer', 'min:120', 'max:230'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'save_search' => ['nullable', 'boolean'],
        ]);

        $search = PlayerSearch::query()->create([
            'manager_id' => (int) $request->user()->id,
            'position' => $validated['position'] ?? null,
            'city' => $validated['city'] ?? null,
            'min_age' => $validated['min_age'] ?? null,
            'max_age' => $validated['max_age'] ?? null,
            'min_height_cm' => $validated['min_height_cm'] ?? null,
            'max_height_cm' => $validated['max_height_cm'] ?? null,
            'min_rating' => $validated['min_rating'] ?? null,
            'save_search' => (bool) ($validated['save_search'] ?? false),
        ]);

        $currentYear = (int) now()->format('Y');

        $players = DB::table('users')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->when(! empty($validated['position']), fn ($query) => $query->where('player_profiles.position', 'like', '%'.$validated['position'].'%'))
            ->when(! empty($validated['city']), fn ($query) => $query->where('users.city', 'like', '%'.$validated['city'].'%'))
            ->when(! empty($validated['min_age']), function ($query) use ($validated, $currentYear) {
                $query->where('player_profiles.birth_year', '<=', $currentYear - (int) $validated['min_age']);
            })
            ->when(! empty($validated['max_age']), function ($query) use ($validated, $currentYear) {
                $query->where('player_profiles.birth_year', '>=', $currentYear - (int) $validated['max_age']);
            })
            ->when(! empty($validated['min_height_cm']), fn ($query) => $query->where('player_profiles.height_cm', '>=', (int) $validated['min_height_cm']))
            ->when(! empty($validated['max_height_cm']), fn ($query) => $query->where('player_profiles.height_cm', '<=', (int) $validated['max_height_cm']))
            ->when(! empty($validated['min_rating']), fn ($query) => $query->where('users.rating', '>=', (float) $validated['min_rating']))
            ->select([
                'users.id',
                'users.name',
                'users.city',
                'users.rating',
                'player_profiles.birth_year',
                'player_profiles.position',
                'player_profiles.height_cm',
                'player_profiles.current_team',
            ])
            ->get();

        foreach ($players as $player) {
            PlayerSearchResult::query()->create([
                'search_id' => $search->id,
                'player_id' => $player->id,
                'match_score' => $this->calculateMatchScore($search, $player, $currentYear),
                'match_details' => $this->matchDetails($search, $player, $currentYear),
            ]);
        }

        $results = PlayerSearchResult::query()
            ->where('search_id', $search->id)
            ->with('player:id,name,email,role,city,position,age,rating')
            ->orderByDesc('match_score')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'search_id' => $search->id,
            'total_results' => $results->total(),
            'data' => $results,
        ]);
    }

    public function getSavedSearches(Request $request): JsonResponse
    {
        $searches = PlayerSearch::query()
            ->where('manager_id', $request->user()->id)
            ->where('save_search', true)
            ->withCount('results')
            ->latest('id')
            ->paginate(10);

        return response()->json([
            'ok' => true,
            'data' => $searches,
        ]);
    }

    public function getSearchResults(int $searchId, Request $request): JsonResponse
    {
        $search = PlayerSearch::query()->findOrFail($searchId);

        if ((int) $search->manager_id !== (int) $request->user()->id) {
            return response()->json([
                'ok' => false,
                'message' => 'Yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        $results = PlayerSearchResult::query()
            ->where('search_id', $searchId)
            ->with('player:id,name,email,role,city,position,age,rating')
            ->orderByDesc('match_score')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $results,
        ]);
    }

    private function calculateMatchScore(PlayerSearch $search, object $player, int $currentYear): float
    {
        $score = 0;

        if ($search->position && strcasecmp((string) $player->position, (string) $search->position) === 0) {
            $score += 30;
        }

        if ($search->city && strcasecmp((string) $player->city, (string) $search->city) === 0) {
            $score += 20;
        }

        $age = $player->birth_year ? $currentYear - (int) $player->birth_year : null;
        if ($age !== null && $search->min_age && $search->max_age && $age >= $search->min_age && $age <= $search->max_age) {
            $score += 20;
        }

        if ($search->min_height_cm && $player->height_cm !== null && (int) $player->height_cm >= (int) $search->min_height_cm) {
            $score += 10;
        }

        if ($search->max_height_cm && $player->height_cm !== null && (int) $player->height_cm <= (int) $search->max_height_cm) {
            $score += 10;
        }

        if ($search->min_rating !== null && $player->rating !== null && (float) $player->rating >= (float) $search->min_rating) {
            $score += 10;
        }

        return min(100, $score);
    }

    private function matchDetails(PlayerSearch $search, object $player, int $currentYear): array
    {
        $details = [];

        if ($search->position && strcasecmp((string) $player->position, (string) $search->position) === 0) {
            $details[] = 'Pozisyon uyumlu';
        }

        if ($search->city && strcasecmp((string) $player->city, (string) $search->city) === 0) {
            $details[] = 'Sehir uyumlu';
        }

        $age = $player->birth_year ? $currentYear - (int) $player->birth_year : null;
        if ($age !== null && $search->min_age && $search->max_age && $age >= $search->min_age && $age <= $search->max_age) {
            $details[] = 'Yas araliginda';
        }

        if ($search->min_rating !== null && $player->rating !== null && (float) $player->rating >= (float) $search->min_rating) {
            $details[] = 'Rating yeterli';
        }

        return $details;
    }
}
