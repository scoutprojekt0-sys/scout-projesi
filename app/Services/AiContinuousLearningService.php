<?php

namespace App\Services;

use App\Jobs\MaybeAutoTrainSportJob;
use App\Jobs\PrepareAiDatasetForSportJob;
use App\Jobs\RunVideoAnalysisJob;
use App\Models\AiDatasetCandidate;
use App\Models\VideoAnalysis;
use App\Models\VideoClip;

class AiContinuousLearningService
{
    public function onCandidateQueued(AiDatasetCandidate $candidate): void
    {
        PrepareAiDatasetForSportJob::dispatch($candidate->sport);
        MaybeAutoTrainSportJob::dispatch($candidate->sport);
    }

    public function onVideoUploaded(VideoClip $clip, ?AiDatasetCandidate $candidate = null): void
    {
        if (! (bool) config('scout.ai_training.continuous_learning.auto_analyze_uploads', true)) {
            return;
        }

        $candidate ??= AiDatasetCandidate::query()
            ->where('video_clip_id', $clip->id)
            ->first();

        if (! $candidate) {
            return;
        }

        $existingAnalysis = VideoAnalysis::query()
            ->where('video_clip_id', $clip->id)
            ->where('target_player_id', $clip->user_id)
            ->where('analysis_type', 'continuous_learning')
            ->whereIn('status', ['queued', 'processing', 'completed'])
            ->latest('id')
            ->first();

        if ($existingAnalysis) {
            return;
        }

        $mode = (string) config('scout.ai_analysis.mode', 'external');
        $analysis = VideoAnalysis::query()->create([
            'video_clip_id' => $clip->id,
            'requested_by' => $clip->user_id,
            'target_player_id' => $clip->user_id,
            'analysis_type' => 'continuous_learning',
            'provider' => $mode === 'external' ? 'external' : 'mock',
            'status' => 'queued',
            'worker_status' => 'queued',
            'analysis_version' => $mode === 'external' ? 'external-worker' : 'mock-v1',
        ]);

        RunVideoAnalysisJob::dispatch($analysis->id);
    }

    public function onAnalysisCompleted(VideoAnalysis $analysis): void
    {
        if (! (bool) config('scout.ai_training.continuous_learning.auto_promote_confident_analysis', true)) {
            return;
        }

        $candidate = AiDatasetCandidate::query()
            ->where('video_clip_id', $analysis->video_clip_id)
            ->first();

        if (! $candidate || ! in_array($candidate->status, [
            AiDatasetCandidate::STATUS_QUEUED,
            AiDatasetCandidate::STATUS_LABELING,
            AiDatasetCandidate::STATUS_LABELED,
        ], true)) {
            return;
        }

        $decision = $this->buildPromotionDecision($analysis, $candidate);
        $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
        $metadata['continuous_learning'] = [
            ...((array) ($metadata['continuous_learning'] ?? [])),
            'last_analysis_id' => $analysis->id,
            'promotion_decision' => $decision,
            'evaluated_at' => now()->toIso8601String(),
        ];

        if (($decision['promotion_eligible'] ?? false) !== true) {
            $candidate->forceFill(['metadata' => $metadata])->save();

            return;
        }

        $candidate->forceFill([
            'status' => AiDatasetCandidate::STATUS_APPROVED,
            'metadata' => $metadata,
            'labeled_at' => $candidate->labeled_at ?? now(),
            'reviewed_at' => now(),
            'notes' => trim(((string) $candidate->notes)."\nAuto-approved by continuous learning confidence gate."),
        ])->save();

        MaybeAutoTrainSportJob::dispatch($candidate->sport);
    }

    public function onCandidateReviewed(AiDatasetCandidate $candidate): void
    {
        if (! in_array($candidate->status, [
            AiDatasetCandidate::STATUS_LABELED,
            AiDatasetCandidate::STATUS_APPROVED,
        ], true)) {
            return;
        }

        PrepareAiDatasetForSportJob::dispatch($candidate->sport);
        MaybeAutoTrainSportJob::dispatch($candidate->sport);
    }

    public function onLabelSaved(string $sport): void
    {
        MaybeAutoTrainSportJob::dispatch($sport);
    }

    private function buildPromotionDecision(VideoAnalysis $analysis, AiDatasetCandidate $candidate): array
    {
        $analysis->loadMissing('events');

        $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
        $qualityFlags = (array) data_get($metadata, 'quality_profile.flags', []);
        $eventCount = $analysis->events->count();
        $confidenceValues = $analysis->events
            ->pluck('confidence')
            ->filter(static fn ($value): bool => $value !== null)
            ->map(static fn ($value): float => (float) $value)
            ->values();

        $avgConfidence = $confidenceValues->count() > 0
            ? round((float) $confidenceValues->avg(), 4)
            : 0.0;
        $minConfidence = (float) config(
            'scout.ai_training.continuous_learning.min_avg_event_confidence',
            config('scout.ai_training.pseudo_label.min_confidence', 0.65)
        );
        $minEvents = (int) config('scout.ai_training.continuous_learning.min_events', 3);
        $allowMockPromotion = (bool) config('scout.ai_training.continuous_learning.allow_mock_promotion', ! app()->environment('production'));
        $rawOutput = (array) ($analysis->raw_output ?? []);
        $isMock = $analysis->provider === 'mock'
            || str_contains((string) $analysis->analysis_version, 'mock')
            || ($rawOutput['fallback_mode'] ?? null) === 'mock';

        $blockingReasons = [];
        if ($analysis->status !== 'completed') {
            $blockingReasons[] = 'analysis_not_completed';
        }
        if ($eventCount < $minEvents) {
            $blockingReasons[] = 'insufficient_event_count';
        }
        if ($avgConfidence < $minConfidence) {
            $blockingReasons[] = 'low_avg_event_confidence';
        }
        if ($isMock && ! $allowMockPromotion) {
            $blockingReasons[] = 'mock_analysis_not_promotable';
        }
        if (in_array('short_clip', $qualityFlags, true)) {
            $blockingReasons[] = 'short_clip';
        }

        return [
            'promotion_eligible' => $blockingReasons === [],
            'blocking_reasons' => $blockingReasons,
            'event_count' => $eventCount,
            'min_events' => $minEvents,
            'avg_event_confidence' => $avgConfidence,
            'min_avg_event_confidence' => $minConfidence,
            'provider' => $analysis->provider,
            'analysis_version' => $analysis->analysis_version,
            'quality_flags' => $qualityFlags,
        ];
    }
}
