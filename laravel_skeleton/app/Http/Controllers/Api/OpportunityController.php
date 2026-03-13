<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OpportunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'position' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
            'age_min' => ['nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['nullable', 'integer', 'min:10', 'max:60'],
            'team_user_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('opportunities')
            ->leftJoin('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->select([
                'opportunities.id',
                'opportunities.team_user_id',
                'teams.name as team_name',
                'teams.city as team_city',
                'opportunities.title',
                'opportunities.position',
                'opportunities.age_min',
                'opportunities.age_max',
                'opportunities.city',
                'opportunities.details',
                'opportunities.status',
                'opportunities.created_at',
                'opportunities.updated_at',
            ]);

        if (!empty($validated['status'])) {
            $query->where('opportunities.status', $validated['status']);
        }

        if (!empty($validated['position'])) {
            $query->where('opportunities.position', 'like', '%' . $validated['position'] . '%');
        }

        if (!empty($validated['city'])) {
            $query->where('opportunities.city', 'like', '%' . $validated['city'] . '%');
        }

        if (!empty($validated['team_user_id'])) {
            $query->where('opportunities.team_user_id', (int) $validated['team_user_id']);
        }

        if (!empty($validated['age_min'])) {
            $query->where('opportunities.age_min', '>=', (int) $validated['age_min']);
        }

        if (!empty($validated['age_max'])) {
            $query->where('opportunities.age_max', '<=', (int) $validated['age_max']);
        }

        $opportunities = $query
            ->orderByDesc('opportunities.created_at')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
                'position' => $validated['position'] ?? null,
                'city' => $validated['city'] ?? null,
                'age_min' => $validated['age_min'] ?? null,
                'age_max' => $validated['age_max'] ?? null,
                'team_user_id' => $validated['team_user_id'] ?? null,
            ],
            'data' => $opportunities,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser->role !== 'team') {
            return response()->json([
                'ok' => false,
                'message' => 'Sadece takim rolu ilan olusturabilir.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'position' => ['nullable', 'string', 'max:40'],
            'age_min' => ['nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['nullable', 'integer', 'min:10', 'max:60'],
            'city' => ['nullable', 'string', 'max:80'],
            'details' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
        ]);

        $id = DB::table('opportunities')->insertGetId([
            'team_user_id' => (int) $authUser->id,
            'title' => $validated['title'],
            'position' => $validated['position'] ?? null,
            'age_min' => $validated['age_min'] ?? null,
            'age_max' => $validated['age_max'] ?? null,
            'city' => $validated['city'] ?? null,
            'details' => $validated['details'] ?? null,
            'status' => $validated['status'] ?? 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = DB::table('opportunities')->where('id', $id)->first();

        return response()->json([
            'ok' => true,
            'message' => 'Ilan olusturuldu.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $opportunity = DB::table('opportunities')
            ->leftJoin('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->where('opportunities.id', $id)
            ->select([
                'opportunities.id',
                'opportunities.team_user_id',
                'teams.name as team_name',
                'teams.city as team_city',
                'opportunities.title',
                'opportunities.position',
                'opportunities.age_min',
                'opportunities.age_max',
                'opportunities.city',
                'opportunities.details',
                'opportunities.status',
                'opportunities.created_at',
                'opportunities.updated_at',
            ])
            ->first();

        if (!$opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'ok' => true,
            'data' => $opportunity,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $opportunity = DB::table('opportunities')->where('id', $id)->first();
        if (!$opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        if ((int) $authUser->id !== (int) $opportunity->team_user_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu ilani guncelleme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'min:3', 'max:160'],
            'position' => ['sometimes', 'nullable', 'string', 'max:40'],
            'age_min' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
        ]);

        DB::table('opportunities')
            ->where('id', $id)
            ->update([
                'title' => $validated['title'] ?? $opportunity->title,
                'position' => array_key_exists('position', $validated) ? $validated['position'] : $opportunity->position,
                'age_min' => array_key_exists('age_min', $validated) ? $validated['age_min'] : $opportunity->age_min,
                'age_max' => array_key_exists('age_max', $validated) ? $validated['age_max'] : $opportunity->age_max,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $opportunity->city,
                'details' => array_key_exists('details', $validated) ? $validated['details'] : $opportunity->details,
                'status' => $validated['status'] ?? $opportunity->status,
                'updated_at' => now(),
            ]);

        $updated = DB::table('opportunities')->where('id', $id)->first();

        return response()->json([
            'ok' => true,
            'message' => 'Ilan guncellendi.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $opportunity = DB::table('opportunities')->where('id', $id)->first();
        if (!$opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        if ((int) $authUser->id !== (int) $opportunity->team_user_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu ilani silme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::table('opportunities')->where('id', $id)->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Ilan silindi.',
        ]);
    }
}
