<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_type' => ['nullable', Rule::in(['manager', 'coach', 'scout'])],
            'organization' => ['nullable', 'string', 'max:140'],
            'city' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('users')
            ->join('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
            ->whereIn('users.role', ['manager', 'coach', 'scout'])
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city',
                'users.phone',
                'staff_profiles.role_type',
                'staff_profiles.organization',
                'staff_profiles.experience_years',
                'staff_profiles.bio',
            ]);

        if (!empty($validated['role_type'])) {
            $query->where('staff_profiles.role_type', $validated['role_type']);
        }

        if (!empty($validated['organization'])) {
            $query->where('staff_profiles.organization', 'like', '%' . $validated['organization'] . '%');
        }

        if (!empty($validated['city'])) {
            $query->where('users.city', 'like', '%' . $validated['city'] . '%');
        }

        $staff = $query
            ->orderByDesc('users.created_at')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'role_type' => $validated['role_type'] ?? null,
                'organization' => $validated['organization'] ?? null,
                'city' => $validated['city'] ?? null,
            ],
            'data' => $staff,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $staff = DB::table('users')
            ->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->whereIn('users.role', ['manager', 'coach', 'scout'])
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city',
                'users.phone',
                'users.role',
                'users.created_at',
                'staff_profiles.role_type',
                'staff_profiles.organization',
                'staff_profiles.experience_years',
                'staff_profiles.bio',
            ])
            ->first();

        if (!$staff) {
            return response()->json([
                'ok' => false,
                'message' => 'Staff profili bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'ok' => true,
            'data' => $staff,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = DB::table('users')
            ->where('id', $id)
            ->whereIn('role', ['manager', 'coach', 'scout'])
            ->first();

        if (!$target) {
            return response()->json([
                'ok' => false,
                'message' => 'Staff profili bulunamadi.',
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
            'role_type' => ['sometimes', Rule::in(['manager', 'coach', 'scout'])],
            'organization' => ['sometimes', 'nullable', 'string', 'max:140'],
            'experience_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        DB::table('users')
            ->where('id', $id)
            ->update([
                'name' => $validated['name'] ?? $authUser->name,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $authUser->city,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $authUser->phone,
                'updated_at' => now(),
            ]);

        $existingProfile = DB::table('staff_profiles')->where('user_id', $id)->first();
        $defaultRoleType = in_array($authUser->role, ['manager', 'coach', 'scout'], true) ? $authUser->role : 'scout';

        DB::table('staff_profiles')->updateOrInsert(
            ['user_id' => $id],
            [
                'role_type' => array_key_exists('role_type', $validated) ? $validated['role_type'] : ($existingProfile->role_type ?? $defaultRoleType),
                'organization' => array_key_exists('organization', $validated) ? $validated['organization'] : ($existingProfile->organization ?? null),
                'experience_years' => array_key_exists('experience_years', $validated) ? $validated['experience_years'] : ($existingProfile->experience_years ?? null),
                'bio' => array_key_exists('bio', $validated) ? $validated['bio'] : ($existingProfile->bio ?? null),
                'updated_at' => now(),
            ]
        );

        $updated = DB::table('users')
            ->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.city',
                'users.phone',
                'users.role',
                'staff_profiles.role_type',
                'staff_profiles.organization',
                'staff_profiles.experience_years',
                'staff_profiles.bio',
            ])
            ->first();

        return response()->json([
            'ok' => true,
            'message' => 'Staff profili guncellendi.',
            'data' => $updated,
        ]);
    }
}
