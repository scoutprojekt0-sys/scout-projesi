<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ClubController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'league_level' => ['nullable', 'string', 'max:60'],
            'country_id'   => ['nullable'],
            'league_id'    => ['nullable'],
            'search'       => ['nullable', 'string', 'max:120'],
            'sort_by'      => ['nullable', 'in:created_at,name,squad_count,total_market_value'],
            'sort_order'   => ['nullable', 'in:asc,desc'],
        ]);

        $query = DB::table('users')
            ->join('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'team')
            ->select([
                'users.id',
                'users.name',
                'users.city as user_city',
                'users.created_at',
                'team_profiles.team_name',
                'team_profiles.league_level',
                'team_profiles.city as team_city',
                'team_profiles.founded_year',
                DB::raw('(select count(*) from player_career_timeline where player_career_timeline.club_id = users.id and player_career_timeline.is_current = 1 and player_career_timeline.verification_status = "verified") as squad_count'),
                DB::raw('(select coalesce(sum(fee), 0) from player_transfers where player_transfers.to_club_id = users.id and player_transfers.verification_status = "verified") as total_market_value'),
            ]);

        if (!empty($validated['league_level'])) {
            $query->where('team_profiles.league_level', 'like', '%'.$validated['league_level'].'%');
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('team_profiles.team_name', 'like', '%'.$search.'%')
                    ->orWhere('users.city', 'like', '%'.$search.'%')
                    ->orWhere('team_profiles.city', 'like', '%'.$search.'%');
            });
        }

        $clubs = $query->orderBy($validated['sort_by'] ?? 'total_market_value', $validated['sort_order'] ?? 'desc')
            ->paginate(20);

        return $this->paginatedListResponse($clubs, 'Kulup listesi hazir.');
    }

    public function show(int $id): JsonResponse
    {
        $club = DB::table('users')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'team')
            ->select([
                'users.id', 'users.name', 'users.city as user_city',
                'users.created_at',
                'team_profiles.team_name', 'team_profiles.league_level',
                'team_profiles.city as team_city', 'team_profiles.founded_year',
            ])
            ->first();

        if (!$club) {
            return $this->errorResponse('Kulup bulunamadi.', Response::HTTP_NOT_FOUND, 'club_not_found');
        }

        $recentTransfers = DB::table('player_transfers')
            ->join('users as players', 'players.id', '=', 'player_transfers.player_id')
            ->leftJoin('users as from_club', 'from_club.id', '=', 'player_transfers.from_club_id')
            ->leftJoin('users as to_club', 'to_club.id', '=', 'player_transfers.to_club_id')
            ->where(function ($q) use ($id) {
                $q->where('player_transfers.to_club_id', $id)
                    ->orWhere('player_transfers.from_club_id', $id);
            })
            ->where('player_transfers.verification_status', 'verified')
            ->orderByDesc('player_transfers.transfer_date')
            ->limit(10)
            ->get([
                'player_transfers.id', 'players.name as player_name',
                'from_club.name as from_club_name', 'to_club.name as to_club_name',
                'player_transfers.fee', 'player_transfers.currency',
                'player_transfers.transfer_type', 'player_transfers.transfer_date',
            ]);

        return $this->successResponse([
            'club'             => $club,
            'recent_transfers' => $recentTransfers,
        ], 'Kulup detayi hazir.');
    }

    public function squad(int $id): JsonResponse
    {
        $club = DB::table('users')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'team')
            ->select(['users.id', 'users.name', 'team_profiles.team_name'])
            ->first();

        if (!$club) {
            return $this->errorResponse('Kulup bulunamadi.', Response::HTTP_NOT_FOUND, 'club_not_found');
        }

        $players = DB::table('player_career_timeline')
            ->join('users as players', 'players.id', '=', 'player_career_timeline.player_id')
            ->where('player_career_timeline.club_id', $id)
            ->where('player_career_timeline.is_current', true)
            ->where('player_career_timeline.verification_status', 'verified')
            ->orderByDesc('player_career_timeline.appearances')
            ->get([
                'players.id', 'players.name', 'players.position',
                'players.age', 'player_career_timeline.appearances',
                'player_career_timeline.goals', 'player_career_timeline.assists',
            ]);

        return $this->successResponse([
            'club'    => $club,
            'squad'   => $players,
            'summary' => [
                'total_players'        => $players->count(),
                'average_age'          => round((float) $players->avg('age'), 1),
                'total_market_value'   => 0,
                'average_market_value' => 0,
            ],
        ], 'Kadro bilgisi hazir.');
    }

    public function mostValuable(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);

        $clubs = DB::table('users')
            ->join('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'team')
            ->select([
                'users.id', 'users.name',
                'team_profiles.team_name', 'team_profiles.league_level',
                DB::raw('(select coalesce(sum(fee), 0) from player_transfers where player_transfers.to_club_id = users.id and player_transfers.verification_status = "verified") as total_market_value'),
            ])
            ->orderByDesc('total_market_value')
            ->limit($limit)
            ->get();

        return $this->successResponse($clubs, 'En degerli kulupler hazir.');
    }

    public function transfers(int $id): JsonResponse
    {
        $incoming = DB::table('player_transfers')
            ->join('users as players', 'players.id', '=', 'player_transfers.player_id')
            ->leftJoin('users as from_club', 'from_club.id', '=', 'player_transfers.from_club_id')
            ->where('player_transfers.to_club_id', $id)
            ->where('player_transfers.verification_status', 'verified')
            ->orderByDesc('player_transfers.transfer_date')
            ->get([
                'player_transfers.id', 'players.name as player_name',
                'from_club.name as from_club_name', 'player_transfers.fee',
                'player_transfers.currency', 'player_transfers.transfer_type',
                'player_transfers.transfer_date',
            ]);

        $outgoing = DB::table('player_transfers')
            ->join('users as players', 'players.id', '=', 'player_transfers.player_id')
            ->leftJoin('users as to_club', 'to_club.id', '=', 'player_transfers.to_club_id')
            ->where('player_transfers.from_club_id', $id)
            ->where('player_transfers.verification_status', 'verified')
            ->orderByDesc('player_transfers.transfer_date')
            ->get([
                'player_transfers.id', 'players.name as player_name',
                'to_club.name as to_club_name', 'player_transfers.fee',
                'player_transfers.currency', 'player_transfers.transfer_type',
                'player_transfers.transfer_date',
            ]);

        return $this->successResponse([
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'summary'  => [
                'total_spent'  => (float) $incoming->sum('fee'),
                'total_earned' => (float) $outgoing->sum('fee'),
                'balance'      => (float) $outgoing->sum('fee') - (float) $incoming->sum('fee'),
            ],
        ], 'Kulup transfer gecmisi hazir.');
    }
}
