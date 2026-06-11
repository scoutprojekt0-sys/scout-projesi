<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ClubInternalPlayer;
use App\Models\ClubTeamGroup;
use App\Models\LiveMatch;
use App\Models\LiveMatchEvent;
use App\Models\PlayerMatchRating;
use App\Models\PlayerStatistic;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LiveMatchController extends Controller
{
    use ApiResponds;

    private const LIVE_MATCH_STALE_AFTER_HOURS = 12;

    private const LIVE_MATCH_VISIBILITIES = [
        'public',
        'private',
        'staff_only',
        'players_only',
        'scouts_only',
        'coaches_only',
        'managers_only',
        'clubs_only',
        'lawyers_only',
    ];

    private const ROLE_VISIBILITY_MAP = [
        'players_only' => ['player'],
        'scouts_only' => ['scout'],
        'coaches_only' => ['coach', 'antrenor'],
        'managers_only' => ['manager', 'menajer'],
        'clubs_only' => ['team', 'club', 'kulup'],
        'lawyers_only' => ['lawyer', 'avukat'],
    ];

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
                'id' => $match->id,
                'league' => $match->league,
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
                'status' => 'finished',
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
                'id' => $match->id,
                'league' => $match->league,
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'kickoff' => $match->match_date?->toIso8601String(),
                'status' => 'scheduled',
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
            'scorers' => [
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
            'payload' => $request->all(),
        ], 'Canli mac guncellemesi alindi.');
    }

    public function getCount(Request $request): JsonResponse
    {
        $this->expirePastMatches();

        $viewer = $request->user() ?? auth('sanctum')->user();

        $count = LiveMatch::query()
            ->where('is_live', true)
            ->where('is_finished', false)
            ->orderByDesc('match_date')
            ->limit(150)
            ->get()
            ->filter(fn (LiveMatch $match) => $this->canUserViewMatch($match, $viewer))
            ->count();

        return $this->successResponse([
            'count' => $count,
            'has_live_matches' => $count > 0,
        ], 'Canli mac sayisi hazir.');
    }

    public function index(Request $request): JsonResponse
    {
        $this->expirePastMatches();

        $viewer = $request->user() ?? auth('sanctum')->user();

        $matches = LiveMatch::query()
            ->where('is_live', true)
            ->where('is_finished', false)
            ->orderByDesc('match_date')
            ->limit(150)
            ->get()
            ->filter(fn (LiveMatch $match) => $this->canUserViewMatch($match, $viewer))
            ->map(function (LiveMatch $match) use ($viewer) {
                $meta = $this->decodeRoundMeta($match->round);

                return [
                    'id' => $match->id,
                    'title' => $match->title,
                    'league' => $match->league,
                    'home_team' => $match->home_team,
                    'away_team' => $match->away_team,
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'minute' => null,
                    'status' => 'live',
                    'match_date' => $match->match_date?->toIso8601String(),
                    'location' => $meta['location'] ?? null,
                    'sport' => $meta['sport'] ?? null,
                    'focus' => $meta['focus'] ?? null,
                    'visibility' => $match->visibility ?? 'public',
                    'stream_url' => $meta['stream_url'] ?? null,
                    'stream_links' => is_array($meta['stream_links'] ?? null) ? $meta['stream_links'] : [],
                    'note' => $meta['note'] ?? null,
                    'scout_name' => $meta['scout_name'] ?? null,
                    'source_role' => $meta['source_role'] ?? null,
                    'source_name' => $meta['source_name'] ?? null,
                    'can_manage' => $this->canManageMatch($match, $viewer),
                ];
            })->values();

        return $this->successResponse($matches, 'Canli maclar hazir.', 200, [
            'total' => $matches->count(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'match_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'sport' => ['nullable', 'string', 'max:40'],
            'focus' => ['nullable', 'string', 'max:255'],
            'stream_url' => ['nullable', 'url', 'max:500'],
            'stream_links' => ['nullable', 'array'],
            'stream_links.youtube' => ['nullable', 'url', 'max:500'],
            'stream_links.instagram' => ['nullable', 'url', 'max:500'],
            'stream_links.facebook' => ['nullable', 'url', 'max:500'],
            'stream_links.x' => ['nullable', 'url', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
            'league' => ['nullable', 'string', 'max:120'],
            'home_team' => ['nullable', 'string', 'max:120'],
            'away_team' => ['nullable', 'string', 'max:120'],
            'match_date' => ['nullable', 'date'],
            'scout_name' => ['nullable', 'string', 'max:150'],
            'source_role' => ['nullable', 'string', 'max:50'],
            'source_name' => ['nullable', 'string', 'max:150'],
            'source_user_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', Rule::in(self::LIVE_MATCH_VISIBILITIES)],
        ]);

        [$homeTeam, $awayTeam] = $this->extractTeams($validated['match_name']);
        $scoutName = $this->resolveScoutName($request);

        $meta = [
            'location' => $validated['location'] ?? null,
            'sport' => $validated['sport'] ?? null,
            'focus' => $validated['focus'] ?? null,
            'stream_url' => $validated['stream_url'] ?? null,
            'stream_links' => is_array($validated['stream_links'] ?? null) ? $validated['stream_links'] : [],
            'note' => $validated['note'] ?? null,
            'scout_name' => $scoutName,
            'source_role' => $validated['source_role'] ?? null,
            'source_name' => $validated['source_name'] ?? null,
            'source_user_id' => $validated['source_user_id'] ?? null,
        ];

        $match = LiveMatch::query()->create([
            'title' => $validated['match_name'],
            'league' => $validated['league'] ?? null,
            'home_team' => $validated['home_team'] ?? $homeTeam,
            'away_team' => $validated['away_team'] ?? $awayTeam,
            'home_score' => null,
            'away_score' => null,
            'match_date' => $validated['match_date'] ?? now(),
            'is_live' => true,
            'is_finished' => false,
            'visibility' => $validated['visibility'] ?? 'public',
            'started_at' => now(),
            'round' => $this->encodeRoundMeta(null, $meta),
        ]);

        return $this->successResponse([
            'id' => $match->id,
            'title' => $match->title,
            'home_team' => $match->home_team,
            'away_team' => $match->away_team,
            'visibility' => $match->visibility,
        ], 'Canli mac kaydedildi.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $record = LiveMatch::query()->findOrFail($id);
            $viewer = $request->user() ?? auth('sanctum')->user();
            if (! $this->canUserViewMatch($record, $viewer)) {
                return $this->errorResponse('Mac bulunamadi', 404, 'match_not_found');
            }
            $meta = $this->decodeRoundMeta($record->round);
            $status = $record->is_finished ? 'finished' : ($record->is_live ? 'live' : 'scheduled');

            $updateRow = null;
            if (Schema::hasTable('live_match_updates')) {
                $updateRow = DB::table('live_match_updates')
                    ->where('match_id', $id)
                    ->orderByDesc('update_time')
                    ->first();
            }

            $events = [];
            if ($updateRow && ! empty($updateRow->events)) {
                $decoded = json_decode((string) $updateRow->events, true);
                if (is_array($decoded)) {
                    $events = $decoded;
                }
            }

            return $this->successResponse([
                'id' => $record->id,
                'title' => $record->title,
                'league' => $record->league,
                'home_team' => $record->home_team,
                'away_team' => $record->away_team,
                'home_score' => $updateRow->home_score ?? $record->home_score,
                'away_score' => $updateRow->away_score ?? $record->away_score,
                'minute' => $updateRow->current_minute ?? null,
                'status' => $updateRow->status ?? $status,
                'match_date' => $record->match_date?->toIso8601String(),
                'events' => $events,
                'stadium' => $meta['location'] ?? null,
                'sport' => $meta['sport'] ?? null,
                'focus' => $meta['focus'] ?? null,
                'visibility' => $record->visibility ?? 'public',
                'stream_url' => $meta['stream_url'] ?? null,
                'stream_links' => is_array($meta['stream_links'] ?? null) ? $meta['stream_links'] : [],
                'scout_name' => $meta['scout_name'] ?? null,
                'note' => $meta['note'] ?? null,
                'source_role' => $meta['source_role'] ?? null,
                'source_name' => $meta['source_name'] ?? null,
                'can_manage' => $this->canManageMatch($record, $viewer),
                'updated_at' => $updateRow->update_time ?? $record->updated_at?->toIso8601String(),
            ], 'Mac detayi hazir.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Mac bulunamadi', 404, 'match_not_found');
        }
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        try {
            $match = LiveMatch::query()->findOrFail($id);
            $viewer = $request->user() ?? auth('sanctum')->user();

            if (! $this->canManageMatch($match, $viewer)) {
                return $this->errorResponse('Bu yayini bitirme yetkiniz yok.', Response::HTTP_FORBIDDEN, 'live_match_forbidden');
            }

            $match->forceFill([
                'is_live' => false,
                'is_finished' => true,
                'finished_at' => now(),
            ])->save();

            return $this->successResponse([
                'id' => $match->id,
                'status' => 'finished',
                'finished_at' => $match->finished_at?->toIso8601String(),
            ], 'Canli yayin kapatildi.');
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
        if (empty($clean) && $round) {
            return $round;
        }
        if (empty($clean)) {
            return null;
        }

        return 'meta::'.json_encode(['round' => $round, 'meta' => $clean], JSON_UNESCAPED_UNICODE);
    }

    private function decodeRoundMeta(?string $round): array
    {
        if (! $round || ! str_starts_with($round, 'meta::')) {
            return [];
        }
        $decoded = json_decode(substr($round, 6), true);
        if (! is_array($decoded)) {
            return [];
        }
        $meta = $decoded['meta'] ?? [];

        return is_array($meta) ? $meta : [];
    }

    private function resolveScoutName(Request $request): ?string
    {
        $name = trim((string) $request->input('scout_name', ''));
        if ($name !== '') {
            return $name;
        }
        if (auth()->check()) {
            return (string) (auth()->user()->name ?? '');
        }

        return null;
    }

    private function canUserViewMatch(LiveMatch $match, ?User $viewer): bool
    {
        $visibility = $match->visibility ?? 'public';

        if ($visibility === 'public') {
            return true;
        }

        if ($this->isMatchOwner($match, $viewer)) {
            return true;
        }

        if ($visibility === 'private') {
            return false;
        }

        if ($visibility === 'staff_only') {
            return $this->isProfessionalViewer($viewer);
        }

        if (array_key_exists($visibility, self::ROLE_VISIBILITY_MAP)) {
            return $this->viewerMatchesRoleVisibility($viewer, $visibility);
        }

        return false;
    }

    private function isMatchOwner(LiveMatch $match, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if ((int) ($match->club_user_id ?? 0) === (int) $viewer->id) {
            return true;
        }

        $meta = $this->decodeRoundMeta($match->round);

        return (int) ($meta['source_user_id'] ?? 0) === (int) $viewer->id;
    }

    private function isProfessionalViewer(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        return in_array(Str::lower((string) $viewer->role), [
            'scout',
            'manager',
            'coach',
            'team',
            'club',
            'lawyer',
            'staff',
            'admin',
        ], true);
    }

    private function viewerMatchesRoleVisibility(?User $viewer, string $visibility): bool
    {
        if (! $viewer) {
            return false;
        }

        $role = Str::lower((string) $viewer->role);
        if (in_array($role, ['admin', 'staff'], true)) {
            return true;
        }

        return in_array($role, self::ROLE_VISIBILITY_MAP[$visibility] ?? [], true);
    }

    private function canManageMatch(LiveMatch $match, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if ($this->isMatchOwner($match, $viewer)) {
            return true;
        }

        return in_array(Str::lower((string) $viewer->role), ['admin', 'staff'], true);
    }

    private function expirePastMatches(): void
    {
        $finishedBefore = now()->subHours(self::LIVE_MATCH_STALE_AFTER_HOURS);

        LiveMatch::query()
            ->where('is_live', true)
            ->where('is_finished', false)
            ->where(function ($query) use ($finishedBefore): void {
                $query->where('started_at', '<', $finishedBefore)
                    ->orWhere(function ($query) use ($finishedBefore): void {
                        $query->whereNull('started_at')
                            ->whereNotNull('match_date')
                            ->where('match_date', '<', $finishedBefore);
                    })
                    ->orWhere(function ($query) use ($finishedBefore): void {
                        $query->whereNull('started_at')
                            ->whereNull('match_date')
                            ->where('created_at', '<', $finishedBefore);
                    });
            })
            ->update([
                'is_live' => false,
                'is_finished' => true,
                'finished_at' => now(),
            ]);
    }

    public function startClubMatch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
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

        if (! $group) {
            return $this->errorResponse('Takim grubu bulunamadi.', Response::HTTP_NOT_FOUND, 'group_not_found');
        }

        $sport = $this->normalizeClubMatchSport($validated['sport'] ?? null);

        $meta = [
            'sport' => $sport,
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
            'sport' => $sport,
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
            'actual_elapsed_seconds' => (int) ($meta['actual_elapsed_seconds'] ?? 0),
            'events' => $events,
        ], 'Kulup canli mac detayi hazir.');
    }

    public function clubMatchSummaries(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
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

            $sport = $this->normalizeClubMatchSport($meta['sport'] ?? null);
            $eventCounts = $events
                ->groupBy('event_type')
                ->map(fn ($group) => $group->count());

            $periodBreakdown = $events
                ->groupBy('period')
                ->map(function ($periodEvents, $period) use ($sport) {
                    $counts = $periodEvents
                        ->groupBy('event_type')
                        ->map(fn ($group) => $group->count());

                    return array_merge([
                        'period' => (int) $period,
                        'event_count' => $periodEvents->count(),
                    ], $this->summarizeClubEventCounts($counts->all(), $sport));
                })
                ->sortBy('period')
                ->values();

            return array_merge([
                'id' => $match->id,
                'group_key' => $match->group_key,
                'group_name' => $meta['group_name'] ?? null,
                'sport' => $sport,
                'opponent' => $match->away_team,
                'match_date' => $match->match_date?->toIso8601String(),
                'periods' => $match->periods,
                'event_count' => $events->count(),
                'period_breakdown' => $periodBreakdown,
                'finished_at' => $match->finished_at?->toIso8601String(),
                'actual_elapsed_seconds' => (int) ($meta['actual_elapsed_seconds'] ?? 0),
            ], $this->summarizeClubEventCounts($eventCounts->all(), $sport));
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

        $sport = $this->normalizeClubMatchSport($this->decodeRoundMeta($match->round)['sport'] ?? null);

        $validated = $request->validate([
            'player_id' => ['required', 'integer'],
            'event_type' => ['required', 'string', Rule::in($this->clubEventTypesForSport($sport))],
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

        if (! $player) {
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

        $validated = $request->validate([
            'current_period' => ['nullable', 'integer', 'min:1', 'max:8'],
            'remaining_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $actualElapsedSeconds = $this->resolveActualElapsedSecondsForMatch(
            $match,
            isset($validated['current_period']) ? (int) $validated['current_period'] : null,
            isset($validated['remaining_seconds']) ? (int) $validated['remaining_seconds'] : null,
        );

        $meta = $this->decodeRoundMeta($match->round);
        $meta['actual_elapsed_seconds'] = $actualElapsedSeconds;

        $this->syncClubInternalPlayerStatsFromMatch($match);
        $this->syncPlayerMatchRatingsFromMatch($match, $actualElapsedSeconds);
        $this->syncPlayerProfileStatsFromMatch($match, $actualElapsedSeconds);

        $match->forceFill([
            'is_live' => false,
            'is_finished' => true,
            'finished_at' => now(),
            'round' => $this->encodeRoundMeta(null, $meta),
        ])->save();

        $eventCount = LiveMatchEvent::query()
            ->where('live_match_id', $match->id)
            ->count();

        return $this->successResponse([
            'id' => $match->id,
            'status' => 'finished',
            'finished_at' => $match->finished_at?->toIso8601String(),
            'event_count' => $eventCount,
            'actual_elapsed_seconds' => $actualElapsedSeconds,
        ], 'Canli mac kapatildi.');
    }

    private function resolveClubMatch(Request $request, int $matchId): LiveMatch|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Yetkisiz erisim.', Response::HTTP_UNAUTHORIZED, 'unauthorized');
        }

        $match = LiveMatch::query()
            ->where('id', $matchId)
            ->where('visibility', 'club_private')
            ->where('club_user_id', $user->id)
            ->first();

        if (! $match) {
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

    private function normalizeClubMatchSport(?string $sport): string
    {
        return match (mb_strtolower(trim((string) $sport))) {
            'football', 'soccer', 'futbol' => 'football',
            'volleyball', 'voleybol' => 'volleyball',
            default => 'basketball',
        };
    }

    private function clubEventTypesForSport(string $sport): array
    {
        return match ($this->normalizeClubMatchSport($sport)) {
            'football' => [
                'Gol',
                'Asist',
                'Sut',
                'Isabetli Sut',
                'Top Kapma',
                'Top Kaybi',
                'Faul',
                'Sari Kart',
                'Kirmizi Kart',
                'Korner',
                'Ofsayt',
                'Oyuna Girdi',
                'Oyundan Cikti',
            ],
            'volleyball' => [
                'Sayi',
                'Asist',
                'Hucum',
                'Hucum Hata',
                'Blok',
                'Manset',
                'Pas',
                'Servis Ace',
                'Servis Hata',
                'Top Kaybi',
                'Oyuna Girdi',
                'Oyundan Cikti',
            ],
            default => [
                '1 Sayilik Atis Deneme Basarili',
                '1 Sayilik Atis Deneme Basarisiz',
                '2 Sayilik Atis Deneme',
                '2 Sayilik Atis Basari',
                '3 Sayilik Atis Deneme',
                '3 Sayilik Atis Basari',
                'Serbest Atis Deneme',
                'Serbest Atis Basari',
                'Savunma Ribaund',
                'Hucum Ribaund',
                'Top Calma',
                'Top Kaybi',
                'Asist',
                'Oyuna Girdi',
                'Oyundan Cikti',
            ],
        };
    }

    private function summarizeClubEventCounts(array $counts, string $sport): array
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return [
                'shot_2_attempt' => (int) ($counts['Sut'] ?? 0),
                'shot_2_made' => (int) ($counts['Isabetli Sut'] ?? 0),
                'shot_3_attempt' => (int) ($counts['Gol'] ?? 0),
                'shot_3_made' => (int) ($counts['Korner'] ?? 0),
                'free_throw_attempt' => (int) ($counts['Faul'] ?? 0),
                'free_throw_made' => (int) ($counts['Ofsayt'] ?? 0),
                'assists' => (int) ($counts['Asist'] ?? 0),
                'steals' => (int) ($counts['Top Kapma'] ?? 0),
                'turnovers' => (int) ($counts['Top Kaybi'] ?? 0),
                'off_rebounds' => (int) ($counts['Sari Kart'] ?? 0),
                'def_rebounds' => (int) ($counts['Kirmizi Kart'] ?? 0),
            ];
        }

        if ($this->normalizeClubMatchSport($sport) === 'volleyball') {
            return [
                'shot_2_attempt' => (int) ($counts['Hucum'] ?? 0),
                'shot_2_made' => (int) ($counts['Hucum Hata'] ?? 0),
                'shot_3_attempt' => (int) ($counts['Sayi'] ?? 0),
                'shot_3_made' => (int) ($counts['Blok'] ?? 0),
                'free_throw_attempt' => (int) ($counts['Servis Hata'] ?? 0),
                'free_throw_made' => (int) ($counts['Servis Ace'] ?? 0),
                'assists' => (int) ($counts['Asist'] ?? 0),
                'steals' => (int) ($counts['Manset'] ?? 0),
                'turnovers' => (int) ($counts['Top Kaybi'] ?? 0),
                'off_rebounds' => (int) ($counts['Pas'] ?? 0),
                'def_rebounds' => (int) ($counts['Blok'] ?? 0),
            ];
        }

        return [
            'shot_2_attempt' => (int) ($counts['2 Sayilik Atis Deneme'] ?? 0),
            'shot_2_made' => (int) ($counts['2 Sayilik Atis Basari'] ?? 0),
            'shot_3_attempt' => (int) ($counts['3 Sayilik Atis Deneme'] ?? 0),
            'shot_3_made' => (int) ($counts['3 Sayilik Atis Basari'] ?? 0),
            'free_throw_attempt' => (int) ($counts['Serbest Atis Deneme'] ?? 0)
                + (int) ($counts['1 Sayilik Atis Deneme Basarili'] ?? 0)
                + (int) ($counts['1 Sayilik Atis Deneme Basarisiz'] ?? 0),
            'free_throw_made' => (int) ($counts['Serbest Atis Basari'] ?? 0)
                + (int) ($counts['1 Sayilik Atis Deneme Basarili'] ?? 0),
            'assists' => (int) ($counts['Asist'] ?? 0),
            'steals' => (int) ($counts['Top Calma'] ?? 0),
            'turnovers' => (int) ($counts['Top Kaybi'] ?? 0),
            'off_rebounds' => (int) ($counts['Hucum Ribaund'] ?? 0),
            'def_rebounds' => (int) ($counts['Savunma Ribaund'] ?? 0),
        ];
    }

    private function syncClubInternalPlayerStatsFromMatch(LiveMatch $match): void
    {
        $meta = $this->decodeRoundMeta($match->round);
        $sport = $this->normalizeClubMatchSport($meta['sport'] ?? null);

        $eventsByPlayer = LiveMatchEvent::query()
            ->where('live_match_id', $match->id)
            ->get()
            ->groupBy('club_internal_player_id');

        foreach ($eventsByPlayer as $playerId => $events) {
            $player = ClubInternalPlayer::query()
                ->where('id', (int) $playerId)
                ->where('club_user_id', $match->club_user_id)
                ->first();

            if (! $player) {
                continue;
            }

            $counts = $events
                ->groupBy('event_type')
                ->map(fn ($group) => $group->count())
                ->all();

            $summary = $this->summarizeClubEventCounts($counts, $sport);
            $production = $this->clubProductionValueFromSummary($summary, $sport);
            $assists = (int) ($summary['assists'] ?? 0);

            $player->forceFill([
                'matches' => $this->readNumericValue($player->matches) + 1,
                'goals' => $this->readNumericValue($player->goals) + $production,
                'assists' => $this->readNumericValue($player->assists) + $assists,
            ])->save();
        }
    }

    private function syncPlayerMatchRatingsFromMatch(LiveMatch $match, int $actualElapsedSeconds): void
    {
        $meta = $this->decodeRoundMeta($match->round);
        $sport = $this->normalizeClubMatchSport($meta['sport'] ?? null);

        $eventsByPlayer = LiveMatchEvent::query()
            ->where('live_match_id', $match->id)
            ->orderBy('id')
            ->get()
            ->groupBy('club_internal_player_id');

        foreach ($eventsByPlayer as $playerId => $events) {
            $player = ClubInternalPlayer::query()
                ->where('id', (int) $playerId)
                ->where('club_user_id', $match->club_user_id)
                ->first();

            if (! $player) {
                continue;
            }

            $counts = $events
                ->groupBy('event_type')
                ->map(fn ($group) => $group->count())
                ->all();

            $minutesPlayed = $this->calculatePlayedMinutesForEvents(
                $events,
                $sport,
                (int) ($match->periods ?? 0),
                $actualElapsedSeconds,
            );

            $ratingSummary = $this->buildMatchRatingSummary($counts, $sport);
            $rating = $this->calculateMatchRating(
                $sport,
                $player->position,
                $ratingSummary,
                $minutesPlayed,
            );

            PlayerMatchRating::query()->updateOrCreate(
                [
                    'live_match_id' => $match->id,
                    'club_internal_player_id' => $player->id,
                ],
                [
                    'club_user_id' => $match->club_user_id,
                    'sport' => $sport,
                    'position' => $player->position,
                    'minutes_played' => $minutesPlayed,
                    'base_score' => $rating['base_score'],
                    'positive_score' => $rating['positive_score'],
                    'negative_score' => $rating['negative_score'],
                    'final_rating' => $rating['final_rating'],
                    'summary_json' => $ratingSummary,
                ]
            );

            $this->syncInternalPlayerRatingSnapshot(
                $player,
                $match,
                $sport,
                $minutesPlayed,
                $ratingSummary,
                $rating,
            );
        }
    }

    private function syncPlayerProfileStatsFromMatch(LiveMatch $match, int $actualElapsedSeconds): void
    {
        $meta = $this->decodeRoundMeta($match->round);
        $sport = $this->normalizeClubMatchSport($meta['sport'] ?? null);
        $season = $this->resolveSeasonLabelForMatch($match);

        $eventsByPlayer = LiveMatchEvent::query()
            ->where('live_match_id', $match->id)
            ->orderBy('id')
            ->get()
            ->groupBy('club_internal_player_id');

        foreach ($eventsByPlayer as $playerId => $events) {
            $player = ClubInternalPlayer::query()
                ->where('id', (int) $playerId)
                ->where('club_user_id', $match->club_user_id)
                ->first();

            if (! $player) {
                continue;
            }

            $playerUser = $this->findPlayerUserForInternalPlayer($match, $player);
            if (! $playerUser) {
                continue;
            }

            $counts = $events
                ->groupBy('event_type')
                ->map(fn ($group) => $group->count())
                ->all();

            $summary = $this->summarizeClubEventCounts($counts, $sport);
            $minutesPlayed = $this->calculatePlayedMinutesForEvents(
                $events,
                $sport,
                (int) ($match->periods ?? 0),
                $actualElapsedSeconds,
            );
            $started = $this->didPlayerStartMatch($events);

            $stat = PlayerStatistic::query()->firstOrCreate(
                [
                    'user_id' => (int) $playerUser->id,
                    'club_id' => (int) $match->club_user_id,
                    'season' => $season,
                ],
                [
                    'league' => null,
                    'matches_played' => 0,
                    'matches_started' => 0,
                    'matches_benched' => 0,
                    'goals' => 0,
                    'assists' => 0,
                    'yellow_cards' => 0,
                    'red_cards' => 0,
                    'minutes_played' => 0,
                ]
            );

            $stat->forceFill([
                'matches_played' => ((int) $stat->matches_played) + 1,
                'matches_started' => ((int) $stat->matches_started) + ($started ? 1 : 0),
                'matches_benched' => ((int) $stat->matches_benched) + ($started ? 0 : 1),
                'goals' => ((int) $stat->goals) + $this->clubProductionValueFromSummary($summary, $sport),
                'assists' => ((int) $stat->assists) + ((int) ($summary['assists'] ?? 0)),
                'yellow_cards' => ((int) $stat->yellow_cards) + $this->yellowCardCountFromSummary($summary, $sport),
                'red_cards' => ((int) $stat->red_cards) + $this->redCardCountFromSummary($summary, $sport),
                'minutes_played' => ((int) $stat->minutes_played) + $minutesPlayed,
            ])->save();
        }
    }

    private function buildMatchRatingSummary(array $counts, string $sport): array
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return [
                'goals' => (int) ($counts['Gol'] ?? 0),
                'assists' => (int) ($counts['Asist'] ?? 0),
                'shots' => (int) ($counts['Sut'] ?? 0),
                'shots_on_target' => (int) ($counts['Isabetli Sut'] ?? 0),
                'tackles' => (int) ($counts['Top Kapma'] ?? 0),
                'turnovers' => (int) ($counts['Top Kaybi'] ?? 0),
                'fouls' => (int) ($counts['Faul'] ?? 0),
                'yellow_cards' => (int) ($counts['Sari Kart'] ?? 0),
                'red_cards' => (int) ($counts['Kirmizi Kart'] ?? 0),
                'corners' => (int) ($counts['Korner'] ?? 0),
                'offsides' => (int) ($counts['Ofsayt'] ?? 0),
            ];
        }

        if ($this->normalizeClubMatchSport($sport) === 'volleyball') {
            return [
                'points' => (int) ($counts['Sayi'] ?? 0),
                'assists' => (int) ($counts['Asist'] ?? 0),
                'attacks' => (int) ($counts['Hucum'] ?? 0),
                'attack_errors' => (int) ($counts['Hucum Hata'] ?? 0),
                'blocks' => (int) ($counts['Blok'] ?? 0),
                'receptions' => (int) ($counts['Manset'] ?? 0),
                'sets' => (int) ($counts['Pas'] ?? 0),
                'aces' => (int) ($counts['Servis Ace'] ?? 0),
                'service_errors' => (int) ($counts['Servis Hata'] ?? 0),
                'turnovers' => (int) ($counts['Top Kaybi'] ?? 0),
            ];
        }

        return [
            'ft_made_alt' => (int) ($counts['1 Sayilik Atis Deneme Basarili'] ?? 0),
            'ft_miss_alt' => (int) ($counts['1 Sayilik Atis Deneme Basarisiz'] ?? 0),
            'two_pt_attempt' => (int) ($counts['2 Sayilik Atis Deneme'] ?? 0),
            'two_pt_made' => (int) ($counts['2 Sayilik Atis Basari'] ?? 0),
            'three_pt_attempt' => (int) ($counts['3 Sayilik Atis Deneme'] ?? 0),
            'three_pt_made' => (int) ($counts['3 Sayilik Atis Basari'] ?? 0),
            'ft_attempt' => (int) ($counts['Serbest Atis Deneme'] ?? 0),
            'ft_made' => (int) ($counts['Serbest Atis Basari'] ?? 0),
            'def_reb' => (int) ($counts['Savunma Ribaund'] ?? 0),
            'off_reb' => (int) ($counts['Hucum Ribaund'] ?? 0),
            'steals' => (int) ($counts['Top Calma'] ?? 0),
            'turnovers' => (int) ($counts['Top Kaybi'] ?? 0),
            'assists' => (int) ($counts['Asist'] ?? 0),
        ];
    }

    private function calculateMatchRating(string $sport, ?string $position, array $summary, int $minutesPlayed): array
    {
        $baseScore = 6.0;
        $normalizedSport = $this->normalizeClubMatchSport($sport);
        $weights = $this->matchRatingWeights($normalizedSport);
        $multipliers = $this->positionRatingMultipliers($normalizedSport, $position);

        $positiveScore = 0.0;
        $negativeScore = 0.0;

        foreach ($weights as $key => $weight) {
            $count = (float) ($summary[$key] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $impact = $count * abs($weight) * (float) ($multipliers[$key] ?? 1.0);
            if ($weight >= 0) {
                $positiveScore += $impact;
            } else {
                $negativeScore += $impact;
            }
        }

        $expectedMinutes = match ($normalizedSport) {
            'basketball' => 40,
            'volleyball' => 75,
            default => 90,
        };

        $minutesFactor = min($minutesPlayed / max($expectedMinutes, 1), 1.0);
        $impactFactor = 0.6 + (0.4 * $minutesFactor);
        $finalRating = $baseScore + (($positiveScore - $negativeScore) * $impactFactor);

        return [
            'base_score' => $baseScore,
            'positive_score' => round($positiveScore, 2),
            'negative_score' => round($negativeScore, 2),
            'final_rating' => max(1.0, min(10.0, round($finalRating, 2))),
        ];
    }

    private function matchRatingWeights(string $sport): array
    {
        return match ($sport) {
            'football' => [
                'goals' => 1.20,
                'assists' => 0.90,
                'shots' => 0.08,
                'shots_on_target' => 0.20,
                'tackles' => 0.15,
                'corners' => 0.10,
                'turnovers' => -0.12,
                'fouls' => -0.08,
                'yellow_cards' => -0.45,
                'red_cards' => -1.20,
                'offsides' => -0.10,
            ],
            'volleyball' => [
                'points' => 0.55,
                'assists' => 0.28,
                'attacks' => 0.25,
                'blocks' => 0.45,
                'receptions' => 0.18,
                'sets' => 0.16,
                'aces' => 0.50,
                'attack_errors' => -0.30,
                'service_errors' => -0.28,
                'turnovers' => -0.25,
            ],
            default => [
                'two_pt_made' => 0.45,
                'three_pt_made' => 0.70,
                'ft_made' => 0.22,
                'ft_made_alt' => 0.22,
                'assists' => 0.30,
                'def_reb' => 0.18,
                'off_reb' => 0.25,
                'steals' => 0.35,
                'turnovers' => -0.28,
                'two_pt_attempt' => -0.06,
                'three_pt_attempt' => -0.08,
                'ft_attempt' => -0.03,
                'ft_miss_alt' => -0.08,
            ],
        };
    }

    private function positionRatingMultipliers(string $sport, ?string $position): array
    {
        if ($sport !== 'football') {
            return [];
        }

        $normalizedPosition = Str::upper(trim((string) $position));

        return match ($normalizedPosition) {
            'ST', 'CF', 'FW' => ['goals' => 1.20, 'assists' => 1.00, 'tackles' => 0.80],
            'CB', 'LB', 'RB', 'DEF' => ['goals' => 0.90, 'tackles' => 1.20, 'turnovers' => 1.10],
            'CM', 'AM', 'DM', 'MF' => ['assists' => 1.15, 'tackles' => 1.00, 'turnovers' => 1.10],
            default => [],
        };
    }

    private function syncInternalPlayerRatingSnapshot(
        ClubInternalPlayer $player,
        LiveMatch $match,
        string $sport,
        int $minutesPlayed,
        array $summary,
        array $rating,
    ): void {
        $history = $player->performance_history ?? [];
        array_unshift($history, [
            'match_id' => $match->id,
            'match_name' => $match->title,
            'match_date' => $match->match_date?->toIso8601String(),
            'sport' => $sport,
            'minutes' => $minutesPlayed,
            'goals' => $this->summaryPrimaryProduction($summary, $sport),
            'assists' => (int) ($summary['assists'] ?? 0),
            'rating' => number_format((float) $rating['final_rating'], 2, '.', ''),
            'summary' => $this->ratingSummaryText($summary, $sport),
            'summary_map' => $summary,
            'highlights' => $this->ratingHighlights($summary, $sport),
            'created_at' => now()->toIso8601String(),
        ]);
        $history = array_slice($history, 0, 12);

        $recentRatings = array_values(array_filter(array_map(
            fn ($item) => is_numeric($item['rating'] ?? null) ? (float) $item['rating'] : null,
            $history
        ), fn ($value) => $value !== null));

        $averageRating = count($recentRatings) > 0
            ? round(array_sum($recentRatings) / count($recentRatings), 2)
            : (float) $rating['final_rating'];

        $player->forceFill([
            'minutes' => (string) ($this->readNumericValue($player->minutes) + $minutesPlayed),
            'rating' => number_format($averageRating, 2, '.', ''),
            'performance_history' => array_values($history),
        ])->save();
    }

    private function summaryPrimaryProduction(array $summary, string $sport): int
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return (int) ($summary['goals'] ?? 0);
        }

        if ($this->normalizeClubMatchSport($sport) === 'volleyball') {
            return (int) ($summary['points'] ?? 0);
        }

        return ((int) ($summary['two_pt_made'] ?? 0) * 2)
            + ((int) ($summary['three_pt_made'] ?? 0) * 3)
            + (int) (($summary['ft_made'] ?? 0) + ($summary['ft_made_alt'] ?? 0));
    }

    private function ratingSummaryText(array $summary, string $sport): string
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return trim(implode(' | ', array_filter([
                ((int) ($summary['goals'] ?? 0)) > 0 ? ((int) $summary['goals']).' gol' : null,
                ((int) ($summary['assists'] ?? 0)) > 0 ? ((int) $summary['assists']).' asist' : null,
                ((int) ($summary['tackles'] ?? 0)) > 0 ? ((int) $summary['tackles']).' top kapma' : null,
            ])));
        }

        if ($this->normalizeClubMatchSport($sport) === 'volleyball') {
            return trim(implode(' | ', array_filter([
                ((int) ($summary['points'] ?? 0)) > 0 ? ((int) $summary['points']).' sayi' : null,
                ((int) ($summary['assists'] ?? 0)) > 0 ? ((int) $summary['assists']).' asist' : null,
                ((int) ($summary['blocks'] ?? 0)) > 0 ? ((int) $summary['blocks']).' blok' : null,
            ])));
        }

        return trim(implode(' | ', array_filter([
            $this->summaryPrimaryProduction($summary, $sport) > 0
                ? $this->summaryPrimaryProduction($summary, $sport).' sayi'
                : null,
            ((int) ($summary['assists'] ?? 0)) > 0 ? ((int) $summary['assists']).' asist' : null,
            ((int) ($summary['def_reb'] ?? 0) + (int) ($summary['off_reb'] ?? 0)) > 0
                ? (((int) ($summary['def_reb'] ?? 0) + (int) ($summary['off_reb'] ?? 0))).' ribaund'
                : null,
        ])));
    }

    private function ratingHighlights(array $summary, string $sport): array
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return array_values(array_filter([
                $this->highlightItem('Gol', (int) ($summary['goals'] ?? 0)),
                $this->highlightItem('Asist', (int) ($summary['assists'] ?? 0)),
                $this->highlightItem('Kirmizi Kart', (int) ($summary['red_cards'] ?? 0), true),
                $this->highlightItem('Top Kaybi', (int) ($summary['turnovers'] ?? 0), true),
                $this->highlightItem('Sari Kart', (int) ($summary['yellow_cards'] ?? 0), true),
                $this->highlightItem('Isabetli Sut', (int) ($summary['shots_on_target'] ?? 0)),
                $this->highlightItem('Top Kapma', (int) ($summary['tackles'] ?? 0)),
            ]));
        }

        if ($this->normalizeClubMatchSport($sport) === 'volleyball') {
            return array_values(array_filter([
                $this->highlightItem('Sayi', (int) ($summary['points'] ?? 0)),
                $this->highlightItem('Asist', (int) ($summary['assists'] ?? 0)),
                $this->highlightItem('Blok', (int) ($summary['blocks'] ?? 0)),
                $this->highlightItem('Ace', (int) ($summary['aces'] ?? 0)),
                $this->highlightItem('Servis Hata', (int) ($summary['service_errors'] ?? 0), true),
                $this->highlightItem('Hucum Hata', (int) ($summary['attack_errors'] ?? 0), true),
                $this->highlightItem('Top Kaybi', (int) ($summary['turnovers'] ?? 0), true),
            ]));
        }

        return array_values(array_filter([
            $this->highlightItem('Sayi', $this->summaryPrimaryProduction($summary, $sport)),
            $this->highlightItem('Asist', (int) ($summary['assists'] ?? 0)),
            $this->highlightItem('Ribaund', ((int) ($summary['def_reb'] ?? 0) + (int) ($summary['off_reb'] ?? 0))),
            $this->highlightItem('Top Calma', (int) ($summary['steals'] ?? 0)),
            $this->highlightItem('Top Kaybi', (int) ($summary['turnovers'] ?? 0), true),
        ]));
    }

    private function highlightItem(string $label, int $value, bool $negative = false): ?array
    {
        if ($value <= 0) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $value,
            'negative' => $negative,
        ];
    }

    private function clubProductionValueFromSummary(array $summary, string $sport): int
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return (int) ($summary['shot_3_attempt'] ?? 0);
        }

        if ($this->normalizeClubMatchSport($sport) === 'volleyball') {
            return (int) ($summary['shot_3_attempt'] ?? 0);
        }

        return ((int) ($summary['shot_2_made'] ?? 0) * 2)
            + ((int) ($summary['shot_3_made'] ?? 0) * 3)
            + (int) ($summary['free_throw_made'] ?? 0);
    }

    private function yellowCardCountFromSummary(array $summary, string $sport): int
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return (int) ($summary['off_rebounds'] ?? 0);
        }

        return 0;
    }

    private function redCardCountFromSummary(array $summary, string $sport): int
    {
        if ($this->normalizeClubMatchSport($sport) === 'football') {
            return (int) ($summary['def_rebounds'] ?? 0);
        }

        return 0;
    }

    private function findPlayerUserForInternalPlayer(LiveMatch $match, ClubInternalPlayer $player): ?User
    {
        $club = User::query()
            ->select('users.*', 'team_profiles.team_name as resolved_team_name')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.id', (int) $match->club_user_id)
            ->first();

        if (! $club) {
            return null;
        }

        $teamName = trim((string) ($club->getAttribute('resolved_team_name') ?: $club->name ?: ''));
        $normalizedTeamName = $this->normalizeLookupValue($teamName);
        $normalizedPlayerName = $this->normalizeLookupValue((string) $player->name);

        return User::query()
            ->select('users.*', 'player_profiles.current_team as login_current_team')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->get()
            ->first(function (User $user) use ($normalizedPlayerName, $normalizedTeamName): bool {
                $currentTeam = (string) ($user->getAttribute('login_current_team') ?? '');

                return $this->normalizeLookupValue((string) $user->name) === $normalizedPlayerName
                    && $this->normalizeLookupValue($currentTeam) === $normalizedTeamName;
            });
    }

    private function normalizeLookupValue(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->squish()
            ->replace(' ', '')
            ->ascii('tr')
            ->lower()
            ->value();
    }

    private function resolveSeasonLabelForMatch(LiveMatch $match): string
    {
        $matchDate = $match->match_date ?? now();
        $startYear = (int) ($matchDate->month >= 7 ? $matchDate->year : $matchDate->year - 1);

        return sprintf('%d-%d', $startYear, $startYear + 1);
    }

    private function liveMatchPeriodDurationSeconds(string $sport): int
    {
        return match ($this->normalizeClubMatchSport($sport)) {
            'football' => 45 * 60,
            'volleyball' => 25 * 60,
            default => 10 * 60,
        };
    }

    private function calculatePlayedMinutesForEvents($events, string $sport, int $periods, ?int $actualElapsedSeconds = null): int
    {
        $periodDurationSeconds = $this->liveMatchPeriodDurationSeconds($sport);
        $scheduledMatchDurationSeconds = max(1, max(1, $periods) * $periodDurationSeconds);
        $matchDurationSeconds = max(
            1,
            min($scheduledMatchDurationSeconds, (int) ($actualElapsedSeconds ?? $scheduledMatchDurationSeconds))
        );

        if ($events->isEmpty()) {
            return 0;
        }

        $sorted = $events->sort(function (LiveMatchEvent $left, LiveMatchEvent $right) use ($periodDurationSeconds) {
            $leftTimestamp = $this->eventElapsedSeconds($left, $periodDurationSeconds);
            $rightTimestamp = $this->eventElapsedSeconds($right, $periodDurationSeconds);

            if ($leftTimestamp !== $rightTimestamp) {
                return $leftTimestamp <=> $rightTimestamp;
            }

            if ($left->event_type === $right->event_type) {
                return 0;
            }

            if ($left->event_type === 'Oyuna Girdi') {
                return -1;
            }

            if ($right->event_type === 'Oyuna Girdi') {
                return 1;
            }

            if ($left->event_type === 'Oyundan Cikti') {
                return -1;
            }

            if ($right->event_type === 'Oyundan Cikti') {
                return 1;
            }

            return 0;
        })->values();

        $playedSeconds = 0;
        $onCourt = false;
        $stintStart = null;

        foreach ($sorted as $event) {
            $timestamp = $this->eventElapsedSeconds($event, $periodDurationSeconds);

            switch ($event->event_type) {
                case 'Oyuna Girdi':
                    if (! $onCourt) {
                        $onCourt = true;
                        $stintStart = $timestamp;
                    }
                    break;

                case 'Oyundan Cikti':
                    if (! $onCourt) {
                        $hasEntryAtSameTimestamp = $sorted->contains(function (LiveMatchEvent $candidate) use ($event, $timestamp, $periodDurationSeconds): bool {
                            return $candidate->id !== $event->id
                                && $candidate->event_type === 'Oyuna Girdi'
                                && $this->eventElapsedSeconds($candidate, $periodDurationSeconds) === $timestamp;
                        });

                        if ($hasEntryAtSameTimestamp) {
                            break;
                        }

                        $onCourt = true;
                        $stintStart = 0;
                    }

                    $playedSeconds += max(0, $timestamp - ($stintStart ?? 0));
                    $onCourt = false;
                    $stintStart = null;
                    break;

                default:
                    if (! $onCourt) {
                        $onCourt = true;
                        $stintStart = 0;
                    }
                    break;
            }
        }

        if ($onCourt) {
            $playedSeconds += max(0, $matchDurationSeconds - ($stintStart ?? 0));
        }

        return (int) ceil(min($playedSeconds, $matchDurationSeconds) / 60);
    }

    private function resolveActualElapsedSecondsForMatch(
        LiveMatch $match,
        ?int $currentPeriod,
        ?int $remainingSeconds
    ): int {
        $periodDurationSeconds = $this->liveMatchPeriodDurationSeconds(
            $this->normalizeClubMatchSport($this->decodeRoundMeta($match->round)['sport'] ?? null)
        );
        $scheduledMatchDurationSeconds = max(1, max(1, (int) ($match->periods ?? 0)) * $periodDurationSeconds);

        if ($currentPeriod !== null && $remainingSeconds !== null) {
            $elapsedInCurrentPeriod = max(0, min($periodDurationSeconds, $periodDurationSeconds - $remainingSeconds));
            $elapsed = (max(0, $currentPeriod - 1) * $periodDurationSeconds) + $elapsedInCurrentPeriod;

            return max(1, min($scheduledMatchDurationSeconds, $elapsed));
        }

        return $scheduledMatchDurationSeconds;
    }

    private function didPlayerStartMatch($events): bool
    {
        if ($events->isEmpty()) {
            return false;
        }

        $firstEvent = $events->sortBy([
            ['period', 'asc'],
            ['minute', 'asc'],
            ['second', 'asc'],
            ['id', 'asc'],
        ])->first();

        return $firstEvent?->event_type !== 'Oyuna Girdi';
    }

    private function eventElapsedSeconds(LiveMatchEvent $event, int $periodDurationSeconds): int
    {
        $elapsed = ((int) $event->minute * 60) + (int) $event->second;
        $elapsedInPeriod = max(0, min($periodDurationSeconds, $elapsed));

        return (max(0, ((int) $event->period) - 1) * $periodDurationSeconds) + $elapsedInPeriod;
    }

    private function readNumericValue(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
