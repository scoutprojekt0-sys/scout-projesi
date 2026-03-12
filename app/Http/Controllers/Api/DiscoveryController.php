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
}
