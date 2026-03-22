<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\AmateurResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAmateurResultController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $rows = AmateurResult::query()
            ->latest('id')
            ->paginate((int) $request->input('per_page', 100));

        return $this->successResponse($rows, 'Amator sonuc listesi hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'league' => ['required', 'string', 'max:120'],
            'season' => ['required', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:80'],
            'sport' => ['nullable', 'string', 'max:40'],
            'home_team' => ['required', 'string', 'max:120'],
            'away_team' => ['required', 'string', 'max:120'],
            'home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'away_score' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $row = AmateurResult::query()->create([
            ...$validated,
            'country' => $validated['country'] ?? 'Turkiye',
            'sport' => $validated['sport'] ?? 'futbol',
            'status' => 'pending',
            'source' => 'admin',
        ]);

        return $this->successResponse($row, 'Amator sonuc eklendi.', Response::HTTP_CREATED);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,verified,rejected'],
        ]);

        $row = AmateurResult::query()->find($id);
        if (!$row) {
            return $this->errorResponse('Amator sonuc bulunamadi.', 404, 'amateur_result_not_found');
        }

        $row->update([
            'status' => $validated['status'],
            'reviewed_at' => now(),
        ]);

        return $this->successResponse($row->fresh(), 'Amator sonuc durumu guncellendi.');
    }

    public function standings(): JsonResponse
    {
        $rows = AmateurResult::query()
            ->where('status', 'verified')
            ->orderByDesc('reviewed_at')
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $key = implode('__', [
                $row->country ?: 'Turkiye',
                $row->sport ?: 'futbol',
                $row->league ?: '-',
                $row->season ?: '-',
            ]);
            $buckets[$key] ??= [];
            foreach ([$row->home_team, $row->away_team] as $teamName) {
                $buckets[$key][$teamName] ??= [
                    'team' => $teamName,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'gf' => 0,
                    'ga' => 0,
                    'gd' => 0,
                    'points' => 0,
                ];
            }

            $home = &$buckets[$key][$row->home_team];
            $away = &$buckets[$key][$row->away_team];

            $home['played'] += 1;
            $away['played'] += 1;
            $home['gf'] += (int) $row->home_score;
            $home['ga'] += (int) $row->away_score;
            $away['gf'] += (int) $row->away_score;
            $away['ga'] += (int) $row->home_score;

            if ((int) $row->home_score > (int) $row->away_score) {
                $home['won'] += 1;
                $away['lost'] += 1;
                $home['points'] += 3;
            } elseif ((int) $row->home_score < (int) $row->away_score) {
                $away['won'] += 1;
                $home['lost'] += 1;
                $away['points'] += 3;
            } else {
                $home['drawn'] += 1;
                $away['drawn'] += 1;
                $home['points'] += 1;
                $away['points'] += 1;
            }
            unset($home, $away);
        }

        $out = collect($buckets)->map(function (array $teams, string $group) {
            $standings = collect($teams)
                ->map(function (array $team) {
                    $team['gd'] = $team['gf'] - $team['ga'];
                    return $team;
                })
                ->sortBy([
                    ['points', 'desc'],
                    ['gd', 'desc'],
                    ['gf', 'desc'],
                    ['team', 'asc'],
                ])
                ->values();

            return [
                'group' => $group,
                'standings' => $standings,
                'updated_at' => now()->toIso8601String(),
            ];
        })->values();

        return $this->successResponse($out, 'Amator puan durumlari hazir.');
    }
}
