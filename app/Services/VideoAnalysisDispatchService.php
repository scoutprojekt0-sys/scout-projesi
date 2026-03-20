<?php

namespace App\Services;

use App\Models\VideoAnalysis;
use RuntimeException;

class VideoAnalysisDispatchService
{
    public function __construct(
        private readonly MockVideoAnalysisService $mockVideoAnalysisService,
        private readonly ExternalVideoAnalysisClient $externalVideoAnalysisClient,
        private readonly VideoAnalysisResultService $resultService,
    ) {
    }

    public function dispatch(VideoAnalysis $analysis): VideoAnalysis
    {
        $mode = (string) config('scout.ai_analysis.mode', 'mock');

        if ($mode === 'external') {
            return $this->submitToExternalWorker($analysis);
        }

        return $this->mockVideoAnalysisService->run($analysis);
    }

    public function submitToExternalWorker(VideoAnalysis $analysis): VideoAnalysis
    {
        $analysis->update([
            'status' => 'processing',
            'provider' => 'external',
            'worker_status' => 'submitting',
            'started_at' => now(),
        ]);

        try {
            $response = $this->externalVideoAnalysisClient->submit($analysis->fresh('videoClip'));
        } catch (RuntimeException $exception) {
            return $this->resultService->fail($analysis, $exception->getMessage(), [
                'engine' => 'external-worker',
                'stage' => 'submit',
            ]);
        }

        $analysis->update([
            'worker_status' => $response['status'] ?? 'submitted',
            'external_job_id' => $response['job_id'] ?? null,
            'analysis_version' => $response['analysis_version'] ?? 'external-worker',
            'submitted_at' => now(),
            'raw_output' => [
                'engine' => 'external-worker',
                'submit_response' => $response,
            ],
        ]);

        return $analysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']);
    }
}
