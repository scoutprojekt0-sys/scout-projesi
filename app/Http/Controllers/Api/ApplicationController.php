<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\ApplyApplicationRequest;
use App\Http\Requests\Application\ChangeApplicationStatusRequest;
use App\Http\Requests\Application\IncomingApplicationsRequest;
use App\Http\Requests\Application\OutgoingApplicationsRequest;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApplicationController extends Controller
{
    public function apply(ApplyApplicationRequest $request, int $id): JsonResponse
    {
        $this->authorize('apply', Application::class);

        $opportunity = DB::table('opportunities')
            ->where('id', $id)
            ->where('status', 'open')
            ->first();

        if (! $opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi veya basvuruya kapali.',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        $exists = DB::table('applications')
            ->where('opportunity_id', $id)
            ->where('player_user_id', (int) $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu ilana zaten basvurdunuz.',
            ], Response::HTTP_CONFLICT);
        }

        $applicationId = DB::table('applications')->insertGetId([
            'opportunity_id' => $id,
            'player_user_id' => (int) $request->user()->id,
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

    public function incoming(IncomingApplicationsRequest $request): JsonResponse
    {
        $this->authorize('viewIncoming', Application::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $sortBy = (string) ($request->query('sort_by', 'created_at'));
        $sortDir = (string) ($request->query('sort_dir', 'desc'));
        if (! in_array($sortBy, ['created_at', 'status', 'opportunity_title'], true)) {
            $sortBy = 'created_at';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->join('users as players', 'players.id', '=', 'applications.player_user_id')
            ->where('opportunities.team_user_id', (int) $request->user()->id)
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

        if (! empty($validated['status'])) {
            $query->where('applications.status', $validated['status']);
        }

        $sortColumnMap = [
            'created_at' => 'applications.created_at',
            'status' => 'applications.status',
            'opportunity_title' => 'opportunities.title',
        ];
        $query->orderBy($sortColumnMap[$sortBy], $sortDir);

        $incoming = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'data' => $incoming,
        ]);
    }

    public function outgoing(OutgoingApplicationsRequest $request): JsonResponse
    {
        $this->authorize('viewOutgoing', Application::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $sortBy = (string) ($request->query('sort_by', 'created_at'));
        $sortDir = (string) ($request->query('sort_dir', 'desc'));
        if (! in_array($sortBy, ['created_at', 'status', 'opportunity_title'], true)) {
            $sortBy = 'created_at';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->join('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->where('applications.player_user_id', (int) $request->user()->id)
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

        if (! empty($validated['status'])) {
            $query->where('applications.status', $validated['status']);
        }

        $sortColumnMap = [
            'created_at' => 'applications.created_at',
            'status' => 'applications.status',
            'opportunity_title' => 'opportunities.title',
        ];
        $query->orderBy($sortColumnMap[$sortBy], $sortDir);

        $outgoing = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'data' => $outgoing,
        ]);
    }

    public function changeStatus(ChangeApplicationStatusRequest $request, int $id): JsonResponse
    {
        $application = Application::query()->with('opportunity')->find($id);

        if (! $application) {
            return response()->json([
                'ok' => false,
                'message' => 'Basvuru bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('changeStatus', $application);

        $validated = $request->validated();

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
