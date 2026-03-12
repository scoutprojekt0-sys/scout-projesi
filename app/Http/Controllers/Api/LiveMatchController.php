<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveMatchController extends Controller
{
    use ApiResponds;

    public function liveMatches(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function recentResults(Request $request): JsonResponse
    {
        $matches = LiveMatch::query()
            ->where('is_finished', true)
            ->orderByDesc('match_date')
            ->limit(20)
            ->get()
            ->map(fn (LiveMatch $match) => [
                'id'          => $match->id,
                'league'      => $match->league,
                'home_team'   => $match->home_team,
                'away_team'   => $match->away_team,
                'home_score'  => $match->home_score,
                'away_score'  => $match->away_score,
                'status'      => 'finished',
                'finished_at' => $match->match_date?->toIso8601String(),
            ])->values();

        return $this->successResponse($matches, 'Son sonuclar hazir.', 200, ['total' => $matches->count()]);
    }

    public function upcomingMatches(Request $request): JsonResponse
    {
        $matches = LiveMatch::query()
            ->where('is_live', false)
            ->where('is_finished', false)
            ->orderBy('match_date')
            ->limit(20)
            ->get()
            ->map(fn (LiveMatch $match) => [
                'id'        => $match->id,
                'league'    => $match->league,
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'kickoff'   => $match->match_date?->toIso8601String(),
                'status'    => 'scheduled',
            ])->values();

        return $this->successResponse($matches, 'Yaklasan maclar hazir.', 200, ['total' => $matches->count()]);
    }

    public function matchDetails(Request $request, int $matchId): JsonResponse
    {
        return $this->show($request, $matchId);
    }

    public function matchScorers(Request $request, int $matchId): JsonResponse
    {
        return $this->successResponse([
            'match_id' => $matchId,
            'scorers'  => [
                ['player' => 'Icardi', 'team' => 'home', 'minute' => 15],
                ['player' => 'Dzeko',  'team' => 'away', 'minute' => 45],
                ['player' => 'Zaha',   'team' => 'home', 'minute' => 62],
            ],
        ], 'Gol atanlar hazir.');
    }

    public function updateLiveMatch(Request $request, int $matchId): JsonResponse
    {
        return $this->successResponse([
            'match_id' => $matchId,
            'payload'  => $request->all(),
        ], 'Canli mac guncellemesi alindi.');
    }

    public function getCount(Request $request): JsonResponse
    {
        $count = LiveMatch::query()->where('is_live', true)->where('is_finished', false)->count();

        return $this->successResponse([
            'count'            => $count,
            'has_live_matches' => $count > 0,
        ], 'Canli mac sayisi hazir.');
    }

    public function index(Request $request): JsonResponse
    {
        $matches = LiveMatch::query()
            ->where('is_live', true)
            ->where('is_finished', false)
            ->orderByDesc('match_date')
            ->limit(100)
            ->get()
            ->map(function (LiveMatch $match) {
                $meta = $this->decodeRoundMeta($match->round);
                return [
                    'id'           => $match->id,
                    'title'        => $match->title,
                    'league'       => $match->league,
                    'home_team'    => $match->home_team,
                    'away_team'    => $match->away_team,
                    'home_score'   => $match->home_score,
                    'away_score'   => $match->away_score,
                    'minute'       => null,
                    'status'       => 'live',
                    'match_date'   => $match->match_date?->toIso8601String(),
                    'location'     => $meta['location']    ?? null,
                    'sport'        => $meta['sport']       ?? null,
                    'focus'        => $meta['focus']       ?? null,
                    'stream_url'   => $meta['stream_url']  ?? null,
                    'stream_links' => is_array($meta['stream_links'] ?? null) ? $meta['stream_links'] : [],
                    'note'         => $meta['note']        ?? null,
                    'scout_name'   => $meta['scout_name']  ?? null,
                ];
            })->values();

        return $this->successResponse($matches, 'Canli maclar hazir.', 200, [
            'total'      => $matches->count(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'match_name'             => ['required', 'string', 'max:255'],
            'location'               => ['nullable', 'string', 'max:255'],
            'sport'                  => ['nullable', 'string', 'max:40'],
            'focus'                  => ['nullable', 'string', 'max:255'],
            'stream_url'             => ['nullable', 'url', 'max:500'],
            'stream_links'           => ['nullable', 'array'],
            'stream_links.youtube'   => ['nullable', 'url', 'max:500'],
            'stream_links.instagram' => ['nullable', 'url', 'max:500'],
            'stream_links.facebook'  => ['nullable', 'url', 'max:500'],
            'stream_links.x'         => ['nullable', 'url', 'max:500'],
            'note'                   => ['nullable', 'string', 'max:2000'],
            'league'                 => ['nullable', 'string', 'max:120'],
            'home_team'              => ['nullable', 'string', 'max:120'],
            'away_team'              => ['nullable', 'string', 'max:120'],
            'match_date'             => ['nullable', 'date'],
            'scout_name'             => ['nullable', 'string', 'max:150'],
        ]);

        [$homeTeam, $awayTeam] = $this->extractTeams($validated['match_name']);
        $scoutName = $this->resolveScoutName($request);

        $meta  = [
            'location'     => $validated['location']    ?? null,
            'sport'        => $validated['sport']       ?? null,
            'focus'        => $validated['focus']       ?? null,
            'stream_url'   => $validated['stream_url']  ?? null,
            'stream_links' => is_array($validated['stream_links'] ?? null) ? $validated['stream_links'] : [],
            'note'         => $validated['note']        ?? null,
            'scout_name'   => $scoutName,
        ];

        $match = LiveMatch::query()->create([
            'title'       => $validated['match_name'],
            'league'      => $validated['league']     ?? null,
            'home_team'   => $validated['home_team']  ?? $homeTeam,
            'away_team'   => $validated['away_team']  ?? $awayTeam,
            'home_score'  => null,
            'away_score'  => null,
            'match_date'  => $validated['match_date'] ?? now(),
            'is_live'     => true,
            'is_finished' => false,
            'round'       => $this->encodeRoundMeta(null, $meta),
        ]);

        return $this->successResponse([
            'id'        => $match->id,
            'title'     => $match->title,
            'home_team' => $match->home_team,
            'away_team' => $match->away_team,
        ], 'Canli mac kaydedildi.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $record  = LiveMatch::query()->findOrFail($id);
            $meta    = $this->decodeRoundMeta($record->round);
            $status  = $record->is_finished ? 'finished' : ($record->is_live ? 'live' : 'scheduled');

            $updateRow = null;
            if (Schema::hasTable('live_match_updates')) {
                $updateRow = DB::table('live_match_updates')
                    ->where('match_id', $id)
                    ->orderByDesc('update_time')
                    ->first();
            }

            $events = [];
            if ($updateRow && !empty($updateRow->events)) {
                $decoded = json_decode((string) $updateRow->events, true);
                if (is_array($decoded)) { $events = $decoded; }
            }

            return $this->successResponse([
                'id'           => $record->id,
                'title'        => $record->title,
                'league'       => $record->league,
                'home_team'    => $record->home_team,
                'away_team'    => $record->away_team,
                'home_score'   => $updateRow->home_score ?? $record->home_score,
                'away_score'   => $updateRow->away_score ?? $record->away_score,
                'minute'       => $updateRow->current_minute ?? null,
                'status'       => $updateRow->status ?? $status,
                'match_date'   => $record->match_date?->toIso8601String(),
                'events'       => $events,
                'stadium'      => $meta['location']   ?? null,
                'sport'        => $meta['sport']      ?? null,
                'focus'        => $meta['focus']      ?? null,
                'stream_url'   => $meta['stream_url'] ?? null,
                'stream_links' => is_array($meta['stream_links'] ?? null) ? $meta['stream_links'] : [],
                'scout_name'   => $meta['scout_name'] ?? null,
                'note'         => $meta['note']       ?? null,
                'updated_at'   => $updateRow->update_time ?? $record->updated_at?->toIso8601String(),
            ], 'Mac detayi hazir.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Mac bulunamadi', 404, 'match_not_found');
        }
    }

    private function extractTeams(string $matchName): array
    {
        $parts = preg_split('/\s+[-:]\s+/', trim($matchName));
        if (is_array($parts) && count($parts) >= 2) {
            return [trim($parts[0]) ?: 'Ev Sahibi', trim($parts[1]) ?: 'Deplasman'];
        }
        return ['Ev Sahibi', 'Deplasman'];
    }

    private function encodeRoundMeta(?string $round, array $meta): ?string
    {
        $clean = array_filter($meta, fn ($value) => $value !== null && $value !== '');
        if (empty($clean) && $round) { return $round; }
        if (empty($clean)) { return null; }
        return 'meta::'.json_encode(['round' => $round, 'meta' => $clean], JSON_UNESCAPED_UNICODE);
    }

    private function decodeRoundMeta(?string $round): array
    {
        if (!$round || !str_starts_with($round, 'meta::')) { return []; }
        $decoded = json_decode(substr($round, 6), true);
        if (!is_array($decoded)) { return []; }
        $meta = $decoded['meta'] ?? [];
        return is_array($meta) ? $meta : [];
    }

    private function resolveScoutName(Request $request): ?string
    {
        $name = trim((string) $request->input('scout_name', ''));
        if ($name !== '') { return $name; }
        if (auth()->check()) { return (string) (auth()->user()->name ?? ''); }
        return null;
    }
}
