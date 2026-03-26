<?php

namespace App\Services;

use App\Models\VideoAnalysis;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExternalVideoAnalysisClient
{
    public function submit(VideoAnalysis $analysis): array
    {
        $baseUrl = $this->resolveBaseUrl();

        $response = Http::timeout((int) config('scout.ai_analysis.worker_timeout_seconds', 20))
            ->acceptJson()
            ->post($baseUrl.'/jobs/video-analysis', [
                'analysis_id' => $analysis->id,
                'video_clip_id' => $analysis->video_clip_id,
                'video_url' => $analysis->videoClip?->video_url,
                'thumbnail_url' => $analysis->videoClip?->thumbnail_url,
                'target_player_id' => $analysis->target_player_id,
                'requested_by' => $analysis->requested_by,
                'analysis_type' => $analysis->analysis_type,
                'callback_url' => rtrim((string) config('app.url'), '/').'/api/video-analyses/'.$analysis->id.'/callback',
                'callback_secret' => (string) config('scout.ai_analysis.callback_secret'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI worker istegi basarisiz: '.$response->status());
        }

        return $response->json() ?: [];
    }

    private function resolveBaseUrl(): string
    {
        $configuredBaseUrl = trim((string) config('scout.ai_analysis.worker_base_url', ''));
        $normalizedBaseUrl = rtrim($configuredBaseUrl, '/');

        if ($normalizedBaseUrl !== '' && ! str_contains($normalizedBaseUrl, 'ai-worker-production-url')) {
            return $normalizedBaseUrl;
        }

        if (! app()->environment('production')) {
            return 'http://127.0.0.1:8010';
        }

        throw new RuntimeException('AI worker base URL tanimli degil.');
    }
}
