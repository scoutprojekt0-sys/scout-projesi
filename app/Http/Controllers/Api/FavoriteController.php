<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FavoriteController extends Controller
{
    public function publicLeaderboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'in:player,manager,scout,coach,club'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $role = (string) ($validated['role'] ?? 'player');
        $limit = (int) ($validated['limit'] ?? 24);

        if ($role === 'player') {
            $rows = DB::table('users')
                ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
                ->leftJoin('favorites', 'favorites.target_user_id', '=', 'users.id')
                ->where('users.role', 'player')
                ->groupBy([
                    'users.id',
                    'users.name',
                    'users.role',
                    'users.city',
                    'users.age',
                    'users.photo_url',
                    'users.rating',
                    'player_profiles.position',
                    'player_profiles.current_team',
                    'player_profiles.height_cm',
                ])
                ->selectRaw('
                    users.id,
                    users.name,
                    users.role,
                    users.city,
                    users.age,
                    users.photo_url,
                    users.rating,
                    player_profiles.position,
                    player_profiles.current_team as club,
                    player_profiles.height_cm as height,
                    COUNT(favorites.id) as favorites_count
                ')
                ->orderByDesc('favorites_count')
                ->orderByDesc('users.rating')
                ->limit($limit)
                ->get();
        } elseif (in_array($role, ['manager', 'scout', 'coach'], true)) {
            $rows = DB::table('users')
                ->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
                ->leftJoin('favorites', 'favorites.target_user_id', '=', 'users.id')
                ->where('users.role', $role)
                ->groupBy([
                    'users.id',
                    'users.name',
                    'users.role',
                    'users.city',
                    'users.age',
                    'users.photo_url',
                    'users.rating',
                    'staff_profiles.role_type',
                    'staff_profiles.organization',
                    'staff_profiles.experience_years',
                ])
                ->selectRaw('
                    users.id,
                    users.name,
                    users.role,
                    users.city,
                    users.age,
                    users.photo_url,
                    users.rating,
                    staff_profiles.role_type,
                    staff_profiles.organization as club,
                    staff_profiles.experience_years,
                    COUNT(favorites.id) as favorites_count
                ')
                ->orderByDesc('favorites_count')
                ->orderByDesc('users.rating')
                ->limit($limit)
                ->get();
        } else {
            $rows = DB::table('users')
                ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
                ->leftJoin('favorites', 'favorites.target_user_id', '=', 'users.id')
                ->whereIn('users.role', ['team', 'club'])
                ->groupBy([
                    'users.id',
                    'users.name',
                    'users.role',
                    'users.city',
                    'users.age',
                    'users.photo_url',
                    'users.rating',
                    'team_profiles.team_name',
                    'team_profiles.league_level',
                    'team_profiles.founded_year',
                ])
                ->selectRaw('
                    users.id,
                    users.name,
                    users.role,
                    users.city,
                    users.age,
                    users.photo_url,
                    users.rating,
                    team_profiles.team_name as club,
                    team_profiles.league_level as league,
                    team_profiles.founded_year,
                    COUNT(favorites.id) as favorites_count
                ')
                ->orderByDesc('favorites_count')
                ->orderByDesc('users.rating')
                ->limit($limit)
                ->get();
        }

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->with('targetUser:id,name,role,city,position,photo_url')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $favorites,
        ]);
    }

    public function toggle(Request $request, int $targetUserId): JsonResponse
    {
        $userId = (int) $request->user()->id;

        if ($userId === $targetUserId) {
            return response()->json([
                'ok' => false,
                'message' => 'Kendinizi favorilere ekleyemezsiniz.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $favorite = Favorite::query()
            ->where('user_id', $userId)
            ->where('target_user_id', $targetUserId)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Favorilerden cikarildi.',
                'data' => ['is_favorited' => false],
            ]);
        }

        Favorite::query()->create([
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
        ]);

        $actor = User::query()->find($userId, ['id', 'name', 'role']);

        Notification::query()->create([
            'user_id' => $targetUserId,
            'type' => 'favorite_added',
            'title' => 'Yeni takipci',
            'message' => sprintf(
                '%s seni takip listesine ekledi.',
                $actor?->name ?: 'Bir uye'
            ),
            'payload' => [
                'actor_user_id' => $userId,
                'actor_name' => $actor?->name,
                'actor_role' => $actor?->role,
            ],
            'priority' => 'normal',
            'is_read' => false,
            'related_player_id' => $targetUserId,
        ]);
        Cache::forget("notifications_count_{$targetUserId}");

        return response()->json([
            'ok' => true,
            'message' => 'Favorilere eklendi.',
            'data' => ['is_favorited' => true],
        ]);
    }

    public function check(Request $request, int $targetUserId): JsonResponse
    {
        $isFavorited = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('target_user_id', $targetUserId)
            ->exists();

        return response()->json([
            'ok' => true,
            'data' => ['is_favorited' => $isFavorited],
        ]);
    }
}
