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
            if ($this->shouldFallbackToMock()) {
                $mockedAnalysis = $this->mockVideoAnalysisService->run($analysis->fresh(['videoClip', 'targetPlayer']));
                $rawOutput = (array) ($mockedAnalysis->raw_output ?? []);
                $rawOutput['fallback_from'] = 'external-worker';
                $rawOutput['fallback_reason'] = $exception->getMessage();
                $rawOutput['fallback_mode'] = 'mock';

                $mockedAnalysis->update([
                    'provider' => 'mock',
                    'analysis_version' => 'mock-v1-fallback',
                    'raw_output' => $rawOutput,
                ]);

                return $mockedAnalysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']);
            }

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
                'stage' => 'submitted',
            ],
        ]);

        return $analysis->fresh(['videoClip', 'targetPlayer', 'events.clips', 'metrics', 'targets']);
    }

    private function shouldFallbackToMock(): bool
    {
        $configured = config('scout.ai_analysis.allow_mock_fallback');
        if ($configured === null || $configured === '') {
            return ! app()->environment('production');
        }

        return filter_var($configured, FILTER_VALIDATE_BOOL);
    }
}
