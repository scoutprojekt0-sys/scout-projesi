<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DiscoveryController extends Controller
{
    use ApiResponds;

    public function publicPlayers(): JsonResponse
    {
        $search = request('search');
        $position = request('position');
        $city = request('city');

        $players = DB::table('users')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'users.id')
            ->where('role', 'player')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($position, fn($q) => $q->where(function ($inner) use ($position) {
                $inner->where('users.position', $position)
                    ->orWhere('pp.position', $position);
            }))
            ->when($city, fn($q) => $q->where('city', $city))
            ->select([
                'users.id',
                'users.name',
                'users.sport',
                DB::raw('COALESCE(pp.position, users.position) as position'),
                'users.city',
                'users.age',
                'users.photo_url',
                'pp.height_cm',
                'pp.current_team',
                DB::raw("'-' as league"),
                'users.created_at',
            ])
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.id')
            ->paginate(20);

        return $this->paginatedListResponse($players, 'Public oyuncu listesi hazir.');
    }

    public function contractsLive(): JsonResponse
    {
        $limit = max(1, min((int) request()->query('limit', 12), 50));
        $statuses = collect(explode(',', (string) request()->query('statuses', 'expired,suspended')))
            ->map(fn ($status) => trim(strtolower($status)))
            ->filter(fn ($status) => in_array($status, ['expired', 'suspended'], true))
            ->values();

        if ($statuses->isEmpty()) {
            $statuses = collect(['expired', 'suspended']);
        }

        $today = now()->toDateString();
        $schema = DB::getSchemaBuilder();
        $hasContractExpires = $schema->hasTable('player_profiles') && $schema->hasColumn('player_profiles', 'contract_expires');

        $rows = DB::table('users as u')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'u.id')
            ->where('u.role', 'player')
            ->select([
                'u.id as player_id',
                'u.name as player_name',
                'pp.position',
                DB::raw("COALESCE(pp.current_team, '-') as club_name"),
                DB::raw($hasContractExpires ? 'pp.contract_expires' : 'NULL as contract_expires'),
                DB::raw("EXISTS(
                    SELECT 1
                    FROM contracts c
                    WHERE c.player_id = u.id
                      AND c.status = 'terminated'
                ) as has_suspended_contract"),
            ])
            ->orderByDesc('u.updated_at')
            ->limit($limit)
            ->get();

        $items = $rows->map(function ($row) use ($today, $statuses) {
            $isExpired = ! empty($row->contract_expires) && substr((string) $row->contract_expires, 0, 10) < $today;
            $isSuspended = (int) ($row->has_suspended_contract ?? 0) === 1;

            $status = null;
            if ($isSuspended && $statuses->contains('suspended')) {
                $status = 'suspended';
            } elseif ($isExpired && $statuses->contains('expired')) {
                $status = 'expired';
            }

            if ($status === null) {
                return null;
            }

            return [
                'player_id' => (int) $row->player_id,
                'player_name' => (string) $row->player_name,
                'position' => (string) ($row->position ?? '-'),
                'club_name' => (string) ($row->club_name ?? '-'),
                'status' => $status,
                'contract_expires' => $row->contract_expires,
                'note' => $status === 'suspended'
                    ? 'Sozlesme sureci askida.'
                    : 'Sozlesmesi sona erdi.',
            ];
        })->filter()->values();

        return $this->successResponse($items, 'Canli sozlesme listesi hazir.', 200, [
            'meta' => [
                'statuses' => $statuses->all(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function playerOfWeek(): JsonResponse
    {
        // Get player with highest rating from last week
        $player = DB::table('users')
            ->where('role', 'player')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('rating', 'desc')
            ->first();

        return $this->successResponse($player ?: [], 'Haftanin oyuncusu hazir.');
    }

    public function trendingWeek(): JsonResponse
    {
        $trending = DB::table('users')
            ->where('role', 'player')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('views_count', 'desc')
            ->limit(10)
            ->get();

        return $this->successResponse($trending, 'Haftanin trend listesi hazir.');
    }

    public function risingStars(): JsonResponse
    {
        $stars = DB::table('users')
            ->where('role', 'player')
            ->whereNotNull('age')
            ->where('age', '<=', 21)
            ->orderBy('rating', 'desc')
            ->limit(10)
            ->get();

        return $this->successResponse($stars, 'Yukselen yildizlar hazir.');
    }

    public function clubNeeds(): JsonResponse
    {
        $needs = DB::table('opportunities')
            ->where('status', 'open')
            ->join('users as teams', 'opportunities.team_user_id', '=', 'teams.id')
            ->where('teams.role', 'team')
            ->select('opportunities.*', 'teams.name as team_name')
            ->orderBy('opportunities.created_at', 'desc')
            ->paginate(20);

        return $this->paginatedListResponse($needs, 'Kulup ihtiyaclari hazir.');
    }

    public function managerNeeds(): JsonResponse
    {
        $needs = DB::table('opportunities')
            ->where('status', 'open')
            ->join('users as teams', 'opportunities.team_user_id', '=', 'teams.id')
            ->where('teams.role', 'manager')
            ->select([
                'opportunities.*',
                'teams.name as manager_name',
                'teams.name as author_name',
                'opportunities.details as description',
            ])
            ->orderBy('opportunities.created_at', 'desc')
            ->paginate(20);

        return $this->paginatedListResponse($needs, 'Menajer ihtiyaclari hazir.');
    }

    public function weeklyDigest(): JsonResponse
    {
        $windowStart = now()->subDays(7);

        $weeklyPlayerCount = DB::table('users')
            ->where('role', 'player')
            ->where('created_at', '>=', $windowStart)
            ->count();

        $weeklyManagerNeeds = DB::table('opportunities')
            ->join('users as owner', 'opportunities.team_user_id', '=', 'owner.id')
            ->where('opportunities.status', 'open')
            ->where('owner.role', 'manager')
            ->where('opportunities.created_at', '>=', $windowStart)
            ->count();

        $weeklyCoachNeeds = DB::table('opportunities')
            ->join('users as owner', 'opportunities.team_user_id', '=', 'owner.id')
            ->where('opportunities.status', 'open')
            ->where('owner.role', 'coach')
            ->where('opportunities.created_at', '>=', $windowStart)
            ->count();

        $topViewedPlayers = DB::table('users')
            ->where('role', 'player')
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('views_count')
            ->limit(5)
            ->get(['id', 'name', 'views_count'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? 'Oyuncu'),
                'views' => (int) ($row->views_count ?? 0),
            ])
            ->values();

        return $this->successResponse([
            'weekly_player_count' => $weeklyPlayerCount,
            'weekly_manager_needs' => $weeklyManagerNeeds,
            'weekly_coach_needs' => $weeklyCoachNeeds,
            'top_viewed_players' => $topViewedPlayers,
        ], 'Haftalik bulten hazir.');
    }

    public function matchesForUser(): JsonResponse
    {
        $user = request()->user();
        if (! $user) {
            return $this->errorResponse('Yetkilendirme gerekli.', 401);
        }

        if ($user->role !== 'player') {
            return $this->successResponse([], 'Bu kart şu anda oyuncu hesapları için aktif.', 200, [
                'meta' => [
                    'count' => 0,
                    'role' => $user->role,
                ],
            ]);
        }

        $player = DB::table('users as u')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'u.id')
            ->where('u.id', $user->id)
            ->select([
                'u.id',
                'u.name',
                'u.role',
                'u.city',
                'u.country',
                'u.position',
                'u.age',
                'u.rating',
                'pp.position as profile_position',
                'pp.current_team',
            ])
            ->first();

        if (! $player) {
            return $this->successResponse([], 'Oyuncu profili bulunamadi.', 200, [
                'meta' => [
                    'count' => 0,
                    'role' => $user->role,
                ],
            ]);
        }

        $playerPosition = $this->normalizePosition($player->profile_position ?: $player->position);
        $playerAge = (int) ($player->age ?? 0);
        $playerCity = $this->normalizeText($player->city);
        $hasTeam = ! empty($player->current_team);

        $rows = DB::table('opportunities as o')
            ->join('users as owner', 'o.team_user_id', '=', 'owner.id')
            ->where('o.status', 'open')
            ->whereIn('owner.role', ['team', 'manager'])
            ->select([
                'o.id',
                'o.title',
                'o.position',
                'o.age_min',
                'o.age_max',
                'o.city',
                'o.details',
                'o.created_at',
                'owner.name as owner_name',
                'owner.role as owner_role',
            ])
            ->orderByDesc('o.created_at')
            ->limit(50)
            ->get();

        $matches = collect($rows)
            ->map(function ($row) use ($playerPosition, $playerAge, $playerCity, $hasTeam) {
                return $this->buildOpportunityMatch($row, $playerPosition, $playerAge, $playerCity, $hasTeam);
            })
            ->filter()
            ->sortByDesc('match_score')
            ->take(3)
            ->values();

        return $this->successResponse($matches, 'Sana uygun fırsatlar hazır.', 200, [
            'meta' => [
                'count' => $matches->count(),
                'role' => $user->role,
            ],
        ]);
    }

    public function newProfessionals(): JsonResponse
    {
        $scouts = DB::table('users')
            ->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'scout')
            ->orderByDesc('users.created_at')
            ->limit(2)
            ->get([
                'users.id',
                'users.name',
                'users.city',
                'users.created_at',
                DB::raw("COALESCE(staff_profiles.organization, 'Scout Ağı') as subtitle"),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? 'Scout'),
                'city' => (string) ($row->city ?? ''),
                'subtitle' => (string) ($row->subtitle ?? 'Scout Ağı'),
                'joined_at' => $row->created_at,
            ])->values();

        $managers = DB::table('users')
            ->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'manager')
            ->orderByDesc('users.created_at')
            ->limit(2)
            ->get([
                'users.id',
                'users.name',
                'users.city',
                'users.created_at',
                DB::raw("COALESCE(staff_profiles.organization, 'Menajer Portföyü') as subtitle"),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? 'Menajer'),
                'city' => (string) ($row->city ?? ''),
                'subtitle' => (string) ($row->subtitle ?? 'Menajer Portföyü'),
                'joined_at' => $row->created_at,
            ])->values();

        $clubs = DB::table('users')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->whereIn('users.role', ['team', 'club'])
            ->orderByDesc('users.created_at')
            ->limit(2)
            ->get([
                'users.id',
                'users.name',
                'users.city',
                'users.created_at',
                DB::raw("COALESCE(team_profiles.team_name, users.name, 'Kulüp') as display_name"),
                DB::raw("COALESCE(team_profiles.league_level, 'Kulüp Ağı') as subtitle"),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) ($row->display_name ?? 'Kulüp'),
                'city' => (string) ($row->city ?? ''),
                'subtitle' => (string) ($row->subtitle ?? 'Kulüp Ağı'),
                'joined_at' => $row->created_at,
            ])->values();

        $lawyers = DB::table('lawyers')
            ->leftJoin('users', 'users.id', '=', 'lawyers.user_id')
            ->where('lawyers.is_active', true)
            ->orderByDesc('lawyers.id')
            ->limit(2)
            ->get([
                'lawyers.id',
                'lawyers.office_name',
                'lawyers.specialization',
                'lawyers.created_at',
                'users.name',
                'users.city',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) ($row->office_name ?: $row->name ?: 'Hukuk Ofisi'),
                'city' => (string) ($row->city ?? ''),
                'subtitle' => (string) ($row->specialization ?? 'Spor Hukuku'),
                'joined_at' => $row->created_at,
            ])->values();

        return $this->successResponse([
            'scouts' => $scouts,
            'managers' => $managers,
            'clubs' => $clubs,
            'lawyers' => $lawyers,
        ], 'Platforma yeni katılan profesyoneller hazir.', 200, [
            'meta' => [
                'count' => $scouts->count() + $managers->count() + $clubs->count() + $lawyers->count(),
            ],
        ]);
    }

    public function publicTurkeyHeatmap(): JsonResponse
    {
        $schema = DB::getSchemaBuilder();
        $from = now()->startOfDay();
        $to = now()->copy()->addDays(7)->endOfDay();

        $playerRows = DB::table('users')
            ->where('role', 'player')
            ->select([
                'city',
                DB::raw('COUNT(*) as total_players'),
                DB::raw('SUM(CASE WHEN COALESCE(rating, 0) >= 80 THEN 1 ELSE 0 END) as high_rated_players'),
            ])
            ->groupBy('city')
            ->get();

        $scheduleRows = collect();
        if ($schema->hasTable('player_match_schedules')) {
            $scheduleRows = DB::table('player_match_schedules')
                ->where('is_public', true)
                ->whereBetween('match_date', [$from, $to])
                ->select([
                    'city',
                    DB::raw('COUNT(*) as live_matches'),
                    DB::raw('COUNT(DISTINCT COALESCE(venue, match_title)) as active_fields'),
                    DB::raw('COUNT(DISTINCT player_user_id) as tracked_talents'),
                    DB::raw('MAX(position) as top_position'),
                ])
                ->groupBy('city')
                ->get();
        }

        $cityMap = [];
        foreach ($playerRows as $row) {
            $key = $this->normalizeText((string) ($row->city ?? ''));
            if ($key === '') {
                continue;
            }
            $cityMap[$key] = [
                'city' => (string) $row->city,
                'total_players' => (int) ($row->total_players ?? 0),
                'high_rated_players' => (int) ($row->high_rated_players ?? 0),
                'live_matches' => 0,
                'active_fields' => 0,
                'tracked_talents' => 0,
                'top_position' => 'Veri bekleniyor',
            ];
        }

        foreach ($scheduleRows as $row) {
            $key = $this->normalizeText((string) ($row->city ?? ''));
            if ($key === '') {
                continue;
            }
            if (! isset($cityMap[$key])) {
                $cityMap[$key] = [
                    'city' => (string) $row->city,
                    'total_players' => 0,
                    'high_rated_players' => 0,
                    'live_matches' => 0,
                    'active_fields' => 0,
                    'tracked_talents' => 0,
                    'top_position' => 'Veri bekleniyor',
                ];
            }

            $cityMap[$key]['live_matches'] = (int) ($row->live_matches ?? 0);
            $cityMap[$key]['active_fields'] = (int) ($row->active_fields ?? 0);
            $cityMap[$key]['tracked_talents'] = (int) ($row->tracked_talents ?? 0);
            $cityMap[$key]['top_position'] = (string) ($row->top_position ?: $cityMap[$key]['top_position']);
        }

        $cities = collect($cityMap)
            ->map(function (array $item, string $key) {
                $intensity = min(100, max(
                    16,
                    ($item['high_rated_players'] * 14)
                    + ($item['live_matches'] * 10)
                    + ($item['tracked_talents'] * 8)
                ));

                return [
                    'key' => $key,
                    'city' => $item['city'],
                    'total_players' => $item['total_players'],
                    'high_rated_players' => $item['high_rated_players'],
                    'live_matches' => $item['live_matches'],
                    'active_fields' => $item['active_fields'],
                    'tracked_talents' => $item['tracked_talents'],
                    'top_position' => $item['top_position'],
                    'intensity' => $intensity,
                    'hover_text' => sprintf(
                        'Su an %d farkli sahada mac var ve %d yetenek takibimizde.',
                        $item['active_fields'],
                        $item['tracked_talents']
                    ),
                    'fixtures' => $this->buildCityFixturePreview($item['city'], $schema, $from, $to),
                ];
            })
            ->sortByDesc('intensity')
            ->values();

        $summary = [
            'city_count' => $cities->count(),
            'high_rated_players' => $cities->sum('high_rated_players'),
            'live_matches' => $cities->sum('live_matches'),
            'tracked_talents' => $cities->sum('tracked_talents'),
            'top_city' => $cities->first()['city'] ?? 'Istanbul',
        ];

        return $this->successResponse([
            'summary' => $summary,
            'cities' => $cities,
            'window_days' => 7,
        ], 'Turkiye isi haritasi hazir.');
    }

    private function buildCityFixturePreview(string $city, $schema, $from, $to): array
    {
        if (! $schema->hasTable('player_match_schedules')) {
            return [];
        }

        return DB::table('player_match_schedules')
            ->leftJoin('users', 'users.id', '=', 'player_match_schedules.player_user_id')
            ->where('is_public', true)
            ->whereBetween('match_date', [$from, $to])
            ->where('city', $city)
            ->orderBy('match_date')
            ->limit(4)
            ->get([
                'users.name as player_name',
                'match_title',
                'venue',
                'district',
                'position',
                'match_date',
            ])
            ->map(function ($row) {
                return [
                    'player_name' => (string) ($row->player_name ?? 'Oyuncu'),
                    'match_title' => (string) ($row->match_title ?? 'Açık maç'),
                    'venue' => (string) ($row->venue ?? 'Saha belirtilmedi'),
                    'district' => (string) ($row->district ?? ''),
                    'position' => (string) ($row->position ?? 'Pozisyon belirtilmedi'),
                    'match_date' => $row->match_date,
                ];
            })
            ->values()
            ->all();
    }

    private function buildOpportunityMatch(object $row, string $playerPosition, int $playerAge, string $playerCity, bool $hasTeam): ?array
    {
        $score = 46;
        $reasons = [];
        $needPosition = $this->normalizePosition($row->position);
        $needCity = $this->normalizeText($row->city);

        if ($needPosition !== '' && $playerPosition !== '' && $needPosition === $playerPosition) {
            $score += 28;
            $reasons[] = 'Pozisyon profiline uyuyor';
        } elseif ($needPosition === '' || $playerPosition === '') {
            $score += 8;
            $reasons[] = 'Pozisyon bilgisi esnek';
        } else {
            return null;
        }

        if ($playerAge > 0 && ($row->age_min || $row->age_max)) {
            $ageMin = (int) ($row->age_min ?? 0);
            $ageMax = (int) ($row->age_max ?? 0);
            if (($ageMin === 0 || $playerAge >= $ageMin) && ($ageMax === 0 || $playerAge <= $ageMax)) {
                $score += 16;
                $reasons[] = 'Yaş aralığıyla eşleşiyor';
            }
        }

        if ($needCity !== '' && $playerCity !== '' && $needCity === $playerCity) {
            $score += 8;
            $reasons[] = 'Şehir tercihi uyumlu';
        }

        if (! $hasTeam) {
            $score += 6;
            $reasons[] = 'Mevcut durumun hızlı değerlendirmeye uygun';
        }

        $score = max(55, min($score, 98));
        $reason = $reasons[0] ?? 'Profil kriterleriyle uyumlu';
        if (count($reasons) > 1) {
            $reason = $reasons[0].', '.$reasons[1];
        }

        return [
            'opportunity_id' => (int) $row->id,
            'club_name' => (string) ($row->owner_name ?? 'Kulüp'),
            'owner_role' => (string) ($row->owner_role ?? 'team'),
            'position_needed' => (string) ($row->position ?? 'Oyuncu'),
            'match_score' => $score,
            'reason' => $reason,
            'city' => (string) ($row->city ?? '-'),
            'title' => (string) ($row->title ?? 'Açık fırsat'),
        ];
    }

    private function normalizePosition(?string $value): string
    {
        $value = $this->normalizeText($value);
        if ($value === '') {
            return '';
        }

        return match ($value) {
            'stoper', 'defans', 'defender', 'centre-back' => 'defans',
            'orta saha', 'ortasaha', 'midfielder', '6 numara', '10 numara' => 'ortasaha',
            'forvet', 'santrafor', 'forward', 'striker' => 'forvet',
            'kaleci', 'goalkeeper' => 'kaleci',
            'sag bek', 'sol bek', 'bek', 'full-back' => 'defans',
            'kanat', 'winger' => 'forvet',
            default => $value,
        };
    }

    private function normalizeText(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $map = [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's',
            'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u',
            'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ];

        return strtolower(strtr($value, $map));
    }
}
