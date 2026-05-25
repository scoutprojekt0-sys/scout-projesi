<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\EnforcesPrivacy;
use App\Http\Controllers\Concerns\ResolvesPublicFileUrls;
use App\Support\ProfileReviewData;
use App\Support\SportBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PlayerController extends Controller
{
    use EnforcesPrivacy;
    use ResolvesPublicFileUrls;
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
            'age_min' => ['nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['nullable', 'integer', 'min:10', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentYear = (int) now()->format('Y');
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('users')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.sport',
                'users.gender',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.phone',
                'player_profiles.birth_year',
                'player_profiles.position',
                'player_profiles.dominant_foot',
                'player_profiles.height_cm',
                'player_profiles.weight_kg',
                'player_profiles.current_team',
                'player_profiles.bio',
            ]);

        if (! empty($validated['position'])) {
            $query->where('player_profiles.position', 'like', '%'.$validated['position'].'%');
        }

        if (! empty($validated['city'])) {
            $query->where('users.city', 'like', '%'.$validated['city'].'%');
        }

        if (! empty($validated['age_min'])) {
            $birthYearMax = $currentYear - (int) $validated['age_min'];
            $query->where('player_profiles.birth_year', '<=', $birthYearMax);
        }

        if (! empty($validated['age_max'])) {
            $birthYearMin = $currentYear - (int) $validated['age_max'];
            $query->where('player_profiles.birth_year', '>=', $birthYearMin);
        }

        $players = $query
            ->orderByDesc('users.created_at')
            ->paginate($perPage);

        $authUser = $request->user();
        $canSeePrivate = $this->isAdmin($authUser);

        $players->getCollection()->transform(function ($player) use ($authUser, $canSeePrivate) {
            $isOwner = $authUser && (int) $authUser->id === (int) ($player->id ?? 0);
            return $this->redactPrivateFields($player, $canSeePrivate || $isOwner);
        });

        return response()->json([
            'ok' => true,
            'filters' => [
                'position' => $validated['position'] ?? null,
                'city' => $validated['city'] ?? null,
                'age_min' => $validated['age_min'] ?? null,
                'age_max' => $validated['age_max'] ?? null,
            ],
            'data' => $players,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $player = DB::table('users')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.sport',
                'users.gender',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.phone',
                'users.photo_url',
                'users.views_count',
                'users.rating',
                'users.updated_at',
                'users.created_at',
                'player_profiles.birth_year',
                'player_profiles.position',
                'player_profiles.dominant_foot',
                'player_profiles.height_cm',
                'player_profiles.weight_kg',
                'player_profiles.current_team',
                'player_profiles.bio',
            ])
            ->first();

        if (! $player) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $clubInternalPlayer = $this->resolveClubInternalPlayerForUser($player);
        if ($clubInternalPlayer && empty($player->photo_url) && ! empty($clubInternalPlayer->photo_url)) {
            $player->photo_url = $clubInternalPlayer->photo_url;
        }
        if ($clubInternalPlayer && empty($player->current_team) && ! empty($clubInternalPlayer->current_team)) {
            $player->current_team = $clubInternalPlayer->current_team;
        }
        if ($clubInternalPlayer) {
            $player->club_internal_player_id = (int) $clubInternalPlayer->id;
            $player->shirt_number = $clubInternalPlayer->shirt_number;
            $player->group_key = $clubInternalPlayer->group_key;
        }

        $authUser = $request->user();
        $isOwner = $authUser && (int) $authUser->id === (int) ($player->id ?? 0);
        $player = $this->redactPrivateFields($player, $this->isAdmin($authUser) || $isOwner);
        $sport = $this->normalizePublicProfileSport($player->sport ?? null);
        $statsPayload = $this->buildPlayerStatsPayload((int) $id, $player, $clubInternalPlayer, $sport);
        $clubCount = $this->resolvePlayerClubCount((int) $id, $player);
        $showcase = $this->buildShowcaseStatus((int) $id, $player);

        return response()->json([
            'ok' => true,
            'data' => [
                ...((array) $player),
                'current_club' => (string) (($player->current_team ?? null) ?: '-'),
                'club_name' => (string) (($player->current_team ?? null) ?: '-'),
                'overall_rating' => $statsPayload['summary']['rating'],
                'matches_played' => $statsPayload['summary']['matches'],
                'minutes_played' => $statsPayload['summary']['minutes'],
                'goals' => $statsPayload['summary']['goals'],
                'assists' => $statsPayload['summary']['assists'],
                'club_count' => $clubCount,
                'sport' => $sport,
                'stats' => $statsPayload,
                'talent_metrics' => $this->buildTalentMetrics($statsPayload['summary'], $sport),
                'showcase' => $showcase,
                'reviews' => ProfileReviewData::latestForTarget($id, $authUser),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = DB::table('users')->where('id', $id)->where('role', 'player')->first();
        if (! $target) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        if ((int) $authUser->id !== $id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu profili guncelleme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'sport' => ['sometimes', 'nullable', Rule::in(SportBranch::allowedInputs())],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'contract_status' => ['sometimes', 'nullable', Rule::in(['active', 'free'])],
            'seeking_club' => ['sometimes', 'nullable', 'boolean'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'photo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'birth_year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:'.now()->format('Y')],
            'position' => ['sometimes', 'nullable', 'string', 'max:40'],
            'dominant_foot' => ['sometimes', 'nullable', Rule::in(['left', 'right', 'both'])],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'min:120', 'max:230'],
            'weight_kg' => ['sometimes', 'nullable', 'integer', 'min:35', 'max:160'],
            'current_team' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $currentSport = SportBranch::normalize($authUser->sport ?? $target->sport ?? null);
        $requestedSport = array_key_exists('sport', $validated)
            ? SportBranch::normalize($validated['sport'])
            : null;

        if ($currentSport !== null && $requestedSport !== null && $requestedSport !== $currentSport) {
            return response()->json([
                'ok' => false,
                'message' => 'Brans kayit sonrasi degistirilemez.',
                'errors' => [
                    'sport' => ['Brans kayit sonrasi degistirilemez.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resolvedSport = $currentSport ?? $requestedSport;

        DB::table('users')
            ->where('id', $id)
            ->where('role', 'player')
            ->update([
                'name' => $validated['name'] ?? $authUser->name,
                'sport' => $resolvedSport ?? $authUser->sport,
                'gender' => array_key_exists('gender', $validated) ? $validated['gender'] : $authUser->gender,
                'contract_status' => array_key_exists('contract_status', $validated) ? $validated['contract_status'] : $authUser->contract_status,
                'seeking_club' => array_key_exists('seeking_club', $validated) ? $validated['seeking_club'] : $authUser->seeking_club,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $authUser->city,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $authUser->phone,
                'age' => array_key_exists('age', $validated) ? $validated['age'] : $authUser->age,
                'rating' => array_key_exists('rating', $validated) ? $validated['rating'] : $authUser->rating,
                'photo_url' => array_key_exists('photo_url', $validated) ? $validated['photo_url'] : $authUser->photo_url,
                'position' => array_key_exists('position', $validated) ? $validated['position'] : $authUser->position,
                'updated_at' => now(),
            ]);

        $existingProfile = DB::table('player_profiles')->where('user_id', $id)->first();

        DB::table('player_profiles')->updateOrInsert(
            ['user_id' => $id],
            [
                'birth_year' => array_key_exists('birth_year', $validated) ? $validated['birth_year'] : ($existingProfile->birth_year ?? null),
                'position' => array_key_exists('position', $validated) ? $validated['position'] : ($existingProfile->position ?? null),
                'dominant_foot' => array_key_exists('dominant_foot', $validated) ? $validated['dominant_foot'] : ($existingProfile->dominant_foot ?? null),
                'height_cm' => array_key_exists('height_cm', $validated) ? $validated['height_cm'] : ($existingProfile->height_cm ?? null),
                'weight_kg' => array_key_exists('weight_kg', $validated) ? $validated['weight_kg'] : ($existingProfile->weight_kg ?? null),
                'current_team' => array_key_exists('current_team', $validated) ? $validated['current_team'] : ($existingProfile->current_team ?? null),
                'bio' => array_key_exists('bio', $validated) ? $validated['bio'] : ($existingProfile->bio ?? null),
                'updated_at' => now(),
            ]
        );

        $updated = DB::table('users')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.sport',
                'users.gender',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.phone',
                'users.age',
                'users.photo_url',
                'users.rating',
                'users.position as user_position',
                'player_profiles.birth_year',
                'player_profiles.position',
                'player_profiles.dominant_foot',
                'player_profiles.height_cm',
                'player_profiles.weight_kg',
                'player_profiles.current_team',
                'player_profiles.bio',
            ])
            ->first();

        return response()->json([
            'ok' => true,
            'message' => 'Oyuncu profili guncellendi.',
            'data' => $updated,
        ]);
    }

    public function publicProfile(int $id): JsonResponse
    {
        $player = DB::table('users')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.sport',
                'users.gender',
                'users.is_verified',
                'users.verification_status',
                'users.verified_at',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.country',
                'users.age',
                'users.position as user_position',
                'users.photo_url',
                'users.rating as user_rating',
                'users.confidence_score',
                'pp.birth_year',
                'pp.position',
                'pp.dominant_foot',
                'pp.height_cm',
                'pp.weight_kg',
                'pp.current_team',
                'pp.bio',
            ])
            ->first();

        if (! $player) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $clubInternalPlayer = $this->resolveClubInternalPlayerForUser($player);
        $fallbackPhotoUrl = $player->photo_url ?? null;
        if (empty($fallbackPhotoUrl) && $clubInternalPlayer && ! empty($clubInternalPlayer->photo_url)) {
            $fallbackPhotoUrl = $clubInternalPlayer->photo_url;
        }
        if (empty($fallbackPhotoUrl) && Schema::hasTable('media')) {
            $fallbackPhotoUrl = DB::table('media')
                ->where('user_id', $id)
                ->where('type', 'image')
                ->orderByDesc('id')
                ->value('url');
        }

        $sport = $this->normalizePublicProfileSport($player->sport ?? null);
        $statsPayload = $this->buildPlayerStatsPayload($id, $player, $clubInternalPlayer, $sport);
        $summary = $statsPayload['summary'];
        $latest = $statsPayload['latest'];
        $clubPerformance = $statsPayload['club_performance'] ?? null;
        $talentMetrics = $this->buildTalentMetrics($summary, $sport);

        $position = $player->position
            ?: $player->user_position
            ?: ($clubInternalPlayer?->position ?: 'Oyuncu');
        $age = $player->age;
        if (! $age && $player->birth_year) {
            $age = (int) now()->format('Y') - (int) $player->birth_year;
        }
        if (! $age && $clubInternalPlayer && ! empty($clubInternalPlayer->birth_year)) {
            $age = (int) now()->format('Y') - (int) $clubInternalPlayer->birth_year;
        }
        $verificationStatus = strtolower((string) ($player->verification_status ?? ''));
        $isVerified = (bool) ($player->is_verified ?? false)
            || $verificationStatus === 'verified'
            || ! empty($player->verified_at);
        $showcase = $this->buildShowcaseStatus((int) $id, $player);
        $profileClubName = $player->current_team
            ?: ($clubInternalPlayer?->current_team ?: '-');
        $profileBio = $player->bio ?: ($clubInternalPlayer?->bio ?: '');
        $latestSummary = $statsPayload['latest'];
        $historyRows = collect($statsPayload['history']);

        return response()->json([
            'ok' => true,
            'data' => [
                'user' => [
                    'id' => (int) $player->id,
                    'name' => (string) $player->name,
                    'sport' => $sport,
                    'gender' => (string) ($player->gender ?: 'bay'),
                ],
                'profile' => [
                    'name' => (string) $player->name,
                    'sport' => $sport,
                    'branch' => $sport,
                    'gender' => (string) ($player->gender ?: 'bay'),
                    'position' => (string) $position,
                    'age' => $age !== null ? (int) $age : null,
                    'birth_year' => $player->birth_year
                        ? (int) $player->birth_year
                        : ($clubInternalPlayer?->birth_year ? (int) $clubInternalPlayer->birth_year : null),
                    'height_cm' => $player->height_cm
                        ? (int) $player->height_cm
                        : ($clubInternalPlayer && is_numeric((string) $clubInternalPlayer->height)
                            ? (int) $clubInternalPlayer->height
                            : null),
                    'weight_kg' => $player->weight_kg ? (int) $player->weight_kg : null,
                    'current_club' => (string) $profileClubName,
                    'club_name' => (string) $profileClubName,
                    'bio' => (string) $profileBio,
                    'dominant_foot' => $player->dominant_foot ?: ($clubInternalPlayer?->dominant_foot),
                    'shirt_number' => $clubInternalPlayer?->shirt_number,
                    'club_internal_player_id' => $clubInternalPlayer ? (int) $clubInternalPlayer->id : null,
                    'contract_status' => (string) ($player->contract_status ?: 'active'),
                    'seeking_club' => (bool) ($player->seeking_club ?? false),
                    'nationality' => (string) ($player->country ?? ''),
                    'city' => (string) ($player->city ?? ''),
                    'photo_url' => $this->publicFileUrl($fallbackPhotoUrl),
                    'profile_photo_url' => $this->publicFileUrl($fallbackPhotoUrl),
                    'views_count' => (int) ($player->views_count ?? 0),
                    'view_count' => (int) ($player->views_count ?? 0),
                    'is_verified' => $isVerified,
                    'verification_status' => $verificationStatus ?: null,
                ],
                'card' => [
                    'id' => (int) $player->id,
                    'position' => (string) $position,
                    'age' => $age !== null ? (int) $age : null,
                    'height' => $player->height_cm ? ((int) $player->height_cm).'cm' : '-',
                    'overall_rating' => $summary['rating'],
                    'matches_played' => $summary['matches'],
                    'goals' => $summary['goals'],
                    'assists' => $summary['assists'],
                    'nationality' => (string) ($player->country ?? ''),
                    'profile_photo_url' => $this->publicFileUrl($fallbackPhotoUrl),
                    'birth_year' => $player->birth_year ? (int) $player->birth_year : null,
                    'dominant_foot' => $player->dominant_foot,
                    'weight_kg' => $player->weight_kg ? (int) $player->weight_kg : null,
                    'view_count' => (int) ($player->views_count ?? 0),
                    'confidence_score' => $player->confidence_score !== null ? (float) $player->confidence_score : null,
                    'is_verified' => $isVerified,
                    'verification_status' => $verificationStatus ?: null,
                    'showcase' => $showcase,
                    'talent_metrics' => $talentMetrics,
                ],
                'stats' => [
                    'summary' => $summary,
                    'latest' => $latestSummary,
                    'history' => $historyRows->values(),
                    'club_performance' => $clubPerformance,
                ],
            ],
        ]);
    }

    private function buildPlayerStatsPayload(int $playerId, object $player, ?object $clubInternalPlayer = null, ?string $sport = null): array
    {
        $sport = $sport ?? $this->normalizePublicProfileSport($player->sport ?? null);
        $statsRows = collect();
        if (Schema::hasTable('player_statistics')) {
            $statsColumns = $this->existingTableColumns('player_statistics', [
                'season',
                'league',
                'matches_played',
                'matches_started',
                'matches_benched',
                'goals',
                'assists',
                'shot_2_made',
                'shot_3_made',
                'free_throw_made',
                'free_throw_attempt',
                'steals',
                'turnovers',
                'off_rebounds',
                'def_rebounds',
                'minutes_played',
                'avg_rating',
            ]);

            if ($statsColumns !== []) {
                $statsRows = DB::table('player_statistics')
                    ->where('user_id', $playerId)
                    ->orderByDesc('season')
                    ->orderByDesc('id')
                    ->get($statsColumns);
            }
        }

        $latestRow = $statsRows->first();
        $fallbackScoutRating = null;
        if (Schema::hasTable('scout_player_reports')) {
            $fallbackScoutRating = DB::table('scout_player_reports')
                ->where('player_user_id', $playerId)
                ->orderByDesc('id')
                ->value('rating');
        }

        $summary = $this->buildPublicProfileSummary(
            $statsRows,
            $sport,
            $latestRow?->avg_rating !== null
                ? (float) $latestRow->avg_rating
                : (($player->user_rating ?? $player->rating ?? null) !== null
                    ? (float) ($player->user_rating ?? $player->rating)
                    : (is_numeric((string) $fallbackScoutRating) ? (float) $fallbackScoutRating : 0.0))
        );

        $clubSummary = $this->buildClubInternalSummaryForPublicProfile($clubInternalPlayer, $sport);
        $useClubInternalStats = $clubInternalPlayer !== null && (($clubSummary['matches'] ?? 0) > 0);

        $latest = $latestRow
            ? [
                'season' => $latestRow->season,
                'league' => $latestRow->league,
                'matches_played' => (int) ($latestRow->matches_played ?? 0),
                'minutes_played' => (int) ($latestRow->minutes_played ?? 0),
                'goals' => (int) ($latestRow->goals ?? 0),
                'assists' => (int) ($latestRow->assists ?? 0),
                'rating' => $latestRow->avg_rating !== null ? (float) $latestRow->avg_rating : (float) ($summary['rating'] ?? 0),
            ]
            : ($useClubInternalStats
                ? $this->buildClubInternalLatestForPublicProfile($clubInternalPlayer, $clubSummary, $sport)
                : null);

        $history = $statsRows->isNotEmpty()
            ? $statsRows->map(fn ($row) => [
                'season' => $row->season,
                'league' => $row->league,
                'matches_played' => (int) ($row->matches_played ?? 0),
                'matches_started' => (int) ($row->matches_started ?? 0),
                'matches_benched' => (int) ($row->matches_benched ?? 0),
                'minutes_played' => (int) ($row->minutes_played ?? 0),
                'goals' => (int) ($row->goals ?? 0),
                'assists' => (int) ($row->assists ?? 0),
                'avg_rating' => $row->avg_rating !== null ? (float) $row->avg_rating : null,
            ])->values()->all()
            : [];

        $clubPerformance = $useClubInternalStats ? [
            'summary' => $clubSummary,
            'latest' => $this->buildClubInternalLatestForPublicProfile($clubInternalPlayer, $clubSummary, $sport),
            'history' => $this->buildClubInternalHistoryForPublicProfile($clubInternalPlayer, $sport),
        ] : null;

        return [
            'summary' => $summary,
            'latest' => $latest,
            'history' => $history,
            'club_performance' => $clubPerformance,
        ];
    }

    private function resolvePlayerClubCount(int $playerId, object $player): int
    {
        if (Schema::hasTable('player_career_timeline')) {
            return (int) DB::table('player_career_timeline')
                ->where('player_id', $playerId)
                ->distinct('club_id')
                ->count('club_id');
        }

        return ! empty($player->current_team ?? null) ? 1 : 0;
    }

    private function buildShowcaseStatus(int $playerId, object $player): array
    {
        $recentThreshold = now()->subDays(15);

        $profileChecks = [
            'photo' => ! empty($player->photo_url ?? null),
            'position' => ! empty($player->position ?? null) || ! empty($player->user_position ?? null),
            'city' => ! empty($player->city ?? null),
            'bio' => ! empty($player->bio ?? null),
            'age' => ! empty($player->age ?? null) || ! empty($player->birth_year ?? null),
            'team' => ! empty($player->current_team ?? null),
            'height' => ! empty($player->height_cm ?? null),
        ];

        $profileReady = $profileChecks['photo']
            && $profileChecks['position']
            && $profileChecks['city']
            && $profileChecks['bio']
            && $profileChecks['age'];

        $recentSignals = 0;
        if (! empty($player->updated_at ?? null) && Carbon::parse((string) $player->updated_at)->gte($recentThreshold)) {
            $recentSignals++;
        }

        if (Schema::hasColumn('users', 'last_login_at')) {
            $lastLoginAt = DB::table('users')->where('id', $playerId)->value('last_login_at');
            if ($lastLoginAt && Carbon::parse((string) $lastLoginAt)->gte($recentThreshold)) {
                $recentSignals++;
            }
        }

        $recentSignals += $this->recentCountIfTableHasColumns('media', ['user_id', 'created_at'], fn ($query) => $query
            ->where('user_id', $playerId)
            ->where('created_at', '>=', $recentThreshold)
        ) > 0 ? 1 : 0;

        $recentSignals += $this->recentCountIfTableHasColumns('applications', ['player_user_id', 'updated_at'], fn ($query) => $query
            ->where('player_user_id', $playerId)
            ->where('updated_at', '>=', $recentThreshold)
        ) > 0 ? 1 : 0;

        $recentSignals += $this->recentCountIfTableHasColumns('profile_reviews', ['target_user_id', 'created_at'], fn ($query) => $query
            ->where('target_user_id', $playerId)
            ->where('created_at', '>=', $recentThreshold)
        ) > 0 ? 1 : 0;

        $recentSignals += $this->recentCountIfTableHasColumns('contacts', ['from_user_id', 'to_user_id', 'updated_at'], fn ($query) => $query
            ->where(function ($builder) use ($playerId) {
                $builder->where('from_user_id', $playerId)->orWhere('to_user_id', $playerId);
            })
            ->where('updated_at', '>=', $recentThreshold)
        ) > 0 ? 1 : 0;

        $recentSignals += $this->recentCountIfTableHasColumns('player_statistics', ['user_id', 'updated_at'], fn ($query) => $query
            ->where('user_id', $playerId)
            ->where('updated_at', '>=', $recentThreshold)
        ) > 0 ? 1 : 0;

        $isActive = $recentSignals > 0;
        $isFeatured = $isActive && $profileReady;

        $badges = [];
        if ($isActive) {
            $badges[] = 'Aktif';
        }
        if ($profileReady) {
            $badges[] = 'Vitrine Hazir';
        }
        if ($isFeatured) {
            $badges[] = 'One Cikan';
        }

        $currentLevel = $isFeatured ? 'one_cikan' : ($profileReady ? 'vitrine_hazir' : ($isActive ? 'aktif' : 'temel'));

        return [
            'current_level' => $currentLevel,
            'badges' => $badges,
            'is_active' => $isActive,
            'is_profile_ready' => $profileReady,
            'is_featured' => $isFeatured,
            'profile_strength_score' => (int) round((collect($profileChecks)->filter()->count() / count($profileChecks)) * 100),
            'recent_signal_count' => $recentSignals,
        ];
    }

    private function recentCountIfTableExists(string $table, callable $callback): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $callback($query);

        return (int) $query->count();
    }

    private function recentCountIfTableHasColumns(string $table, array $columns, callable $callback): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return 0;
            }
        }

        $query = DB::table($table);
        $callback($query);

        return (int) $query->count();
    }


    private function existingTableColumns(string $table, array $columns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn($table, $column)));
    }

    public function shareAssets(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'player') {
            return response()->json([
                'ok' => false,
                'message' => 'Sadece oyuncu hesaplari bu islemi kullanabilir.',
            ], Response::HTTP_FORBIDDEN);
        }

        $profile = DB::table('users')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'users.id')
            ->where('users.id', $user->id)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city',
                'users.rating',
                'users.position as user_position',
                'pp.position',
                'pp.height_cm',
                'pp.current_team',
                'pp.bio',
            ])
            ->first();

        $stats = DB::table('player_statistics')
            ->where('user_id', $user->id)
            ->orderByDesc('season')
            ->first();

        $contract = DB::table('contracts')
            ->leftJoin('users as clubs', 'clubs.id', '=', 'contracts.club_id')
            ->where('contracts.player_id', $user->id)
            ->orderByRaw("case when contracts.status = 'active' then 0 else 1 end")
            ->orderByDesc('contracts.updated_at')
            ->select([
                'contracts.status',
                'clubs.name as club_name',
            ])
            ->first();

        $position = (string) ($profile->position ?? $profile->user_position ?? 'Oyuncu');
        $height = $profile->height_cm ? ((string) $profile->height_cm.'cm') : '-';
        $matches = (int) ($stats->matches_played ?? 0);
        $goals = (int) ($stats->goals ?? 0);
        $assists = (int) ($stats->assists ?? 0);
        $rating = number_format((float) ($stats->avg_rating ?? $profile->rating ?? 0), 1);
        $bio = trim((string) ($profile->bio ?? ''));
        $profileUrl = rtrim(config('app.url'), '/').'/api/public/players/'.$user->id.'/profile';
        $contractLine = trim((string) ($contract->club_name ?? '-')).' / '.trim((string) ($contract->status ?? '-'));

        return response()->json([
            'ok' => true,
            'data' => [
                'profile_url' => $profileUrl,
                'share_summary' => $position.' oyuncu profili | Puan '.$rating.' | '
                    .$matches.' mac, '.$goals.' gol, '.$assists.' asist | '.$profileUrl,
                'scout_summary' => 'SCOUT SUNUMU'."\n"
                    .$position.' | '.$matches.' mac | '.$goals.' gol | puan '.$rating."\n"
                    .($bio !== '' ? $bio : 'Oyuncu biyografisi eklenmemis.'),
                'club_summary' => 'Kulup incelemesi icin oyuncu ozeti: '.$position.', '.$height.', '
                    .$matches.' mac, '.$goals.' gol. Profil: '.$profileUrl,
                'pdf_full' => 'OYUNCU PROFILI'."\n"
                    .'Pozisyon: '.$position."\n"
                    .'Boy: '.$height."\n"
                    .'Mac: '.$matches."\n"
                    .'Gol/Asist: '.$goals.'/'.$assists."\n"
                    .'Puan: '.$rating."\n"
                    .'Sozlesme: '.$contractLine,
                'pdf_scout' => 'SCOUT SUNUMU'."\n"
                    .$position.' | '.$matches.' mac | '.$goals.' gol | puan '.$rating."\n"
                    .($bio !== '' ? $bio : 'Oyuncu biyografisi eklenmemis.'),
                'pdf_club' => 'KULUP PAKETI'."\n"
                    .'Oyuncu: '.$position."\n"
                    .'Verim: '.$goals.' gol, '.$assists.' asist'."\n"
                    .'Kulup/Sozlesme: '.$contractLine,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['full', 'scout', 'club'])],
        ]);

        $assetsResponse = $this->shareAssets($request);
        $payload = $assetsResponse->getData(true);
        $data = $payload['data'] ?? [];
        $type = $validated['type'] ?? 'full';

        $content = match ($type) {
            'scout' => (string) ($data['pdf_scout'] ?? ''),
            'club' => (string) ($data['pdf_club'] ?? ''),
            default => (string) ($data['pdf_full'] ?? ''),
        };

        $filename = 'nextscout-player-'.$type.'-'.$request->user()->id.'.pdf';
        $binary = $this->buildSimplePdf($content, (string) ($request->user()->name ?? 'Player Export'));

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => strlen($binary),
        ]);
    }

    private function buildSimplePdf(string $content, string $title): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));
        if ($lines === []) {
            $lines = ['NextScout PDF Export'];
        }

        $textLines = [];
        $y = 780;
        foreach ($lines as $index => $line) {
            $safe = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $line);
            $fontSize = $index === 0 ? 16 : 12;
            $textLines[] = "BT /F1 {$fontSize} Tf 50 {$y} Td ({$safe}) Tj ET";
            $y -= $index === 0 ? 28 : 20;
            if ($y < 60) {
                break;
            }
        }

        $stream = implode("\n", $textLines);
        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";
        $objects[] = "5 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream\nendobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($offsets))."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $safeTitle = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $title);
        $pdf .= "trailer\n<< /Size ".count($offsets)." /Root 1 0 R /Info << /Title ({$safeTitle}) >> >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function buildTalentMetrics(array $summary, string $sport): array
    {
        $matches = (int) ($summary['matches'] ?? 0);
        $goals = (int) ($summary['goals'] ?? 0);
        $assists = (int) ($summary['assists'] ?? 0);
        $minutes = (int) ($summary['minutes'] ?? 0);
        $rating = (float) ($summary['rating'] ?? 0);
        $primaryStat = (int) ($summary['primary_stat_value'] ?? $goals);
        $steals = (int) ($summary['steals'] ?? 0);
        $turnovers = (int) ($summary['turnovers'] ?? 0);
        $rebounds = (int) ($summary['rebounds'] ?? 0);
        $blocks = (int) ($summary['blocks'] ?? 0);
        $aces = (int) ($summary['aces'] ?? 0);
        $serviceErrors = (int) ($summary['service_errors'] ?? 0);
        $yellowCards = (int) ($summary['yellow_cards'] ?? 0);
        $redCards = (int) ($summary['red_cards'] ?? 0);

        $minutesPerMatch = $matches > 0 ? $minutes / $matches : 0.0;
        $goalRate = $matches > 0 ? $goals / $matches : 0.0;
        $assistRate = $matches > 0 ? $assists / $matches : 0.0;
        $involvementRate = $matches > 0 ? ($goals + $assists) / $matches : 0.0;
        $ratingMomentum = max(-12.0, min(12.0, ($rating - 7.0) * 6));
        $minutesRatio = max(0.0, min(1.0, $minutesPerMatch / 90));
        $minutesBlocks = max(0.0, min(40.0, $minutes / 90));

        if ($sport === 'basketbol') {
            $pointRate = $matches > 0 ? $primaryStat / $matches : 0.0;
            $reboundRate = $matches > 0 ? $rebounds / $matches : 0.0;
            $stealRate = $matches > 0 ? $steals / $matches : 0.0;
            $turnoverPenalty = $matches > 0 ? $turnovers / $matches : 0.0;

            return [
                [
                    'label' => 'Skor Uretimi',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(38, [
                            $rating * 2.4,
                            $ratingMomentum,
                            $pointRate * 3.2,
                            $minutesRatio * 14,
                        ])
                    ),
                ],
                [
                    'label' => 'Oyun Kurulum',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(36, [
                            $rating * 2.0,
                            $assistRate * 34,
                            $stealRate * 18,
                            $minutesRatio * 12,
                        ])
                    ),
                ],
                [
                    'label' => 'Savunma Etkisi',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(35, [
                            $rating * 1.8,
                            $reboundRate * 24,
                            $stealRate * 22,
                            ($turnoverPenalty * -10),
                            $minutesRatio * 12,
                        ])
                    ),
                ],
                [
                    'label' => 'Mac Etkisi',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(34, [
                            $matches * 0.8,
                            $pointRate * 1.8,
                            $assistRate * 12,
                            $rating * 1.9,
                            $minutesBlocks * 0.5,
                        ])
                    ),
                ],
            ];
        }

        if ($sport === 'voleybol') {
            $pointRate = $matches > 0 ? $primaryStat / $matches : 0.0;
            $blockRate = $matches > 0 ? $blocks / $matches : 0.0;
            $aceRate = $matches > 0 ? $aces / $matches : 0.0;
            $serviceErrorPenalty = $matches > 0 ? $serviceErrors / $matches : 0.0;

            return [
                [
                    'label' => 'Hucum Etkisi',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(38, [
                            $rating * 2.3,
                            $pointRate * 3.0,
                            $aceRate * 18,
                            $minutesRatio * 14,
                        ])
                    ),
                ],
                [
                    'label' => 'Oyun Kurulum',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(36, [
                            $rating * 2.0,
                            $assistRate * 34,
                            $pointRate * 0.8,
                            $minutesRatio * 12,
                        ])
                    ),
                ],
                [
                    'label' => 'Blok-Savunma',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(35, [
                            $rating * 1.9,
                            $blockRate * 28,
                            ($serviceErrorPenalty * -10),
                            $minutesRatio * 12,
                        ])
                    ),
                ],
                [
                    'label' => 'Mac Etkisi',
                    'value' => $this->normalizeTalentMetric(
                        $this->metricScore(34, [
                            $matches * 0.8,
                            $pointRate * 1.8,
                            $blockRate * 12,
                            $rating * 1.8,
                            $minutesBlocks * 0.5,
                        ])
                    ),
                ],
            ];
        }

        return [
            [
                'label' => 'Topla Oyun',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(40, [
                        $rating * 2.8,
                        $ratingMomentum,
                        $assistRate * 18,
                        $involvementRate * 8,
                        $minutesRatio * 16,
                    ])
                ),
            ],
            [
                'label' => 'Bitiricilik',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(38, [
                        $rating * 2.0,
                        $ratingMomentum,
                        $goalRate * 34,
                        $goals * 1.2,
                        ($yellowCards * -1.2),
                        ($redCards * -3.0),
                        $minutesRatio * 10,
                    ])
                ),
            ],
            [
                'label' => 'Oyun Kurulum',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(38, [
                        $rating * 2.2,
                        $ratingMomentum,
                        $assistRate * 36,
                        $involvementRate * 10,
                        $minutesRatio * 12,
                    ])
                ),
            ],
            [
                'label' => 'Mac Etkisi',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(35, [
                        $matches * 0.9,
                        $minutesBlocks * 0.6,
                        $involvementRate * 12,
                        $rating * 1.6,
                        $ratingMomentum,
                    ])
                ),
            ],
        ];
    }

    private function metricScore(float $base, array $factors): float
    {
        $total = $base + array_sum($factors);
        if ($total < 0) {
            return 0.0;
        }
        if ($total > 95) {
            return 95.0;
        }

        return $total;
    }

    private function normalizeTalentMetric(float $raw): float
    {
        $normalized = $raw / 100;
        if ($normalized < 0.45) {
            return 0.45;
        }
        if ($normalized > 0.95) {
            return 0.95;
        }

        return round($normalized, 4);
    }

    private function normalizePublicProfileSport(mixed $value): string
    {
        $normalized = Str::of((string) ($value ?? ''))
            ->trim()
            ->lower()
            ->ascii()
            ->value();

        return match ($normalized) {
            'basket', 'basketball', 'basketbol' => 'basketbol',
            'volleyball', 'voleybol', 'voleyball' => 'voleybol',
            default => 'futbol',
        };
    }

    private function buildPublicProfileSummary($statsRows, string $sport, float $rating): array
    {
        $matches = (int) $statsRows->sum('matches_played');
        $assists = (int) $statsRows->sum('assists');
        $minutes = (int) $statsRows->sum('minutes_played');
        $goals = (int) $statsRows->sum('goals');
        $steals = (int) $statsRows->sum('steals');
        $turnovers = (int) $statsRows->sum('turnovers');
        $offRebounds = (int) $statsRows->sum('off_rebounds');
        $defRebounds = (int) $statsRows->sum('def_rebounds');
        $shot2Made = (int) $statsRows->sum('shot_2_made');
        $shot3Made = (int) $statsRows->sum('shot_3_made');
        $freeThrowMade = (int) $statsRows->sum('free_throw_made');
        $freeThrowAttempt = (int) $statsRows->sum('free_throw_attempt');

        $summary = [
            'sport' => $sport,
            'matches' => $matches,
            'goals' => $goals,
            'assists' => $assists,
            'minutes' => $minutes,
            'rating' => $rating,
            'primary_stat_label' => 'Gol',
            'primary_stat_value' => $goals,
            'steals' => $steals,
            'turnovers' => $turnovers,
            'rebounds' => $offRebounds + $defRebounds,
            'blocks' => 0,
            'aces' => 0,
            'service_errors' => 0,
            'yellow_cards' => $offRebounds,
            'red_cards' => $defRebounds,
            'secondary_stats' => [],
        ];

        if ($sport === 'basketbol') {
            $points = ($shot2Made * 2) + ($shot3Made * 3) + $freeThrowMade;
            $summary['goals'] = $points;
            $summary['primary_stat_label'] = 'Sayi';
            $summary['primary_stat_value'] = $points;
            $summary['rebounds'] = $offRebounds + $defRebounds;
            $summary['yellow_cards'] = 0;
            $summary['red_cards'] = 0;
            $summary['secondary_stats'] = array_values(array_filter([
                $this->publicSummaryStat('Ribaund', $offRebounds + $defRebounds),
                $this->publicSummaryStat('Top Calma', $steals),
                $this->publicSummaryStat('Top Kaybi', $turnovers),
            ]));

            return $summary;
        }

        if ($sport === 'voleybol') {
            $points = (int) $statsRows->sum('shot_3_attempt');
            $blocks = $defRebounds;
            $aces = $freeThrowMade;
            $serviceErrors = $freeThrowAttempt;

            $summary['goals'] = $points;
            $summary['primary_stat_label'] = 'Sayi';
            $summary['primary_stat_value'] = $points;
            $summary['blocks'] = $blocks;
            $summary['aces'] = $aces;
            $summary['service_errors'] = $serviceErrors;
            $summary['yellow_cards'] = 0;
            $summary['red_cards'] = 0;
            $summary['secondary_stats'] = array_values(array_filter([
                $this->publicSummaryStat('Blok', $blocks),
                $this->publicSummaryStat('Ace', $aces),
                $this->publicSummaryStat('Servis Hata', $serviceErrors),
            ]));

            return $summary;
        }

        $summary['secondary_stats'] = array_values(array_filter([
            $this->publicSummaryStat('Top Kapma', $steals),
            $this->publicSummaryStat('Sari Kart', $offRebounds),
            $this->publicSummaryStat('Kirmizi Kart', $defRebounds),
        ]));

        return $summary;
    }

    private function resolveClubInternalPlayerForUser(object $player): ?object
    {
        if (! Schema::hasTable('club_internal_players')) {
            return null;
        }

        $baseQuery = DB::table('club_internal_players as cip')
            ->join('users as clubs', 'clubs.id', '=', 'cip.club_user_id')
            ->leftJoin('team_profiles as tp', 'tp.user_id', '=', 'clubs.id')
            ->select([
                'cip.id',
                'cip.club_user_id',
                'cip.group_key',
                'cip.name',
                'cip.sport',
                'cip.birth_year',
                'cip.position',
                'cip.height',
                'cip.shirt_number',
                'cip.photo_url',
                'cip.bio',
                'cip.dominant_foot',
                'cip.matches',
                'cip.minutes',
                'cip.goals',
                'cip.assists',
                'cip.rating',
                'cip.aggregate_highlights',
                'cip.last_match_highlights',
                'cip.last_match_rating',
                'cip.last_match_summary',
                'cip.last_match_date',
                'cip.performance_history',
                'clubs.name as club_user_name',
                'tp.team_name as club_team_name',
            ]);

        if (Schema::hasTable('player_statistics')) {
            $statsRows = DB::table('player_statistics')
                ->where('user_id', (int) $player->id)
                ->orderByDesc('id')
                ->get(['metadata']);
            foreach ($statsRows as $statsRow) {
                $metadata = is_array($statsRow->metadata ?? null)
                    ? $statsRow->metadata
                    : json_decode((string) ($statsRow->metadata ?? ''), true);
                $clubInternalPlayerId = is_array($metadata)
                    ? (int) ($metadata['club_internal_player_id'] ?? 0)
                    : 0;
                if ($clubInternalPlayerId <= 0) {
                    continue;
                }
                $linkedPlayer = (clone $baseQuery)
                    ->where('cip.id', $clubInternalPlayerId)
                    ->first();
                if ($linkedPlayer) {
                    $linkedPlayer->current_team = trim((string) ($linkedPlayer->club_team_name ?: $linkedPlayer->club_user_name ?: $linkedPlayer->group_key));
                    return $linkedPlayer;
                }
            }
        }

        $normalizedName = $this->normalizeCompactLookupValue((string) ($player->name ?? ''));
        $normalizedTeam = $this->normalizeCompactLookupValue((string) ($player->current_team ?? ''));
        if ($normalizedName === '') {
            return null;
        }

        $rows = $baseQuery
            ->whereRaw("LOWER(REPLACE(TRIM(cip.name), ' ', '')) = ?", [$normalizedName])
            ->get();

        if ($rows->isEmpty()) {
            $nameParts = array_values(array_filter(explode(' ', (string) ($player->name ?? ''))));
            $fallbackQuery = clone $baseQuery;
            if ($nameParts !== []) {
                $fallbackQuery->where(function ($query) use ($nameParts): void {
                    foreach ($nameParts as $part) {
                        $query->orWhere('cip.name', 'like', '%'.$part.'%');
                    }
                });
            }
            $rows = $fallbackQuery->get();
        }

        foreach ($rows as $row) {
            if ($this->normalizeCompactLookupValue((string) ($row->name ?? '')) !== $normalizedName) {
                continue;
            }

            $candidateTeams = [
                $row->club_team_name ?? '',
                $row->club_user_name ?? '',
                $row->group_key ?? '',
            ];
            if ($normalizedTeam === '') {
                $row->current_team = trim((string) ($row->club_team_name ?: $row->club_user_name ?: $row->group_key));
                return $row;
            }
            foreach ($candidateTeams as $candidateTeam) {
                if ($this->normalizeCompactLookupValue((string) $candidateTeam) === $normalizedTeam) {
                    $row->current_team = trim((string) ($row->club_team_name ?: $row->club_user_name ?: $row->group_key));
                    return $row;
                }
            }
        }

        return null;
    }

    private function buildClubInternalSummaryForPublicProfile(?object $player, string $sport): array
    {
        if (! $player) {
            return $this->buildPublicProfileSummary(collect(), $sport, 0.0);
        }

        $history = $this->decodeClubHistory($player->performance_history ?? null);
        $aggregateHighlights = $this->decodeMapList($player->aggregate_highlights ?? null);
        $ratingRows = $this->clubInternalRatingRows($player);

        if ($history === [] && $ratingRows->isNotEmpty()) {
            $summary = $this->buildClubSummaryFromRatingRows($ratingRows, $sport);
            $summary = $this->applyAggregateHighlightsToSummary($summary, $aggregateHighlights, $sport);
            return $this->applyClubInternalManualTotals($summary, $player, $sport);
        }

        if ($history === []) {
            $matches = max(0, (int) $this->numericValue($player->matches ?? 0));
            $minutes = max(0, (int) $this->numericValue($player->minutes ?? 0));
            $primary = max(0, (int) $this->numericValue($player->goals ?? 0));
            $assists = max(0, (int) $this->numericValue($player->assists ?? 0));
            $rating = max(0.0, (float) $this->numericValue($player->rating ?? 0));

            $summary = $this->buildPublicProfileSummary(collect(), $sport, $rating);
            $summary['matches'] = $matches;
            $summary['minutes'] = $minutes;
            $summary['assists'] = $assists;
            $summary['rating'] = $rating;
            $summary['goals'] = $primary;
            $summary['primary_stat_value'] = $primary;
            $summary['primary_stat_label'] = $sport === 'futbol' ? 'Gol' : 'Sayi';
            $summary = $this->applyAggregateHighlightsToSummary($summary, $aggregateHighlights, $sport);
            return $this->applyClubInternalManualTotals($summary, $player, $sport);
        }

        $matches = count($history);
        $minutes = 0;
        $assists = 0;
        $ratings = [];
        $goals = 0;
        $steals = 0;
        $turnovers = 0;
        $rebounds = 0;
        $blocks = 0;
        $receptions = 0;
        $aces = 0;
        $serviceErrors = 0;

        foreach ($history as $item) {
            $summaryMap = is_array($item['summary_map'] ?? null) ? $item['summary_map'] : [];
            $minutes += (int) $this->numericValue($item['minutes'] ?? $summaryMap['minutes'] ?? 0);
            $assists += (int) $this->numericValue($item['assists'] ?? $summaryMap['assists'] ?? 0);
            $ratingValue = $this->numericValue($item['rating'] ?? null);
            if ($ratingValue > 0) {
                $ratings[] = (float) $ratingValue;
            }

            if ($sport === 'basketbol') {
                $twoPt = (int) $this->numericValue($summaryMap['two_pt_made'] ?? 0);
                $threePt = (int) $this->numericValue($summaryMap['three_pt_made'] ?? 0);
                $ftMade = (int) $this->numericValue(($summaryMap['ft_made'] ?? 0) + ($summaryMap['ft_made_alt'] ?? 0));
                $goals += ($twoPt * 2) + ($threePt * 3) + $ftMade;
                $steals += (int) $this->numericValue($summaryMap['steals'] ?? 0);
                $turnovers += (int) $this->numericValue($summaryMap['turnovers'] ?? 0);
                $rebounds += (int) $this->numericValue($summaryMap['rebounds_offensive'] ?? 0)
                    + (int) $this->numericValue($summaryMap['rebounds_defensive'] ?? 0);
                $blocks += (int) $this->numericValue($summaryMap['blocks'] ?? 0);
                continue;
            }

            if ($sport === 'voleybol') {
                $goals += (int) $this->numericValue($summaryMap['points'] ?? $item['goals'] ?? 0);
                $blocks += (int) $this->numericValue($summaryMap['blocks'] ?? 0);
                $receptions += (int) $this->numericValue($summaryMap['receptions'] ?? 0);
                $aces += (int) $this->numericValue($summaryMap['aces'] ?? 0);
                $serviceErrors += (int) $this->numericValue($summaryMap['service_errors'] ?? 0);
                continue;
            }

            $goals += (int) $this->numericValue($item['goals'] ?? $summaryMap['goals'] ?? 0);
            $steals += (int) $this->numericValue($summaryMap['tackles'] ?? 0);
        }

        $rating = $ratings !== [] ? round(array_sum($ratings) / count($ratings), 2) : (float) $this->numericValue($player->rating ?? 0);
        $summary = $this->buildPublicProfileSummary(collect(), $sport, $rating);
        $summary['matches'] = $matches;
        $summary['minutes'] = $minutes;
        $summary['assists'] = $assists;
        $summary['rating'] = $rating;
        $summary['goals'] = $goals;
        $summary['primary_stat_label'] = $sport === 'futbol' ? 'Gol' : 'Sayi';
        $summary['primary_stat_value'] = $goals;
        $summary['steals'] = $steals;
        $summary['turnovers'] = $turnovers;
        $summary['rebounds'] = $rebounds;
        $summary['blocks'] = $blocks;
        $summary['receptions'] = $receptions;
        $summary['aces'] = $aces;
        $summary['service_errors'] = $serviceErrors;
        $summary['secondary_stats'] = match ($sport) {
            'basketbol' => array_values(array_filter([
                $this->publicSummaryStat('Ribaund', $rebounds),
                $this->publicSummaryStat('Top Calma', $steals),
                $this->publicSummaryStat('Top Kaybi', $turnovers),
                $this->publicSummaryStat('Blok', $blocks),
            ])),
            'voleybol' => array_values(array_filter([
                $this->publicSummaryStat('Blok', $blocks),
                $this->publicSummaryStat('Manset', $receptions),
                $this->publicSummaryStat('Ace', $aces),
                $this->publicSummaryStat('Servis Hata', $serviceErrors),
            ])),
            default => array_values(array_filter([
                $this->publicSummaryStat('Top Kapma', $steals),
            ])),
        };

        $summary = $this->applyAggregateHighlightsToSummary($summary, $aggregateHighlights, $sport);
        return $this->applyClubInternalManualTotals($summary, $player, $sport);
    }

    private function buildClubInternalLatestForPublicProfile(?object $player, array $summary, string $sport): ?array
    {
        if (! $player) {
            return null;
        }

        $history = $this->decodeClubHistory($player->performance_history ?? null);
        if ($history !== []) {
            $latest = $history[0];
            return [
                'season' => substr((string) ($latest['match_date'] ?? now()->toDateString()), 0, 4),
                'league' => (string) ($latest['match_name'] ?? 'Canli Mac'),
                'matches_played' => 1,
                'minutes_played' => (int) $this->numericValue($latest['minutes'] ?? 0),
                'goals' => (int) $this->numericValue($latest['goals'] ?? 0),
                'assists' => (int) $this->numericValue($latest['assists'] ?? 0),
                'rating' => (float) $this->numericValue($latest['rating'] ?? ($summary['rating'] ?? 0)),
                'summary' => (string) (($latest['summary'] ?? $latest['match_name'] ?? 'Canli Mac')),
                'match_date' => ! empty($latest['match_date']) ? (string) $latest['match_date'] : null,
            ];
        }

        $ratingRows = $this->clubInternalRatingRows($player);
        if ($ratingRows->isNotEmpty()) {
            $latest = $ratingRows->first();
            $summaryMap = is_array($latest->summary_json ?? null) ? $latest->summary_json : [];
            return [
                'season' => ! empty($latest->match_date) ? substr((string) $latest->match_date, 0, 4) : 'Kulup',
                'league' => (string) ($latest->match_title ?? 'Canli Mac'),
                'matches_played' => 1,
                'minutes_played' => (int) ($latest->minutes_played ?? 0),
                'goals' => $this->clubPrimaryFromRatingSummary($summaryMap, $sport),
                'assists' => (int) $this->numericValue($summaryMap['assists'] ?? 0),
                'rating' => (float) $this->numericValue($latest->final_rating ?? ($summary['rating'] ?? 0)),
                'summary' => (string) ($latest->match_title ?? 'Canli Mac'),
                'match_date' => ! empty($latest->match_date) ? (string) $latest->match_date : null,
            ];
        }

        $lastMatchRating = $this->numericValue($player->last_match_rating ?? 0);
        if ($lastMatchRating > 0 || ! empty($player->last_match_summary) || ! empty($player->last_match_date)) {
            return [
                'season' => ! empty($player->last_match_date) ? substr((string) $player->last_match_date, 0, 4) : 'Kulup',
                'league' => (string) ($player->last_match_summary ?: ($player->current_team ?? $player->group_key ?? 'Kulup')),
                'matches_played' => 1,
                'minutes_played' => 0,
                'goals' => 0,
                'assists' => 0,
                'rating' => $lastMatchRating > 0 ? $lastMatchRating : (float) ($summary['rating'] ?? 0),
                'summary' => (string) ($player->last_match_summary ?: ($player->current_team ?? $player->group_key ?? 'Kulup')),
                'match_date' => ! empty($player->last_match_date) ? (string) $player->last_match_date : null,
            ];
        }

        return ($summary['matches'] ?? 0) > 0 ? [
            'season' => 'Kulup',
            'league' => $player->current_team ?? $player->group_key ?? 'Kulup',
            'matches_played' => (int) ($summary['matches'] ?? 0),
            'minutes_played' => (int) ($summary['minutes'] ?? 0),
            'goals' => (int) ($summary['goals'] ?? 0),
            'assists' => (int) ($summary['assists'] ?? 0),
            'rating' => (float) ($summary['rating'] ?? 0),
            'summary' => $player->current_team ?? $player->group_key ?? 'Kulup',
            'match_date' => null,
        ] : null;
    }

    private function buildClubInternalHistoryForPublicProfile(?object $player, string $sport): array
    {
        if (! $player) {
            return [];
        }

        $history = $this->decodeClubHistory($player->performance_history ?? null);
        if ($history !== []) {
            return array_map(function (array $item) use ($sport): array {
                $summaryMap = is_array($item['summary_map'] ?? null) ? $item['summary_map'] : [];
                $primary = (int) $this->numericValue($item['goals'] ?? 0);
                if ($sport === 'basketbol') {
                    $primary = ((int) $this->numericValue($summaryMap['two_pt_made'] ?? 0) * 2)
                        + ((int) $this->numericValue($summaryMap['three_pt_made'] ?? 0) * 3)
                        + (int) $this->numericValue(($summaryMap['ft_made'] ?? 0) + ($summaryMap['ft_made_alt'] ?? 0));
                } elseif ($sport === 'voleybol') {
                    $primary = (int) $this->numericValue($summaryMap['points'] ?? $item['goals'] ?? 0);
                }

                return [
                    'season' => substr((string) ($item['match_date'] ?? now()->toDateString()), 0, 4),
                    'league' => (string) ($item['match_name'] ?? 'Canli Mac'),
                    'matches_played' => 1,
                    'matches_started' => 1,
                    'matches_benched' => 0,
                    'minutes_played' => (int) $this->numericValue($item['minutes'] ?? 0),
                    'goals' => $primary,
                    'assists' => (int) $this->numericValue($item['assists'] ?? 0),
                    'avg_rating' => (float) $this->numericValue($item['rating'] ?? 0),
                    'summary' => (string) (($item['summary'] ?? $item['match_name'] ?? 'Canli Mac')),
                    'match_date' => ! empty($item['match_date']) ? (string) $item['match_date'] : null,
                ];
            }, $history);
        }

        $ratingRows = $this->clubInternalRatingRows($player);
        if ($ratingRows->isNotEmpty()) {
            return $ratingRows->map(function ($row) use ($sport): array {
                $summaryMap = is_array($row->summary_json ?? null) ? $row->summary_json : [];
                return [
                    'season' => ! empty($row->match_date) ? substr((string) $row->match_date, 0, 4) : 'Kulup',
                    'league' => (string) ($row->match_title ?? 'Canli Mac'),
                    'matches_played' => 1,
                    'matches_started' => 1,
                    'matches_benched' => 0,
                    'minutes_played' => (int) ($row->minutes_played ?? 0),
                    'goals' => $this->clubPrimaryFromRatingSummary($summaryMap, $sport),
                    'assists' => (int) $this->numericValue($summaryMap['assists'] ?? 0),
                    'avg_rating' => (float) $this->numericValue($row->final_rating ?? 0),
                    'summary' => (string) ($row->match_title ?? 'Canli Mac'),
                    'match_date' => ! empty($row->match_date) ? (string) $row->match_date : null,
                ];
            })->values()->all();
        }
        return [];
    }

    private function decodeClubHistory(mixed $value): array
    {
        $rows = [];
        if (is_array($value)) {
            $rows = array_values(array_filter($value, fn ($item) => is_array($item)));
        }
        if ($rows === [] && is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $rows = array_values(array_filter($decoded, fn ($item) => is_array($item)));
            }
        }
        if ($rows === []) {
            return [];
        }

        $filtered = [];
        $seen = [];

        foreach ($rows as $item) {
            $matchDate = trim((string) ($item['match_date'] ?? $item['date'] ?? ''));
            $matchName = trim((string) ($item['match_name'] ?? $item['league'] ?? ''));
            $summary = trim((string) ($item['summary'] ?? ''));
            $minutes = (int) $this->numericValue($item['minutes'] ?? $item['minutes_played'] ?? 0);
            $goals = (int) $this->numericValue($item['goals'] ?? 0);
            $assists = (int) $this->numericValue($item['assists'] ?? 0);
            $rating = (float) $this->numericValue($item['rating'] ?? $item['avg_rating'] ?? 0);

            $isSyntheticLiveRow = $matchName === 'Canli Mac' && $matchDate !== '' && $summary === '';
            if ($isSyntheticLiveRow) {
                continue;
            }

            $identity = implode('|', [
                $matchDate !== '' ? $matchDate : 'no-date',
                $matchName !== '' ? $matchName : 'no-name',
                $summary !== '' ? $summary : 'no-summary',
                $minutes,
                $goals,
                $assists,
                number_format($rating, 2, '.', ''),
            ]);

            if (isset($seen[$identity])) {
                continue;
            }

            $seen[$identity] = true;
            $filtered[] = $item;
        }

        return array_values($filtered);
    }

    private function decodeMapList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => is_array($item)));
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => is_array($item)));
            }
        }
        return [];
    }

    private function applyAggregateHighlightsToSummary(array $summary, array $aggregateHighlights, string $sport): array
    {
        if ($aggregateHighlights === []) {
            return $summary;
        }

        $readValue = function (array $labels) use ($aggregateHighlights): ?int {
            foreach ($aggregateHighlights as $item) {
                $label = Str::of((string) ($item['label'] ?? ''))->trim()->lower()->value();
                foreach ($labels as $candidate) {
                    if ($label === Str::of($candidate)->trim()->lower()->value()) {
                        $value = $item['value'] ?? $item['count'] ?? $item['total'] ?? null;
                        if (is_numeric((string) $value)) {
                            return (int) $value;
                        }
                    }
                }
            }
            return null;
        };

        if ($sport === 'voleybol') {
            $blocks = $readValue(['Blok', 'Blocks']);
            $receptions = $readValue(['Manset', 'Reception', 'Receptions']);
            $aces = $readValue(['Servis Ace', 'Ace', 'Aces']);
            if ($blocks !== null) {
                $summary['blocks'] = $blocks;
            }
            if ($receptions !== null) {
                $summary['receptions'] = $receptions;
            }
            if ($aces !== null) {
                $summary['aces'] = $aces;
            }
            $summary['secondary_stats'] = array_values(array_filter([
                $this->publicSummaryStat('Blok', (int) ($summary['blocks'] ?? 0)),
                $this->publicSummaryStat('Manset', (int) ($summary['receptions'] ?? 0)),
                $this->publicSummaryStat('Ace', (int) ($summary['aces'] ?? 0)),
                $this->publicSummaryStat('Servis Hata', (int) ($summary['service_errors'] ?? 0)),
            ]));
        }

        return $summary;
    }

    private function numericValue(mixed $value): float
    {
        return is_numeric((string) $value) ? (float) $value : 0.0;
    }

    private function clubInternalRatingRows(object $player)
    {
        if (! Schema::hasTable('player_match_ratings')) {
            return collect();
        }

        return DB::table('player_match_ratings as pmr')
            ->leftJoin('live_matches as lm', 'lm.id', '=', 'pmr.live_match_id')
            ->where('pmr.club_internal_player_id', (int) $player->id)
            ->orderByDesc('pmr.id')
            ->get([
                'pmr.minutes_played',
                'pmr.final_rating',
                'pmr.summary_json',
                'pmr.created_at',
                'lm.title as match_title',
                'lm.match_date',
            ]);
    }

    private function buildClubSummaryFromRatingRows($rows, string $sport): array
    {
        $matches = $rows->count();
        $minutes = 0;
        $assists = 0;
        $primary = 0;
        $ratings = [];
        $steals = 0;
        $turnovers = 0;
        $rebounds = 0;
        $blocks = 0;
        $receptions = 0;
        $aces = 0;
        $serviceErrors = 0;
        $yellowCards = 0;
        $redCards = 0;

        foreach ($rows as $row) {
            $summary = is_array($row->summary_json ?? null) ? $row->summary_json : [];
            $minutes += (int) ($row->minutes_played ?? 0);
            $assists += (int) $this->numericValue($summary['assists'] ?? 0);
            $primary += $this->clubPrimaryFromRatingSummary($summary, $sport);
            $rating = $this->numericValue($row->final_rating ?? 0);
            if ($rating > 0) {
                $ratings[] = $rating;
            }

            if ($sport === 'basketbol') {
                $steals += (int) $this->numericValue($summary['steals'] ?? 0);
                $turnovers += (int) $this->numericValue($summary['turnovers'] ?? 0);
                $rebounds += (int) $this->numericValue($summary['def_reb'] ?? 0)
                    + (int) $this->numericValue($summary['off_reb'] ?? 0);
                continue;
            }

            if ($sport === 'voleybol') {
                $blocks += (int) $this->numericValue($summary['blocks'] ?? 0);
                $receptions += (int) $this->numericValue($summary['receptions'] ?? 0);
                $aces += (int) $this->numericValue($summary['aces'] ?? 0);
                $serviceErrors += (int) $this->numericValue($summary['service_errors'] ?? 0);
                $turnovers += (int) $this->numericValue($summary['turnovers'] ?? 0);
                continue;
            }

            $steals += (int) $this->numericValue($summary['tackles'] ?? 0);
            $yellowCards += (int) $this->numericValue($summary['yellow_cards'] ?? 0);
            $redCards += (int) $this->numericValue($summary['red_cards'] ?? 0);
        }

        $rating = $ratings !== [] ? round(array_sum($ratings) / count($ratings), 2) : 0.0;
        $summary = $this->buildPublicProfileSummary(collect(), $sport, $rating);
        $summary['matches'] = $matches;
        $summary['minutes'] = $minutes;
        $summary['assists'] = $assists;
        $summary['rating'] = $rating;
        $summary['goals'] = $primary;
        $summary['primary_stat_label'] = $sport === 'futbol' ? 'Gol' : 'Sayi';
        $summary['primary_stat_value'] = $primary;
        $summary['steals'] = $steals;
        $summary['turnovers'] = $turnovers;
        $summary['rebounds'] = $rebounds;
        $summary['blocks'] = $blocks;
        $summary['receptions'] = $receptions;
        $summary['aces'] = $aces;
        $summary['service_errors'] = $serviceErrors;
        $summary['yellow_cards'] = $yellowCards;
        $summary['red_cards'] = $redCards;
        $summary['secondary_stats'] = match ($sport) {
            'basketbol' => array_values(array_filter([
                $this->publicSummaryStat('Ribaund', $rebounds),
                $this->publicSummaryStat('Top Calma', $steals),
                $this->publicSummaryStat('Top Kaybi', $turnovers),
            ])),
            'voleybol' => array_values(array_filter([
                $this->publicSummaryStat('Blok', $blocks),
                $this->publicSummaryStat('Manset', $receptions),
                $this->publicSummaryStat('Ace', $aces),
                $this->publicSummaryStat('Servis Hata', $serviceErrors),
            ])),
            default => array_values(array_filter([
                $this->publicSummaryStat('Top Kapma', $steals),
                $this->publicSummaryStat('Sari Kart', $yellowCards),
                $this->publicSummaryStat('Kirmizi Kart', $redCards),
            ])),
        };

        return $summary;
    }

    private function applyClubInternalManualTotals(array $summary, object $player, string $sport): array
    {
        $manualMatches = max(0, (int) $this->numericValue($player->matches ?? 0));
        $manualMinutes = max(0, (int) $this->numericValue($player->minutes ?? 0));
        $manualPrimary = max(0, (int) $this->numericValue($player->goals ?? 0));
        $manualAssists = max(0, (int) $this->numericValue($player->assists ?? 0));
        $manualRating = max(0.0, (float) $this->numericValue($player->rating ?? 0));

        if ($manualMatches > ($summary['matches'] ?? 0)) {
            $summary['matches'] = $manualMatches;
        }
        if ($manualMinutes > ($summary['minutes'] ?? 0)) {
            $summary['minutes'] = $manualMinutes;
        }
        if ($manualAssists > ($summary['assists'] ?? 0)) {
            $summary['assists'] = $manualAssists;
        }
        if ($manualPrimary > ($summary['goals'] ?? 0)) {
            $summary['goals'] = $manualPrimary;
            $summary['primary_stat_value'] = $manualPrimary;
            $summary['primary_stat_label'] = $sport === 'futbol' ? 'Gol' : 'Sayi';
        }
        if ($manualRating > ($summary['rating'] ?? 0)) {
            $summary['rating'] = $manualRating;
        }

        return $summary;
    }

    private function clubPrimaryFromRatingSummary(array $summary, string $sport): int
    {
        return match ($sport) {
            'basketbol' => ((int) $this->numericValue($summary['two_pt_made'] ?? 0) * 2)
                + ((int) $this->numericValue($summary['three_pt_made'] ?? 0) * 3)
                + (int) $this->numericValue(($summary['ft_made'] ?? 0) + ($summary['ft_made_alt'] ?? 0)),
            'voleybol' => (int) $this->numericValue($summary['points'] ?? 0),
            default => (int) $this->numericValue($summary['goals'] ?? 0),
        };
    }

    private function normalizeCompactLookupValue(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->squish()
            ->replace(' ', '')
            ->ascii('tr')
            ->lower()
            ->value();
    }

    private function publicSummaryStat(string $label, int $value): ?array
    {
        if ($value <= 0) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $value,
        ];
    }
}
