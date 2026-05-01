<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ClubInternalPlayer;
use App\Models\ClubTeamGroup;
use App\Models\LiveMatch;
use App\Models\LiveMatchEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            ->where('visibility', 'public')
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
            ->where('visibility', 'public')
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
        $this->expirePastMatches();

        $count = LiveMatch::query()
            ->where('visibility', 'public')
            ->where('is_live', true)
            ->where('is_finished', false)
            ->count();

        return $this->successResponse([
            'count'            => $count,
            'has_live_matches' => $count > 0,
        ], 'Canli mac sayisi hazir.');
    }

    public function index(Request $request): JsonResponse
    {
        $this->expirePastMatches();

        $matches = LiveMatch::query()
            ->where('visibility', 'public')
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
                    'source_role'  => $meta['source_role'] ?? null,
                    'source_name'  => $meta['source_name'] ?? null,
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
            'source_role'            => ['nullable', 'string', 'max:50'],
            'source_name'            => ['nullable', 'string', 'max:150'],
            'source_user_id'         => ['nullable', 'integer'],
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
            'source_role'  => $validated['source_role'] ?? null,
            'source_name'  => $validated['source_name'] ?? null,
            'source_user_id' => $validated['source_user_id'] ?? null,
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
            'visibility'  => 'public',
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
            if (($record->visibility ?? 'public') !== 'public') {
                return $this->errorResponse('Mac bulunamadi', 404, 'match_not_found');
            }
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
                'source_role'  => $meta['source_role'] ?? null,
                'source_name'  => $meta['source_name'] ?? null,
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

    private function expirePastMatches(): void
    {
        LiveMatch::query()
            ->where('visibility', 'public')
            ->where('is_live', true)
            ->where('is_finished', false)
            ->whereNotNull('match_date')
            ->where('match_date', '<', now())
            ->update([
                'is_live' => false,
                'is_finished' => true,
            ]);
    }

    public function startClubMatch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Yetkisiz erisim.', Response::HTTP_UNAUTHORIZED, 'unauthorized');
        }

        $validated = $request->validate([
            'group_key' => ['required', 'string', 'max:40'],
            'opponent' => ['required', 'string', 'max:120'],
            'match_date' => ['required', 'date'],
            'periods' => ['required', 'integer', 'min:1', 'max:8'],
            'sport' => ['nullable', 'string', 'max:40'],
        ]);

        $group = ClubTeamGroup::query()
            ->where('club_user_id', $user->id)
            ->where('group_key', $validated['group_key'])
            ->first();

        if (!$group) {
            return $this->errorResponse('Takim grubu bulunamadi.', Response::HTTP_NOT_FOUND, 'group_not_found');
        }

        $meta = [
            'sport' => $validated['sport'] ?? 'basketball',
            'group_name' => $group->name,
            'group_key' => $group->group_key,
            'source_role' => 'club_live_scout',
        ];

        $match = LiveMatch::query()->create([
            'title' => trim(($user->name ?? 'Kulup').' - '.$validated['opponent']),
            'league' => null,
            'home_team' => $user->name,
            'away_team' => $validated['opponent'],
            'home_score' => null,
            'away_score' => null,
            'match_date' => $validated['match_date'],
            'is_live' => true,
            'is_finished' => false,
            'visibility' => 'club_private',
            'club_user_id' => $user->id,
            'group_key' => $group->group_key,
            'periods' => $validated['periods'],
            'started_at' => now(),
            'round' => $this->encodeRoundMeta(null, $meta),
        ]);

        return $this->successResponse([
            'id' => $match->id,
            'group_key' => $match->group_key,
            'group_name' => $group->name,
            'opponent' => $match->away_team,
            'match_date' => $match->match_date?->toIso8601String(),
            'periods' => $match->periods,
            'sport' => $meta['sport'],
            'status' => 'live',
        ], 'Canli mac oturumu baslatildi.', 201);
    }

    public function showClubMatch(Request $request, int $matchId): JsonResponse
    {
        $match = $this->resolveClubMatch($request, $matchId);
        if ($match instanceof JsonResponse) {
            return $match;
        }

        $meta = $this->decodeRoundMeta($match->round);
        $events = LiveMatchEvent::query()
            ->where('live_match_id', $match->id)
            ->orderBy('id')
            ->get()
            ->map(fn (LiveMatchEvent $event) => $this->formatClubEvent($event))
            ->values();

        return $this->successResponse([
            'id' => $match->id,
            'group_key' => $match->group_key,
            'group_name' => $meta['group_name'] ?? null,
            'sport' => $meta['sport'] ?? null,
            'opponent' => $match->away_team,
            'match_date' => $match->match_date?->toIso8601String(),
            'periods' => $match->periods,
            'status' => $match->is_finished ? 'finished' : 'live',
            'started_at' => $match->started_at?->toIso8601String(),
            'finished_at' => $match->finished_at?->toIso8601String(),
            'events' => $events,
        ], 'Kulup canli mac detayi hazir.');
    }

    public function clubMatchSummaries(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Yetkisiz erisim.', Response::HTTP_UNAUTHORIZED, 'unauthorized');
        }

        $validated = $request->validate([
            'group_key' => ['required', 'string', 'max:40'],
        ]);

        $matches = LiveMatch::query()
            ->where('visibility', 'club_private')
            ->where('club_user_id', $user->id)
            ->where('group_key', $validated['group_key'])
            ->where('is_finished', true)
            ->orderByDesc('match_date')
            ->limit(50)
            ->get();

        $summaries = $matches->map(function (LiveMatch $match) {
            $meta = $this->decodeRoundMeta($match->round);
            $events = LiveMatchEvent::query()
                ->where('live_match_id', $match->id)
                ->get();

            $eventCounts = $events
                ->groupBy('event_type')
                ->map(fn ($group) => $group->count());

            return [
                'id' => $match->id,
                'group_key' => $match->group_key,
                'group_name' => $meta['group_name'] ?? null,
                'opponent' => $match->away_team,
                'match_date' => $match->match_date?->toIso8601String(),
                'periods' => $match->periods,
                'event_count' => $events->count(),
                'shot_2_attempt' => (int) ($eventCounts['2 Sayilik Atis Deneme'] ?? 0),
                'shot_2_made' => (int) ($eventCounts['2 Sayilik Atis Basari'] ?? 0),
                'shot_3_attempt' => (int) ($eventCounts['3 Sayilik Atis Deneme'] ?? 0),
                'shot_3_made' => (int) ($eventCounts['3 Sayilik Atis Basari'] ?? 0),
                'free_throw_attempt' => (int) ($eventCounts['Serbest Atis Deneme'] ?? 0),
                'free_throw_made' => (int) ($eventCounts['Serbest Atis Basari'] ?? 0),
                'assists' => (int) ($eventCounts['Asist'] ?? 0),
                'steals' => (int) ($eventCounts['Top Calma'] ?? 0),
                'turnovers' => (int) ($eventCounts['Top Kaybi'] ?? 0),
                'off_rebounds' => (int) ($eventCounts['Hucum Ribaund'] ?? 0),
                'def_rebounds' => (int) ($eventCounts['Savunma Ribaund'] ?? 0),
                'finished_at' => $match->finished_at?->toIso8601String(),
            ];
        })->values();

        return $this->successResponse($summaries, 'Mac ozetleri hazir.', 200, [
            'total' => $summaries->count(),
        ]);
    }

    public function storeClubEvent(Request $request, int $matchId): JsonResponse
    {
        $match = $this->resolveClubMatch($request, $matchId);
        if ($match instanceof JsonResponse) {
            return $match;
        }

        if ($match->is_finished) {
            return $this->errorResponse('Bitmis maca event eklenemez.', Response::HTTP_UNPROCESSABLE_ENTITY, 'match_finished');
        }

        $validated = $request->validate([
            'player_id' => ['required', 'integer'],
            'event_type' => ['required', 'string', 'in:2 Sayilik Atis Deneme,2 Sayilik Atis Basari,3 Sayilik Atis Deneme,3 Sayilik Atis Basari,Serbest Atis Deneme,Serbest Atis Basari,Savunma Ribaund,Hucum Ribaund,Top Calma,Top Kaybi,Asist,Oyuna Girdi,Oyundan Cikti'],
            'period' => ['required', 'integer', 'min:1', 'max:8'],
            'minute' => ['required', 'integer', 'min:0', 'max:99'],
            'second' => ['required', 'integer', 'min:0', 'max:59'],
            'x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'y' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $player = ClubInternalPlayer::query()
            ->where('id', $validated['player_id'])
            ->where('club_user_id', $match->club_user_id)
            ->where('group_key', $match->group_key)
            ->first();

        if (!$player) {
            return $this->errorResponse('Oyuncu bulunamadi.', Response::HTTP_NOT_FOUND, 'player_not_found');
        }

        $event = LiveMatchEvent::query()->create([
            'live_match_id' => $match->id,
            'club_user_id' => $match->club_user_id,
            'club_internal_player_id' => $player->id,
            'group_key' => $match->group_key,
            'event_type' => $validated['event_type'],
            'period' => $validated['period'],
            'minute' => $validated['minute'],
            'second' => $validated['second'],
            'x' => $validated['x'] ?? null,
            'y' => $validated['y'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return $this->successResponse($this->formatClubEvent($event, $player), 'Event kaydedildi.', 201);
    }

    public function finishClubMatch(Request $request, int $matchId): JsonResponse
    {
        $match = $this->resolveClubMatch($request, $matchId);
        if ($match instanceof JsonResponse) {
            return $match;
        }

        $match->forceFill([
            'is_live' => false,
            'is_finished' => true,
            'finished_at' => now(),
        ])->save();

        $eventCount = LiveMatchEvent::query()
            ->where('live_match_id', $match->id)
            ->count();

        return $this->successResponse([
            'id' => $match->id,
            'status' => 'finished',
            'finished_at' => $match->finished_at?->toIso8601String(),
            'event_count' => $eventCount,
        ], 'Canli mac kapatildi.');
    }

    private function resolveClubMatch(Request $request, int $matchId): LiveMatch|JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Yetkisiz erisim.', Response::HTTP_UNAUTHORIZED, 'unauthorized');
        }

        $match = LiveMatch::query()
            ->where('id', $matchId)
            ->where('visibility', 'club_private')
            ->where('club_user_id', $user->id)
            ->first();

        if (!$match) {
            return $this->errorResponse('Mac bulunamadi.', Response::HTTP_NOT_FOUND, 'match_not_found');
        }

        return $match;
    }

    private function formatClubEvent(LiveMatchEvent $event, ?ClubInternalPlayer $player = null): array
    {
        $player ??= ClubInternalPlayer::query()->find($event->club_internal_player_id);

        return [
            'id' => $event->id,
            'match_id' => $event->live_match_id,
            'player_id' => $event->club_internal_player_id,
            'player_name' => $player?->name,
            'shirt_number' => $player?->shirt_number,
            'photo_url' => $player?->photo_url,
            'event_type' => $event->event_type,
            'period' => $event->period,
            'minute' => $event->minute,
            'second' => $event->second,
            'x' => $event->x,
            'y' => $event->y,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }
}
