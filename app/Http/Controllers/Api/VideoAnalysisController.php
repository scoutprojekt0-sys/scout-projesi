<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Jobs\RunVideoAnalysisJob;
use App\Models\VideoAnalysis;
use App\Models\VideoClip;
use App\Services\VideoAnalysisResultService;
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
            'force_reanalyze' => ['nullable', 'boolean'],
        ]);

        $videoClip = VideoClip::findOrFail($validated['video_clip_id']);
        $targetPlayerId = $validated['target_player_id'] ?? $videoClip->user_id;
        $analysisType = $validated['analysis_type'] ?? 'scout_mvp';
        $forceReanalyze = (bool) ($validated['force_reanalyze'] ?? false);

        $existingAnalysis = null;
        if (! $forceReanalyze) {
            $existingAnalysis = VideoAnalysis::query()
                ->where('video_clip_id', $videoClip->id)
                ->where('target_player_id', $targetPlayerId)
                ->where('analysis_type', $analysisType)
                ->whereIn('status', ['queued', 'processing'])
                ->latest('id')
                ->first();
        }

        if ($existingAnalysis) {
            $existingAnalysisPayload = $existingAnalysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']);

            return $this->successResponse(
                $existingAnalysisPayload,
                'Mevcut video analizi donduruldu.',
                Response::HTTP_OK,
                [
                    'meta' => $this->buildAnalysisMeta($existingAnalysisPayload, 'cached'),
                ]
            );
        }

        $analysis = VideoAnalysis::create([
            'video_clip_id' => $videoClip->id,
            'requested_by' => $request->user()->id,
            'target_player_id' => $targetPlayerId,
            'analysis_type' => $analysisType,
            'provider' => (string) config('scout.ai_analysis.mode', 'mock') === 'external' ? 'external' : 'mock',
            'status' => 'queued',
            'worker_status' => 'queued',
            'analysis_version' => 'mock-v1',
        ]);

        RunVideoAnalysisJob::dispatchSync($analysis->id);

        $analysisPayload = $analysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']);

        return $this->successResponse(
            $analysisPayload,
            'Video analizi baslatildi.',
            Response::HTTP_CREATED,
            [
                'meta' => $this->buildAnalysisMeta($analysisPayload, 'fresh'),
            ]
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $analysis = VideoAnalysis::with(['videoClip', 'targetPlayer', 'metrics', 'targets'])
            ->findOrFail($id);

        $this->authorizeView($request, $analysis);

        return $this->successResponse($analysis, 'Video analiz detayi hazir.', Response::HTTP_OK, [
            'meta' => $this->buildAnalysisMeta($analysis),
        ]);
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

    public function callback(Request $request, int $id, VideoAnalysisResultService $resultService): JsonResponse
    {
        $this->authorizeCallback($request);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:completed,failed'],
            'analysis_version' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'array'],
            'raw_output' => ['nullable', 'array'],
            'failure_reason' => ['nullable', 'string'],
            'targets' => ['nullable', 'array'],
            'events' => ['nullable', 'array'],
            'metrics' => ['nullable', 'array'],
        ]);

        $analysis = VideoAnalysis::with('videoClip')->findOrFail($id);

        if ($validated['status'] === 'failed') {
            $resultService->fail(
                $analysis,
                $validated['failure_reason'] ?? 'AI worker analizi basarisiz oldu.',
                $validated['raw_output'] ?? null
            );

            return $this->successResponse($analysis->fresh(), 'Video analiz callback failure kaydi alindi.');
        }

        $updated = $resultService->complete($analysis, $validated);

        return $this->successResponse($updated, 'Video analiz callback sonucu islendi.');
    }

    private function authorizeView(Request $request, VideoAnalysis $analysis): void
    {
        if ((int) $analysis->requested_by !== (int) $request->user()->id && (int) $analysis->videoClip->user_id !== (int) $request->user()->id) {
            abort(403, 'Bu video analizine erisim yetkiniz yok.');
        }
    }

    private function authorizeCallback(Request $request): void
    {
        $configuredSecret = (string) config('scout.ai_analysis.callback_secret', '');
        if ($configuredSecret === '') {
            abort(503, 'AI callback secret tanimli degil.');
        }

        $providedSecret = (string) $request->header('X-Analysis-Callback-Secret', '');
        if (! hash_equals($configuredSecret, $providedSecret)) {
            abort(403, 'Gecersiz callback imzasi.');
        }
    }

    private function buildAnalysisMeta(VideoAnalysis $analysis, ?string $source = null): array
    {
        $rawOutput = (array) ($analysis->raw_output ?? []);

        return array_filter([
            'analysis_source' => $source,
            'provider' => $analysis->provider,
            'analysis_version' => $analysis->analysis_version,
            'fallback_mode' => $rawOutput['fallback_mode'] ?? null,
            'fallback_from' => $rawOutput['fallback_from'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
