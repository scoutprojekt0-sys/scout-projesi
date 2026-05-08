<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiDatasetCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiDatasetCandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport' => ['nullable', 'in:football,basketball,volleyball'],
            'status' => ['nullable', 'in:'.implode(',', AiDatasetCandidate::STATUSES)],
            'mine' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $query = AiDatasetCandidate::query()
            ->with([
                'videoClip:id,user_id,title,video_url,thumbnail_url,platform,match_date,tags,metadata,created_at',
                'user:id,name,role,city',
                'reviewer:id,name,role',
            ])
            ->latest('id');

        $isStaff = in_array((string) $user?->role, ['admin', 'scout', 'manager', 'coach', 'team', 'club'], true);
        if (! $isStaff || filter_var($validated['mine'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where('user_id', $user->id);
        }

        if (isset($validated['sport'])) {
            $query->where('sport', $validated['sport']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $rows = $query->paginate(20)->through(fn (AiDatasetCandidate $candidate) => $this->transform($candidate));

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $candidate = AiDatasetCandidate::query()
            ->with([
                'videoClip',
                'user:id,name,role,city',
                'reviewer:id,name,role',
            ])
            ->findOrFail($id);

        $user = $request->user();
        $isStaff = in_array((string) $user?->role, ['admin', 'scout', 'manager', 'coach', 'team', 'club'], true);
        abort_unless($isStaff || $candidate->user_id === $user->id, Response::HTTP_FORBIDDEN);

        return response()->json([
            'ok' => true,
            'data' => $this->transform($candidate),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', AiDatasetCandidate::STATUSES)],
            'split' => ['nullable', 'in:train,val,test'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'model_version' => ['nullable', 'string', 'max:120'],
        ]);

        $candidate = AiDatasetCandidate::query()->findOrFail($id);
        $candidate->status = $validated['status'];
        $candidate->split = $validated['split'] ?? $candidate->split;
        $candidate->notes = $validated['notes'] ?? $candidate->notes;
        $candidate->model_version = $validated['model_version'] ?? $candidate->model_version;

        $candidate->reviewed_by = $request->user()->id;

        switch ($validated['status']) {
            case AiDatasetCandidate::STATUS_LABELING:
                $candidate->labeling_started_at ??= now();
                break;
            case AiDatasetCandidate::STATUS_LABELED:
                $candidate->labeled_at = now();
                break;
            case AiDatasetCandidate::STATUS_APPROVED:
            case AiDatasetCandidate::STATUS_REJECTED:
            case AiDatasetCandidate::STATUS_ARCHIVED:
                $candidate->reviewed_at = now();
                break;
            case AiDatasetCandidate::STATUS_TRAINED:
                $candidate->trained_at = now();
                $candidate->reviewed_at ??= now();
                break;
        }

        $candidate->save();

        return response()->json([
            'ok' => true,
            'message' => 'Dataset aday durumu guncellendi.',
            'data' => $this->transform($candidate->fresh(['videoClip', 'user:id,name,role,city', 'reviewer:id,name,role'])),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AiDatasetCandidate::query();

        $isStaff = in_array((string) $user?->role, ['admin', 'scout', 'manager', 'coach', 'team', 'club'], true);
        if (! $isStaff) {
            $query->where('user_id', $user->id);
        }

        $rows = $query->get(['sport', 'status']);
        $bySport = [];
        $byStatus = [];

        foreach ($rows as $row) {
            $bySport[$row->sport] = ($bySport[$row->sport] ?? 0) + 1;
            $byStatus[$row->status] = ($byStatus[$row->status] ?? 0) + 1;
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'total' => $rows->count(),
                'by_sport' => $bySport,
                'by_status' => $byStatus,
            ],
        ]);
    }

    private function transform(AiDatasetCandidate $candidate): array
    {
        $clip = $candidate->videoClip;

        return [
            'id' => (int) $candidate->id,
            'video_clip_id' => (int) $candidate->video_clip_id,
            'user_id' => (int) $candidate->user_id,
            'sport' => (string) $candidate->sport,
            'status' => (string) $candidate->status,
            'split' => $candidate->split,
            'source_type' => (string) $candidate->source_type,
            'notes' => $candidate->notes,
            'metadata' => $candidate->metadata ?? [],
            'queued_at' => optional($candidate->queued_at)?->toIso8601String(),
            'labeling_started_at' => optional($candidate->labeling_started_at)?->toIso8601String(),
            'labeled_at' => optional($candidate->labeled_at)?->toIso8601String(),
            'reviewed_at' => optional($candidate->reviewed_at)?->toIso8601String(),
            'trained_at' => optional($candidate->trained_at)?->toIso8601String(),
            'model_version' => $candidate->model_version,
            'video_clip' => $clip ? [
                'id' => (int) $clip->id,
                'title' => (string) $clip->title,
                'video_url' => (string) $clip->video_url,
                'thumbnail_url' => $clip->thumbnail_url,
                'platform' => (string) $clip->platform,
                'match_date' => optional($clip->match_date)?->toDateString(),
                'tags' => is_array($clip->tags) ? $clip->tags : [],
                'created_at' => optional($clip->created_at)?->toIso8601String(),
            ] : null,
            'user' => $candidate->user ? [
                'id' => (int) $candidate->user->id,
                'name' => (string) $candidate->user->name,
                'role' => (string) $candidate->user->role,
                'city' => (string) ($candidate->user->city ?? ''),
            ] : null,
            'reviewer' => $candidate->reviewer ? [
                'id' => (int) $candidate->reviewer->id,
                'name' => (string) $candidate->reviewer->name,
                'role' => (string) $candidate->reviewer->role,
            ] : null,
        ];
    }
}
