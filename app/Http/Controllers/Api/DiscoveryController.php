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
            ->where('role', 'player')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($position, fn($q) => $q->where('position', $position))
            ->when($city, fn($q) => $q->where('city', $city))
            ->select('id', 'name', 'position', 'city', 'age', 'photo_url')
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
            ->select('opportunities.*', 'teams.name as manager_name')
            ->orderBy('opportunities.created_at', 'desc')
            ->paginate(20);

        return $this->paginatedListResponse($needs, 'Menajer ihtiyaclari hazir.');
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
