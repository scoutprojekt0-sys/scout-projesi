<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('player_profiles')
            ->join('users as players', 'players.id', '=', 'player_profiles.user_id')
            ->join('club_internal_players', function ($join): void {
                $join->whereRaw('LOWER(TRIM(club_internal_players.name)) = LOWER(TRIM(players.name))')
                    ->whereNotNull('club_internal_players.bio')
                    ->whereRaw("TRIM(club_internal_players.bio) <> ''")
                    ->whereColumn('player_profiles.bio', 'club_internal_players.bio');
            })
            ->join('users as clubs', 'clubs.id', '=', 'club_internal_players.club_user_id')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'clubs.id')
            ->where('players.role', 'player')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(TRIM(player_profiles.current_team)) = LOWER(TRIM(team_profiles.team_name))')
                    ->orWhereRaw('LOWER(TRIM(player_profiles.current_team)) = LOWER(TRIM(clubs.name))');
            })
            ->select('player_profiles.user_id')
            ->orderBy('player_profiles.user_id')
            ->chunkById(100, function ($profiles): void {
                DB::table('player_profiles')
                    ->whereIn('user_id', $profiles->pluck('user_id')->all())
                    ->update(['bio' => null]);
            }, 'player_profiles.user_id', 'user_id');
    }

    public function down(): void
    {
        //
    }
};
