<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Jobs\RunVideoAnalysisJob;
use App\Models\VideoAnalysis;
use App\Models\VideoClip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VideoAnalysisController extends Controller
{
    use ApiResponds;

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_clip_id' => ['required', 'integer', 'exists:video_clips,id'],
            'target_player_id' => ['nullable', 'integer', 'exists:users,id'],
            'analysis_type' => ['nullable', 'string', 'max:80'],
        ]);

        $videoClip = VideoClip::findOrFail($validated['video_clip_id']);
        $targetPlayerId = $validated['target_player_id'] ?? $videoClip->user_id;
        $analysisType = $validated['analysis_type'] ?? 'scout_mvp';

        $existingAnalysis = VideoAnalysis::query()
            ->where('video_clip_id', $videoClip->id)
            ->where('target_player_id', $targetPlayerId)
            ->where('analysis_type', $analysisType)
            ->whereIn('status', ['queued', 'processing', 'completed'])
            ->latest('id')
            ->first();

        if ($existingAnalysis) {
            return $this->successResponse(
                $existingAnalysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']),
                'Mevcut video analizi donduruldu.',
                Response::HTTP_OK,
                [
                    'meta' => [
                        'analysis_source' => 'cached',
                    ],
                ]
            );
        }

        $analysis = VideoAnalysis::create([
            'video_clip_id' => $videoClip->id,
            'requested_by' => $request->user()->id,
            'target_player_id' => $targetPlayerId,
            'analysis_type' => $analysisType,
            'status' => 'queued',
            'analysis_version' => 'mock-v1',
        ]);

        RunVideoAnalysisJob::dispatchSync($analysis->id);

        return $this->successResponse(
            $analysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']),
            'Video analizi baslatildi.',
            Response::HTTP_CREATED,
            [
                'meta' => [
                    'analysis_source' => 'fresh',
                ],
            ]
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $analysis = VideoAnalysis::with(['videoClip', 'targetPlayer', 'metrics', 'targets'])
            ->findOrFail($id);

        $this->authorizeView($request, $analysis);

        return $this->successResponse($analysis, 'Video analiz detayi hazir.');
    }

    public function events(Request $request, int $id): JsonResponse
    {
        $analysis = VideoAnalysis::findOrFail($id);
        $this->authorizeView($request, $analysis);

        return $this->successResponse(
            $analysis->events()->with('clips')->orderBy('start_second')->get(),
            'Video analiz event listesi hazir.'
        );
    }

    public function clips(Request $request, int $id): JsonResponse
    {
        $analysis = VideoAnalysis::findOrFail($id);
        $this->authorizeView($request, $analysis);

        return $this->successResponse(
            $analysis->clips()->with('event')->get(),
            'Video analiz klipleri hazir.'
        );
    }

    private function authorizeView(Request $request, VideoAnalysis $analysis): void
    {
        if ((int) $analysis->requested_by !== (int) $request->user()->id && (int) $analysis->videoClip->user_id !== (int) $request->user()->id) {
            abort(403, 'Bu video analizine erisim yetkiniz yok.');
        }
    }
}
