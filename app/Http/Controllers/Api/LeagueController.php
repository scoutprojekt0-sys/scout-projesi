<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LeagueController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:120']]);

        $query = DB::table('team_profiles')
            ->join('users', 'users.id', '=', 'team_profiles.user_id')
            ->where('users.role', 'team')
            ->whereNotNull('team_profiles.league_level')
            ->where('team_profiles.league_level', '!=', '')
            ->select([
                'team_profiles.league_level as name',
                DB::raw('count(*) as team_count'),
                DB::raw('min(users.created_at) as created_at'),
            ])
            ->groupBy('team_profiles.league_level')
            ->orderBy('team_profiles.league_level');

        if (!empty($validated['search'])) {
            $query->having('name', 'like', '%'.$validated['search'].'%');
        }

        return $this->successResponse(
            $query->get()->map(fn ($league) => [
                'name'       => $league->name,
                'short_name' => $league->name,
                'tier'       => $this->extractTier((string) $league->name),
                'is_active'  => true,
                'team_count' => (int) $league->team_count,
            ])->values(),
            'Lig listesi hazir.'
        );
    }

    public function show(string $league): JsonResponse
    {
        $name  = urldecode($league);
        $clubs = DB::table('users')
            ->join('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'team')
            ->where('team_profiles.league_level', $name)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.city as user_city',
                   'team_profiles.team_name', 'team_profiles.city as team_city',
                   'team_profiles.founded_year']);

        if ($clubs->isEmpty()) {
            return $this->errorResponse('Lig bulunamadi.', Response::HTTP_NOT_FOUND, 'league_not_found');
        }

        return $this->successResponse([
            'name'        => $name,
            'short_name'  => $name,
            'tier'        => $this->extractTier($name),
            'is_active'   => true,
            'team_count'  => $clubs->count(),
            'clubs'       => $clubs,
        ], 'Lig detayi hazir.');
    }

    public function standings(string $league): JsonResponse
    {
        $name = urldecode($league);

        $rows = DB::table('users')
            ->join('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->leftJoin('player_career_timeline', function ($join) {
                $join->on('player_career_timeline.club_id', '=', 'users.id')
                    ->where('player_career_timeline.is_current', true)
                    ->where('player_career_timeline.verification_status', 'verified');
            })
            ->where('users.role', 'team')
            ->where('team_profiles.league_level', $name)
            ->groupBy('users.id', 'users.name')
            ->orderByRaw('coalesce(sum(player_career_timeline.goals), 0) desc')
            ->orderByRaw('coalesce(sum(player_career_timeline.assists), 0) desc')
            ->orderBy('users.name')
            ->get([
                'users.id as club_id', 'users.name as club_name',
                DB::raw('coalesce(sum(player_career_timeline.appearances), 0) as played'),
                DB::raw('coalesce(sum(player_career_timeline.goals), 0) as goals_for'),
                DB::raw('0 as goals_against'),
                DB::raw('coalesce(sum(player_career_timeline.goals), 0) as goal_difference'),
                DB::raw('coalesce(sum(player_career_timeline.goals) * 3 + sum(player_career_timeline.assists), 0) as points'),
            ]);

        if ($rows->isEmpty()) {
            return $this->errorResponse('Lig bulunamadi.', Response::HTTP_NOT_FOUND, 'league_not_found');
        }

        return $this->successResponse(
            $rows->values()->map(fn ($row, int $i) => [
                'position'      => $i + 1,
                'club_id'       => (int) $row->club_id,
                'club_name'     => $row->club_name,
                'played'        => (int) $row->played,
                'won'           => 0, 'drawn' => 0, 'lost' => 0,
                'goals_for'     => (int) $row->goals_for,
                'goals_against' => (int) $row->goals_against,
                'goal_difference' => (int) $row->goal_difference,
                'points'        => (int) $row->points,
                'form'          => null,
            ]),
            'Lig puan durumu hazir.',
            200,
            ['meta' => ['league' => $name, 'computed_from' => 'current_verified_player_career_timeline', 'computed_at' => now()->toIso8601String()]]
        );
    }

    public function topScorers(string $league): JsonResponse
    {
        $name = urldecode($league);

        $rows = DB::table('player_career_timeline')
            ->join('users as players', 'players.id', '=', 'player_career_timeline.player_id')
            ->join('users as clubs',   'clubs.id',   '=', 'player_career_timeline.club_id')
            ->join('team_profiles',    'team_profiles.user_id', '=', 'clubs.id')
            ->where('player_career_timeline.is_current', true)
            ->where('player_career_timeline.verification_status', 'verified')
            ->where('team_profiles.league_level', $name)
            ->orderByDesc('player_career_timeline.goals')
            ->orderByDesc('player_career_timeline.assists')
            ->limit(20)
            ->get(['players.id as player_id', 'players.name as player_name', 'players.position',
                   'clubs.name as club_name', 'player_career_timeline.goals',
                   'player_career_timeline.assists', 'player_career_timeline.appearances']);

        return $this->successResponse($rows, 'En cok gol atan oyuncular hazir.', 200, [
            'meta' => ['league' => $name, 'computed_from' => 'current_verified_player_career_timeline'],
        ]);
    }

    public function topAssists(string $league): JsonResponse
    {
        $name = urldecode($league);

        $rows = DB::table('player_career_timeline')
            ->join('users as players', 'players.id', '=', 'player_career_timeline.player_id')
            ->join('users as clubs',   'clubs.id',   '=', 'player_career_timeline.club_id')
            ->join('team_profiles',    'team_profiles.user_id', '=', 'clubs.id')
            ->where('player_career_timeline.is_current', true)
            ->where('player_career_timeline.verification_status', 'verified')
            ->where('team_profiles.league_level', $name)
            ->orderByDesc('player_career_timeline.assists')
            ->orderByDesc('player_career_timeline.goals')
            ->limit(20)
            ->get(['players.id as player_id', 'players.name as player_name', 'players.position',
                   'clubs.name as club_name', 'player_career_timeline.goals',
                   'player_career_timeline.assists', 'player_career_timeline.appearances']);

        return $this->successResponse($rows, 'En cok asist yapan oyuncular hazir.', 200, [
            'meta' => ['league' => $name, 'computed_from' => 'current_verified_player_career_timeline'],
        ]);
    }

    private function extractTier(string $name): ?int
    {
        if (preg_match('/(\d+)/', $name, $matches) === 1) {
            return (int) $matches[1];
        }
        return null;
    }
}
