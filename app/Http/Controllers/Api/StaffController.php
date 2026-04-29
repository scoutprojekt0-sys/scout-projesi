<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\EnforcesPrivacy;
use App\Http\Controllers\Concerns\ResolvesPublicFileUrls;
use App\Support\ProfileReviewData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller
{
    use EnforcesPrivacy;
    use ResolvesPublicFileUrls;

    public function me(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if (! $authUser || ! in_array($authUser->role, ['manager', 'coach', 'scout'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Staff profili bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->show($request, (int) $authUser->id);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if (! $authUser || ! in_array($authUser->role, ['manager', 'coach', 'scout'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Staff profili bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->update($request, (int) $authUser->id);
    }

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
                'users.country',
                'users.phone',
                'users.photo_url as profile_photo_url',
                'staff_profiles.role_type',
                'staff_profiles.branch',
                'staff_profiles.organization',
                'staff_profiles.experience_years',
                'staff_profiles.bio',
                'staff_profiles.focus',
                'staff_profiles.coverage',
                'staff_profiles.scouting_notes',
            ]);

        if (! empty($validated['role_type'])) {
            $query->where('staff_profiles.role_type', $validated['role_type']);
        }

        if (! empty($validated['organization'])) {
            $query->where('staff_profiles.organization', 'like', '%'.$validated['organization'].'%');
        }

        if (! empty($validated['city'])) {
            $query->where('users.city', 'like', '%'.$validated['city'].'%');
        }

        $staff = $query
            ->orderByDesc('users.created_at')
            ->paginate($perPage);

        $authUser = $request->user();
        $adminAccess = $this->isAdmin($authUser);
        $staff->getCollection()->transform(function ($member) use ($authUser, $adminAccess) {
            $isOwner = $authUser && (int) $authUser->id === (int) ($member->id ?? 0);
            $redacted = $this->redactPrivateFields($member, $adminAccess || $isOwner);
            $redacted->profile_photo_url = $this->publicFileUrl($redacted->profile_photo_url ?? null);
            return $redacted;
        });

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

    public function show(Request $request, int $id): JsonResponse
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
                'users.country',
                'users.phone',
                'users.photo_url as profile_photo_url',
                'users.role',
                'users.created_at',
                'staff_profiles.role_type',
                'staff_profiles.branch',
                'staff_profiles.organization',
                'staff_profiles.experience_years',
                'staff_profiles.bio',
                'staff_profiles.focus',
                'staff_profiles.coverage',
                'staff_profiles.scouting_notes',
            ])
            ->first();

        if (! $staff) {
            return response()->json([
                'ok' => false,
                'message' => 'Staff profili bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        $staff = $this->redactPrivateFields($staff, $this->canSeePrivate($authUser, $id));
        $staff->profile_photo_url = $this->publicFileUrl($staff->profile_photo_url ?? null);

        return response()->json([
            'ok' => true,
            'data' => [
                ...((array) $staff),
                'reviews' => ProfileReviewData::latestForTarget($id, $authUser),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = DB::table('users')
            ->where('id', $id)
            ->whereIn('role', ['manager', 'coach', 'scout'])
            ->first();

        if (! $target) {
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
            'country' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role_type' => ['sometimes', Rule::in(['manager', 'coach', 'scout'])],
            'branch' => ['sometimes', 'nullable', 'string', 'max:120'],
            'organization' => ['sometimes', 'nullable', 'string', 'max:140'],
            'experience_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'focus' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'coverage' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'scouting_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'profile_photo_url' => ['sometimes', 'nullable', 'string', 'max:65535'],
        ]);

        DB::table('users')
            ->where('id', $id)
            ->update([
                'name' => $validated['name'] ?? $authUser->name,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $authUser->city,
                'country' => array_key_exists('country', $validated) ? $validated['country'] : $authUser->country,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $authUser->phone,
                'photo_url' => array_key_exists('profile_photo_url', $validated) ? $validated['profile_photo_url'] : $authUser->photo_url,
                'updated_at' => now(),
            ]);

        $existingProfile = DB::table('staff_profiles')->where('user_id', $id)->first();
        $defaultRoleType = in_array($authUser->role, ['manager', 'coach', 'scout'], true) ? $authUser->role : 'scout';

        DB::table('staff_profiles')->updateOrInsert(
            ['user_id' => $id],
            [
                'role_type' => array_key_exists('role_type', $validated) ? $validated['role_type'] : ($existingProfile->role_type ?? $defaultRoleType),
                'branch' => array_key_exists('branch', $validated) ? $validated['branch'] : ($existingProfile->branch ?? null),
                'organization' => array_key_exists('organization', $validated) ? $validated['organization'] : ($existingProfile->organization ?? null),
                'experience_years' => array_key_exists('experience_years', $validated) ? $validated['experience_years'] : ($existingProfile->experience_years ?? null),
                'bio' => array_key_exists('bio', $validated) ? $validated['bio'] : ($existingProfile->bio ?? null),
                'focus' => array_key_exists('focus', $validated) ? $validated['focus'] : ($existingProfile->focus ?? null),
                'coverage' => array_key_exists('coverage', $validated) ? $validated['coverage'] : ($existingProfile->coverage ?? null),
                'scouting_notes' => array_key_exists('scouting_notes', $validated) ? $validated['scouting_notes'] : ($existingProfile->scouting_notes ?? null),
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
                'users.country',
                'users.phone',
                'users.photo_url as profile_photo_url',
                'users.role',
                'staff_profiles.role_type',
                'staff_profiles.branch',
                'staff_profiles.organization',
                'staff_profiles.experience_years',
                'staff_profiles.bio',
                'staff_profiles.focus',
                'staff_profiles.coverage',
                'staff_profiles.scouting_notes',
            ])
            ->first();

        if ($updated) {
            $updated->profile_photo_url = $this->publicFileUrl($updated->profile_photo_url ?? null);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Staff profili guncellendi.',
            'data' => $updated,
        ]);
    }

}
