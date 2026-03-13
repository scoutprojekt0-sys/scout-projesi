<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:80'],
            'league_level' => ['nullable', 'string', 'max:60'],
            'needs_text' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('users')
            ->join('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'team')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city as user_city',
                'users.phone',
                'team_profiles.team_name',
                'team_profiles.league_level',
                'team_profiles.city as team_city',
                'team_profiles.founded_year',
                'team_profiles.needs_text',
            ]);

        if (!empty($validated['city'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('users.city', 'like', '%' . $validated['city'] . '%')
                    ->orWhere('team_profiles.city', 'like', '%' . $validated['city'] . '%');
            });
        }

        if (!empty($validated['league_level'])) {
            $query->where('team_profiles.league_level', 'like', '%' . $validated['league_level'] . '%');
        }

        if (!empty($validated['needs_text'])) {
            $query->where('team_profiles.needs_text', 'like', '%' . $validated['needs_text'] . '%');
        }

        $teams = $query
            ->orderByDesc('users.created_at')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'city' => $validated['city'] ?? null,
                'league_level' => $validated['league_level'] ?? null,
                'needs_text' => $validated['needs_text'] ?? null,
            ],
            'data' => $teams,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $team = DB::table('users')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'team')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city as user_city',
                'users.phone',
                'users.created_at',
                'team_profiles.team_name',
                'team_profiles.league_level',
                'team_profiles.city as team_city',
                'team_profiles.founded_year',
                'team_profiles.needs_text',
            ])
            ->first();

        if (!$team) {
            return response()->json([
                'ok' => false,
                'message' => 'Takim bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'ok' => true,
            'data' => $team,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = DB::table('users')->where('id', $id)->where('role', 'team')->first();
        if (!$target) {
            return response()->json([
                'ok' => false,
                'message' => 'Takim bulunamadi.',
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
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'team_name' => ['sometimes', 'string', 'min:2', 'max:140'],
            'league_level' => ['sometimes', 'nullable', 'string', 'max:60'],
            'team_city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'founded_year' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:' . now()->format('Y')],
            'needs_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        DB::table('users')
            ->where('id', $id)
            ->where('role', 'team')
            ->update([
                'name' => $validated['name'] ?? $authUser->name,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $authUser->city,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $authUser->phone,
                'updated_at' => now(),
            ]);

        $existingProfile = DB::table('team_profiles')->where('user_id', $id)->first();

        DB::table('team_profiles')->updateOrInsert(
            ['user_id' => $id],
            [
                'team_name' => array_key_exists('team_name', $validated) ? $validated['team_name'] : ($existingProfile->team_name ?? $authUser->name),
                'league_level' => array_key_exists('league_level', $validated) ? $validated['league_level'] : ($existingProfile->league_level ?? null),
                'city' => array_key_exists('team_city', $validated) ? $validated['team_city'] : ($existingProfile->city ?? null),
                'founded_year' => array_key_exists('founded_year', $validated) ? $validated['founded_year'] : ($existingProfile->founded_year ?? null),
                'needs_text' => array_key_exists('needs_text', $validated) ? $validated['needs_text'] : ($existingProfile->needs_text ?? null),
                'updated_at' => now(),
            ]
        );

        $updated = DB::table('users')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city as user_city',
                'users.phone',
                'team_profiles.team_name',
                'team_profiles.league_level',
                'team_profiles.city as team_city',
                'team_profiles.founded_year',
                'team_profiles.needs_text',
            ])
            ->first();

        return response()->json([
            'ok' => true,
            'message' => 'Takim profili guncellendi.',
            'data' => $updated,
        ]);
    }
}
