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

        $authUser = $request->user();
        $isOwner = $authUser && (int) $authUser->id === (int) ($player->id ?? 0);
        $player = $this->redactPrivateFields($player, $this->isAdmin($authUser) || $isOwner);
        $showcase = $this->buildShowcaseStatus((int) $id, $player);

        return response()->json([
            'ok' => true,
            'data' => [
                ...((array) $player),
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

        $fallbackPhotoUrl = $player->photo_url ?? null;
        if (empty($fallbackPhotoUrl) && Schema::hasTable('media')) {
            $fallbackPhotoUrl = DB::table('media')
                ->where('user_id', $id)
                ->where('type', 'image')
                ->orderByDesc('id')
                ->value('url');
        }

        $statsRows = collect();
        if (Schema::hasTable('player_statistics')) {
            $statsRows = DB::table('player_statistics')
                ->where('user_id', $id)
                ->orderByDesc('season')
                ->orderByDesc('id')
                ->get([
                    'season',
                    'league',
                    'matches_played',
                    'matches_started',
                    'matches_benched',
                    'goals',
                    'assists',
                    'minutes_played',
                    'avg_rating',
                ]);
        }

        $latest = $statsRows->first();
        $fallbackScoutRating = null;
        if (Schema::hasTable('scout_player_reports')) {
            $fallbackScoutRating = DB::table('scout_player_reports')
                ->where('player_user_id', $id)
                ->orderByDesc('id')
                ->value('rating');
        }
        $summary = [
            'matches' => (int) $statsRows->sum('matches_played'),
            'goals' => (int) $statsRows->sum('goals'),
            'assists' => (int) $statsRows->sum('assists'),
            'minutes' => (int) $statsRows->sum('minutes_played'),
            'rating' => $latest?->avg_rating !== null
                ? (float) $latest->avg_rating
                : ($player->user_rating !== null
                    ? (float) $player->user_rating
                    : (is_numeric((string) $fallbackScoutRating) ? (float) $fallbackScoutRating : 0.0)),
        ];
        $talentMetrics = $this->buildTalentMetrics($summary);

        $position = $player->position ?: $player->user_position ?: 'Oyuncu';
        $age = $player->age;
        if (! $age && $player->birth_year) {
            $age = (int) now()->format('Y') - (int) $player->birth_year;
        }
        $verificationStatus = strtolower((string) ($player->verification_status ?? ''));
        $isVerified = (bool) ($player->is_verified ?? false)
            || $verificationStatus === 'verified'
            || ! empty($player->verified_at);
        $showcase = $this->buildShowcaseStatus((int) $id, $player);

        return response()->json([
            'ok' => true,
            'data' => [
                'user' => [
                    'id' => (int) $player->id,
                    'name' => (string) $player->name,
                    'sport' => (string) ($player->sport ?: 'futbol'),
                    'gender' => (string) ($player->gender ?: 'bay'),
                ],
                'profile' => [
                    'name' => (string) $player->name,
                    'sport' => (string) ($player->sport ?: 'futbol'),
                    'branch' => (string) ($player->sport ?: 'futbol'),
                    'gender' => (string) ($player->gender ?: 'bay'),
                    'position' => (string) $position,
                    'age' => $age !== null ? (int) $age : null,
                    'birth_year' => $player->birth_year ? (int) $player->birth_year : null,
                    'height_cm' => $player->height_cm ? (int) $player->height_cm : null,
                    'weight_kg' => $player->weight_kg ? (int) $player->weight_kg : null,
                    'current_club' => (string) ($player->current_team ?? '-'),
                    'club_name' => (string) ($player->current_team ?? '-'),
                    'bio' => (string) ($player->bio ?? ''),
                    'dominant_foot' => $player->dominant_foot,
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
                    'latest' => $latest ? [
                        'season' => $latest->season,
                        'league' => $latest->league,
                        'matches_played' => (int) ($latest->matches_played ?? 0),
                        'minutes_played' => (int) ($latest->minutes_played ?? 0),
                        'goals' => (int) ($latest->goals ?? 0),
                        'assists' => (int) ($latest->assists ?? 0),
                        'rating' => $latest->avg_rating !== null ? (float) $latest->avg_rating : $summary['rating'],
                    ] : null,
                    'history' => $statsRows->map(fn ($row) => [
                        'season' => $row->season,
                        'league' => $row->league,
                        'matches_played' => (int) ($row->matches_played ?? 0),
                        'matches_started' => (int) ($row->matches_started ?? 0),
                        'matches_benched' => (int) ($row->matches_benched ?? 0),
                        'minutes_played' => (int) ($row->minutes_played ?? 0),
                        'goals' => (int) ($row->goals ?? 0),
                        'assists' => (int) ($row->assists ?? 0),
                        'avg_rating' => $row->avg_rating !== null ? (float) $row->avg_rating : null,
                    ])->values(),
                ],
            ],
        ]);
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

    private function buildTalentMetrics(array $summary): array
    {
        $matches = (int) ($summary['matches'] ?? 0);
        $goals = (int) ($summary['goals'] ?? 0);
        $assists = (int) ($summary['assists'] ?? 0);
        $minutes = (int) ($summary['minutes'] ?? 0);
        $rating = (float) ($summary['rating'] ?? 0);

        $minutesPerMatch = $matches > 0 ? $minutes / $matches : 0.0;
        $goalRate = $matches > 0 ? $goals / $matches : 0.0;
        $assistRate = $matches > 0 ? $assists / $matches : 0.0;
        $involvementRate = $matches > 0 ? ($goals + $assists) / $matches : 0.0;
        $ratingMomentum = max(-12.0, min(12.0, ($rating - 7.0) * 6));
        $minutesRatio = max(0.0, min(1.0, $minutesPerMatch / 90));
        $minutesBlocks = max(0.0, min(40.0, $minutes / 90));

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
}
