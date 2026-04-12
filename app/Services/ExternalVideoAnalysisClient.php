<?php

namespace App\Services;

use App\Models\User;
use App\Models\VideoAnalysis;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExternalVideoAnalysisClient
{
    public function submit(VideoAnalysis $analysis): array
    {
        $baseUrl = $this->resolveBaseUrl();
        $videoClip = $analysis->videoClip;
        $callbackSecret = $this->resolveCallbackSecret();
        $callbackUrl = $this->resolveCallbackUrl($analysis->id);

        $response = Http::timeout((int) config('scout.ai_analysis.worker_timeout_seconds', 20))
            ->acceptJson()
            ->post($baseUrl.'/jobs/video-analysis', [
                'analysis_id' => $analysis->id,
                'video_clip_id' => $analysis->video_clip_id,
                'sport' => $this->inferSport($videoClip?->tags, $analysis->analysis_type),
                'video_url' => $videoClip?->video_url,
                'thumbnail_url' => $videoClip?->thumbnail_url,
                'target_player_id' => $analysis->target_player_id,
                'target_profile' => $this->buildTargetProfile($analysis->target_player_id),
                'requested_by' => $analysis->requested_by,
                'analysis_type' => $analysis->analysis_type,
                'callback_url' => $callbackUrl,
                'callback_secret' => $callbackSecret,
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

    private function resolveCallbackSecret(): string
    {
        $callbackSecret = trim((string) config('scout.ai_analysis.callback_secret', ''));
        if ($callbackSecret !== '') {
            return $callbackSecret;
        }

        if (! app()->environment('production')) {
            return '';
        }

        throw new RuntimeException('AI callback secret tanimli degil.');
    }

    private function resolveCallbackUrl(int $analysisId): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl !== '') {
            return $appUrl.'/api/video-analyses/'.$analysisId.'/callback';
        }

        throw new RuntimeException('APP_URL tanimli degil.');
    }

    private function inferSport(?array $tags, string $analysisType): string
    {
        $map = [
            'futbol' => 'football',
            'football' => 'football',
            'soccer' => 'football',
            'basketbol' => 'basketball',
            'basketball' => 'basketball',
            'voleybol' => 'volleyball',
            'volleyball' => 'volleyball',
        ];

        foreach (($tags ?? []) as $tag) {
            $normalized = strtolower(trim((string) $tag));
            if (isset($map[$normalized])) {
                return $map[$normalized];
            }
        }

        $analysis = strtolower(trim($analysisType));
        foreach ($map as $key => $value) {
            if (str_contains($analysis, $key)) {
                return $value;
            }
        }

        return 'football';
    }

    private function buildTargetProfile(?int $targetPlayerId): ?array
    {
        if (! $targetPlayerId) {
            return null;
        }

        $player = User::query()->with('playerProfile')->find($targetPlayerId);
        if (! $player) {
            return null;
        }

        return array_filter([
            'player_id' => $player->id,
            'name' => $player->name,
            'position' => $player->playerProfile?->position ?? $player->position,
            'height_cm' => $player->playerProfile?->height_cm,
            'dominant_foot' => $player->playerProfile?->dominant_foot,
            'current_team' => $player->playerProfile?->current_team,
            'city' => $player->city,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
