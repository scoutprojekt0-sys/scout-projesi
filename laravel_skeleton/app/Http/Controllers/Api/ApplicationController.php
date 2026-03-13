<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ApplicationController extends Controller
{
    public function apply(Request $request, int $id): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser->role !== 'player') {
            return response()->json([
                'ok' => false,
                'message' => 'Sadece oyuncular ilana basvuru yapabilir.',
            ], Response::HTTP_FORBIDDEN);
        }

        $opportunity = DB::table('opportunities')
            ->where('id', $id)
            ->where('status', 'open')
            ->first();

        if (!$opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi veya basvuruya kapali.',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = DB::table('applications')
            ->where('opportunity_id', $id)
            ->where('player_user_id', (int) $authUser->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu ilana zaten basvurdunuz.',
            ], Response::HTTP_CONFLICT);
        }

        $applicationId = DB::table('applications')->insertGetId([
            'opportunity_id' => $id,
            'player_user_id' => (int) $authUser->id,
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = DB::table('applications')->where('id', $applicationId)->first();

        return response()->json([
            'ok' => true,
            'message' => 'Basvuru olusturuldu.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function incoming(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser->role !== 'team') {
            return response()->json([
                'ok' => false,
                'message' => 'Sadece takim hesabi gelen basvurulari gorebilir.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'rejected'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->join('users as players', 'players.id', '=', 'applications.player_user_id')
            ->where('opportunities.team_user_id', (int) $authUser->id)
            ->select([
                'applications.id',
                'applications.status',
                'applications.message',
                'applications.created_at',
                'opportunities.id as opportunity_id',
                'opportunities.title as opportunity_title',
                'players.id as player_id',
                'players.name as player_name',
                'players.city as player_city',
            ]);

        if (!empty($validated['status'])) {
            $query->where('applications.status', $validated['status']);
        }

        $incoming = $query
            ->orderByDesc('applications.created_at')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
            ],
            'data' => $incoming,
        ]);
    }

    public function outgoing(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser->role !== 'player') {
            return response()->json([
                'ok' => false,
                'message' => 'Sadece oyuncu hesabi gonderilen basvurulari gorebilir.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'rejected'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->join('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->where('applications.player_user_id', (int) $authUser->id)
            ->select([
                'applications.id',
                'applications.status',
                'applications.message',
                'applications.created_at',
                'opportunities.id as opportunity_id',
                'opportunities.title as opportunity_title',
                'teams.id as team_id',
                'teams.name as team_name',
                'teams.city as team_city',
            ]);

        if (!empty($validated['status'])) {
            $query->where('applications.status', $validated['status']);
        }

        $outgoing = $query
            ->orderByDesc('applications.created_at')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
            ],
            'data' => $outgoing,
        ]);
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser->role !== 'team') {
            return response()->json([
                'ok' => false,
                'message' => 'Basvuru durumunu sadece takim hesabi degistirebilir.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
        ]);

        $application = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->where('applications.id', $id)
            ->select([
                'applications.id',
                'applications.status',
                'applications.opportunity_id',
                'opportunities.team_user_id',
            ])
            ->first();

        if (!$application) {
            return response()->json([
                'ok' => false,
                'message' => 'Basvuru bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ((int) $application->team_user_id !== (int) $authUser->id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu basvuruya mudahale yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::table('applications')
            ->where('id', $id)
            ->update([
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);

        $updated = DB::table('applications')->where('id', $id)->first();

        return response()->json([
            'ok' => true,
            'message' => 'Basvuru durumu guncellendi.',
            'data' => $updated,
        ]);
    }
}
