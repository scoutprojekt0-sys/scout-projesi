<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiTrainingRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiTrainingRunController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport' => ['nullable', 'in:football,basketball,volleyball'],
            'status' => ['nullable', 'in:queued,running,completed,failed'],
            'include_failed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = AiTrainingRun::query()
            ->withCount('candidates')
            ->latest('id');

        if (isset($validated['sport'])) {
            $query->where('sport', $validated['sport']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if ($request->has('include_failed') && $request->boolean('include_failed') === false && ! isset($validated['status'])) {
            $query->where('status', '!=', AiTrainingRun::STATUS_FAILED);
        }

        $rows = $query->paginate((int) ($validated['per_page'] ?? 20))->through(
            fn (AiTrainingRun $run) => $this->transformSummary($run)
        );

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $run = AiTrainingRun::query()
            ->with([
                'candidates.videoClip:id,user_id,title,video_url,thumbnail_url,platform,match_date',
                'candidates.user:id,name,role,city',
            ])
            ->findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $this->transformDetail($run),
        ]);
    }

    private function transformSummary(AiTrainingRun $run): array
    {
        return [
            'id' => (int) $run->id,
            'sport' => (string) $run->sport,
            'status' => (string) $run->status,
            'model_version' => (string) $run->model_version,
            'device' => (string) $run->device,
            'epochs' => (int) $run->epochs,
            'imgsz' => (int) $run->imgsz,
            'batch' => (int) $run->batch,
            'forced' => (bool) $run->forced,
            'candidate_count' => isset($run->candidates_count)
                ? (int) $run->candidates_count
                : (int) $run->candidate_count,
            'validation_passed' => $run->validation_passed,
            'validation_summary' => $run->validation_summary,
            'started_at' => optional($run->started_at)?->toIso8601String(),
            'completed_at' => optional($run->completed_at)?->toIso8601String(),
            'failed_at' => optional($run->failed_at)?->toIso8601String(),
            'created_at' => optional($run->created_at)?->toIso8601String(),
        ];
    }

    private function transformDetail(AiTrainingRun $run): array
    {
        return array_merge($this->transformSummary($run), [
            'candidate_ids' => is_array($run->candidate_ids) ? $run->candidate_ids : [],
            'notes' => $run->notes,
            'output_log' => $run->output_log,
            'candidates' => $run->candidates->map(function ($candidate) {
                return [
                    'id' => (int) $candidate->id,
                    'sport' => (string) $candidate->sport,
                    'status' => (string) $candidate->status,
                    'split' => $candidate->split,
                    'model_version' => $candidate->model_version,
                    'video_clip' => $candidate->videoClip ? [
                        'id' => (int) $candidate->videoClip->id,
                        'title' => (string) $candidate->videoClip->title,
                        'video_url' => (string) $candidate->videoClip->video_url,
                        'thumbnail_url' => $candidate->videoClip->thumbnail_url,
                        'platform' => (string) $candidate->videoClip->platform,
                        'match_date' => optional($candidate->videoClip->match_date)?->toDateString(),
                    ] : null,
                    'user' => $candidate->user ? [
                        'id' => (int) $candidate->user->id,
                        'name' => (string) $candidate->user->name,
                        'role' => (string) $candidate->user->role,
                        'city' => (string) ($candidate->user->city ?? ''),
                    ] : null,
                ];
            })->values()->all(),
        ]);
    }
}
