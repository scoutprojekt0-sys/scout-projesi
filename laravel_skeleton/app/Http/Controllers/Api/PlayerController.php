<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PlayerController extends Controller
{
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

        if (!empty($validated['position'])) {
            $query->where('player_profiles.position', 'like', '%' . $validated['position'] . '%');
        }

        if (!empty($validated['city'])) {
            $query->where('users.city', 'like', '%' . $validated['city'] . '%');
        }

        if (!empty($validated['age_min'])) {
            $birthYearMax = $currentYear - (int) $validated['age_min'];
            $query->where('player_profiles.birth_year', '<=', $birthYearMax);
        }

        if (!empty($validated['age_max'])) {
            $birthYearMin = $currentYear - (int) $validated['age_max'];
            $query->where('player_profiles.birth_year', '>=', $birthYearMin);
        }

        $players = $query
            ->orderByDesc('users.created_at')
            ->paginate($perPage);

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

    public function show(int $id): JsonResponse
    {
        $player = DB::table('users')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city',
                'users.phone',
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

        if (!$player) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'ok' => true,
            'data' => $player,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = DB::table('users')->where('id', $id)->where('role', 'player')->first();
        if (!$target) {
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
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'birth_year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:' . now()->format('Y')],
            'position' => ['sometimes', 'nullable', 'string', 'max:40'],
            'dominant_foot' => ['sometimes', 'nullable', Rule::in(['left', 'right', 'both'])],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'min:120', 'max:230'],
            'weight_kg' => ['sometimes', 'nullable', 'integer', 'min:35', 'max:160'],
            'current_team' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        DB::table('users')
            ->where('id', $id)
            ->where('role', 'player')
            ->update([
                'name' => $validated['name'] ?? $authUser->name,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $authUser->city,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $authUser->phone,
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
                'users.city',
                'users.phone',
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
}
