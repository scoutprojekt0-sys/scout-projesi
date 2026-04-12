<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reported_user_id' => ['nullable', 'exists:users,id'],
            'reported_entity_type' => ['nullable', 'string', 'max:50'],
            'reported_entity_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'in:spam,inappropriate,fake_profile,harassment,other'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = Report::query()->create([
            'reporter_user_id' => (int) $request->user()->id,
            ...$validated,
            'status' => 'pending',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Sikayet gonderildi. Incelenecektir.',
            'data' => $this->transformReport($report),
        ], Response::HTTP_CREATED);
    }

    public function myReports(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->where('reporter_user_id', $request->user()->id)
            ->with('reportedUser:id,name,role,city')
            ->latest('id')
            ->paginate(20)
            ->through(fn (Report $report) => $this->transformReport($report));

        return response()->json([
            'ok' => true,
            'data' => $reports,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $report = Report::query()
            ->where('id', $id)
            ->where('reporter_user_id', $request->user()->id)
            ->with('reportedUser:id,name,role,city')
            ->firstOrFail();

        return response()->json([
            'ok' => true,
            'data' => $this->transformReport($report),
        ]);
    }

    private function transformReport(Report $report): array
    {
        return [
            'id' => (int) $report->id,
            'reporter_user_id' => (int) $report->reporter_user_id,
            'reported_user_id' => $report->reported_user_id !== null ? (int) $report->reported_user_id : null,
            'reported_entity_type' => $report->reported_entity_type,
            'reported_entity_id' => $report->reported_entity_id !== null ? (int) $report->reported_entity_id : null,
            'reason' => (string) $report->reason,
            'description' => $report->description,
            'status' => (string) $report->status,
            'created_at' => optional($report->created_at)?->toIso8601String(),
            'updated_at' => optional($report->updated_at)?->toIso8601String(),
            'reported_user' => $report->relationLoaded('reportedUser') && $report->reportedUser ? [
                'id' => (int) $report->reportedUser->id,
                'name' => (string) $report->reportedUser->name,
                'role' => (string) $report->reportedUser->role,
                'city' => (string) ($report->reportedUser->city ?? ''),
            ] : null,
        ];
    }
}
