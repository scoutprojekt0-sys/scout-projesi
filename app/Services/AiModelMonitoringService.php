<?php

namespace App\Services;

use App\Models\AiActiveModel;
use App\Models\AiModelMonitoringSnapshot;
use App\Models\AiModelRollout;
use App\Models\VideoAnalysis;
use Illuminate\Support\Carbon;

class AiModelMonitoringService
{
    public function __construct(
        private readonly AiModelRegistryService $registry,
        private readonly AiModelAlertService $alertService,
    ) {
    }

    public function monitorSport(string $sport, ?int $lookbackHours = null, bool $allowRollback = false): array
    {
        $thresholds = $this->thresholdsForSport($sport);
        $active = AiActiveModel::query()
            ->with('trainingRun')
            ->where('sport', $sport)
            ->first();

        if (! $active) {
            return [
                'ok' => false,
                'reason' => 'no_active_model',
                'sport' => $sport,
            ];
        }

        $hours = $lookbackHours ?? (int) config('scout.ai_training.monitoring.lookback_hours', 24);
        $windowEndedAt = now();
        $windowStartedAt = $windowEndedAt->copy()->subHours($hours);

        $analyses = VideoAnalysis::query()
            ->with('events:id,video_analysis_id,confidence')
            ->where('inference_sport', $sport)
            ->where('inference_model_version', $active->model_version)
            ->where(function ($query) use ($windowStartedAt) {
                $query
                    ->where('completed_at', '>=', $windowStartedAt)
                    ->orWhere('failed_at', '>=', $windowStartedAt)
                    ->orWhere('created_at', '>=', $windowStartedAt);
            })
            ->whereIn('status', ['completed', 'failed'])
            ->orderByDesc('id')
            ->get();

        $metricSummary = $this->buildMetricSummary($analyses, $windowStartedAt, $windowEndedAt);
        $previousSnapshot = AiModelMonitoringSnapshot::query()
            ->where('sport', $sport)
            ->where('model_version', $active->model_version)
            ->latest('id')
            ->first();

        $driftSummary = $this->evaluateDrift(
            $metricSummary,
            $previousSnapshot?->metric_summary ?? null,
            $thresholds
        );

        $snapshot = AiModelMonitoringSnapshot::query()->create([
            'sport' => $sport,
            'model_version' => $active->model_version,
            'sample_count' => (int) $metricSummary['sample_count'],
            'window_started_at' => $windowStartedAt,
            'window_ended_at' => $windowEndedAt,
            'metric_summary' => $metricSummary,
            'drift_summary' => $driftSummary,
            'drift_detected' => (bool) ($driftSummary['drift_detected'] ?? false),
            'auto_rollback_executed' => false,
            'captured_at' => now(),
        ]);

        if ($snapshot->drift_detected) {
            $this->alertService->notifyDriftDetected($snapshot);
        }

        $rollbackResult = null;
        if ($allowRollback && ($driftSummary['drift_detected'] ?? false) === true) {
            $rollbackResult = $this->maybeRollback($active, $snapshot, $thresholds);
        }

        return [
            'ok' => true,
            'sport' => $sport,
            'active_model_version' => $active->model_version,
            'snapshot' => $snapshot,
            'rollback' => $rollbackResult,
        ];
    }

    private function buildMetricSummary($analyses, Carbon $windowStartedAt, Carbon $windowEndedAt): array
    {
        $sampleCount = $analyses->count();
        $completed = $analyses->where('status', 'completed')->values();
        $failed = $analyses->where('status', 'failed')->values();
        $eventCounts = $completed->map(static fn (VideoAnalysis $analysis): int => $analysis->events->count());
        $totalEvents = (int) $eventCounts->sum();
        $noEventCount = (int) $eventCounts->filter(static fn (int $count): bool => $count === 0)->count();
        $confidenceValues = $completed
            ->flatMap(static fn (VideoAnalysis $analysis) => $analysis->events->pluck('confidence'))
            ->filter(static fn ($value): bool => $value !== null)
            ->map(static fn ($value): float => (float) $value)
            ->values();

        return [
            'sample_count' => $sampleCount,
            'completed_count' => $completed->count(),
            'failed_count' => $failed->count(),
            'total_event_count' => $totalEvents,
            'failure_rate' => $sampleCount > 0 ? round($failed->count() / $sampleCount, 4) : 0.0,
            'no_event_rate' => $completed->count() > 0 ? round($noEventCount / $completed->count(), 4) : 0.0,
            'avg_events_per_analysis' => $completed->count() > 0 ? round($totalEvents / $completed->count(), 4) : 0.0,
            'avg_event_confidence' => $confidenceValues->count() > 0 ? round($confidenceValues->avg(), 4) : 0.0,
            'window_started_at' => $windowStartedAt->toIso8601String(),
            'window_ended_at' => $windowEndedAt->toIso8601String(),
        ];
    }

    private function evaluateDrift(array $metrics, ?array $previousMetrics, array $thresholds): array
    {
        $minSampleSize = (int) ($thresholds['min_sample_size'] ?? config('scout.ai_training.monitoring.min_sample_size', 5));
        if (($metrics['sample_count'] ?? 0) < $minSampleSize) {
            return [
                'drift_detected' => false,
                'reason' => 'insufficient_sample_size',
                'threshold_profile' => $thresholds,
                'checks' => [
                    'sample_size' => [
                        'value' => (int) ($metrics['sample_count'] ?? 0),
                        'threshold' => $minSampleSize,
                        'passed' => false,
                    ],
                ],
            ];
        }

        $checks = [
            'failure_rate' => $this->maxCheck(
                (float) ($metrics['failure_rate'] ?? 0.0),
                (float) ($thresholds['max_failure_rate'] ?? config('scout.ai_training.monitoring.max_failure_rate', 0.35))
            ),
            'no_event_rate' => $this->maxCheck(
                (float) ($metrics['no_event_rate'] ?? 0.0),
                (float) ($thresholds['max_no_event_rate'] ?? config('scout.ai_training.monitoring.max_no_event_rate', 0.45))
            ),
            'avg_event_confidence' => $this->minCheck(
                (float) ($metrics['avg_event_confidence'] ?? 0.0),
                (float) ($thresholds['min_avg_event_confidence'] ?? config('scout.ai_training.monitoring.min_avg_event_confidence', 0.55))
            ),
            'avg_events_per_analysis' => $this->minCheck(
                (float) ($metrics['avg_events_per_analysis'] ?? 0.0),
                (float) ($thresholds['min_avg_events_per_analysis'] ?? config('scout.ai_training.monitoring.min_avg_events_per_analysis', 1.0))
            ),
        ];

        $relativeChecks = $this->buildRelativeChecks($metrics, $previousMetrics, $thresholds);

        return [
            'drift_detected' => collect($checks)->contains(static fn (array $row): bool => $row['passed'] === false)
                || collect($relativeChecks)->contains(static fn (array $row): bool => $row['passed'] === false),
            'reason' => 'evaluated',
            'threshold_profile' => $thresholds,
            'checks' => $checks,
            'relative_checks' => $relativeChecks,
        ];
    }

    private function maybeRollback(AiActiveModel $active, AiModelMonitoringSnapshot $snapshot, array $thresholds): ?array
    {
        $requiredWindows = max(1, (int) ($thresholds['consecutive_windows_for_rollback'] ?? config('scout.ai_training.monitoring.consecutive_windows_for_rollback', 2)));
        $recentSnapshots = AiModelMonitoringSnapshot::query()
            ->where('sport', $active->sport)
            ->where('model_version', $active->model_version)
            ->latest('id')
            ->take($requiredWindows)
            ->get();

        if ($recentSnapshots->count() < $requiredWindows || $recentSnapshots->contains(static fn (AiModelMonitoringSnapshot $row): bool => $row->drift_detected !== true)) {
            return [
                'executed' => false,
                'reason' => 'consecutive_drift_threshold_not_met',
                'required_windows' => $requiredWindows,
                'observed_windows' => $recentSnapshots->count(),
            ];
        }

        $targetModelVersion = $this->resolveRollbackTargetVersion($active->sport, $active->model_version);
        if ($targetModelVersion === null) {
            return [
                'executed' => false,
                'reason' => 'rollback_target_missing',
            ];
        }

        $rolledBack = $this->registry->rollback(
            $active->sport,
            $targetModelVersion,
            'Auto rollback: production drift monitoring triggered.'
        );

        $snapshot->forceFill([
            'auto_rollback_executed' => true,
            'rollback_target_model_version' => $targetModelVersion,
        ])->save();

        $this->alertService->notifyRollbackExecuted($snapshot->fresh(), $targetModelVersion);

        return [
            'executed' => true,
            'reason' => 'rollback_executed',
            'target_model_version' => $targetModelVersion,
            'active_model_version' => $rolledBack->model_version,
        ];
    }

    private function resolveRollbackTargetVersion(string $sport, string $currentModelVersion): ?string
    {
        $latestPublish = AiModelRollout::query()
            ->where('sport', $sport)
            ->where('action', 'publish')
            ->where('to_model_version', $currentModelVersion)
            ->latest('id')
            ->first();

        $target = trim((string) ($latestPublish?->from_model_version ?? ''));

        return $target !== '' ? $target : null;
    }

    private function buildRelativeChecks(array $metrics, ?array $previousMetrics, array $thresholds): array
    {
        if (! is_array($previousMetrics) || $previousMetrics === []) {
            return [];
        }

        return [
            'failure_rate_increase' => $this->maxCheck(
                max(0.0, (float) ($metrics['failure_rate'] ?? 0.0) - (float) ($previousMetrics['failure_rate'] ?? 0.0)),
                (float) ($thresholds['max_failure_rate_increase'] ?? config('scout.ai_training.monitoring.max_failure_rate_increase', 0.20))
            ),
            'no_event_rate_increase' => $this->maxCheck(
                max(0.0, (float) ($metrics['no_event_rate'] ?? 0.0) - (float) ($previousMetrics['no_event_rate'] ?? 0.0)),
                (float) ($thresholds['max_no_event_rate_increase'] ?? config('scout.ai_training.monitoring.max_no_event_rate_increase', 0.25))
            ),
            'avg_event_confidence_drop' => $this->maxCheck(
                max(0.0, (float) ($previousMetrics['avg_event_confidence'] ?? 0.0) - (float) ($metrics['avg_event_confidence'] ?? 0.0)),
                (float) ($thresholds['max_confidence_drop'] ?? config('scout.ai_training.monitoring.max_confidence_drop', 0.15))
            ),
            'avg_events_per_analysis_drop' => $this->maxCheck(
                max(0.0, (float) ($previousMetrics['avg_events_per_analysis'] ?? 0.0) - (float) ($metrics['avg_events_per_analysis'] ?? 0.0)),
                (float) ($thresholds['max_events_per_analysis_drop'] ?? config('scout.ai_training.monitoring.max_events_per_analysis_drop', 0.80))
            ),
        ];
    }

    private function thresholdsForSport(string $sport): array
    {
        $defaults = [
            'min_sample_size' => (int) config('scout.ai_training.monitoring.min_sample_size', 5),
            'consecutive_windows_for_rollback' => (int) config('scout.ai_training.monitoring.consecutive_windows_for_rollback', 2),
            'max_failure_rate' => (float) config('scout.ai_training.monitoring.max_failure_rate', 0.35),
            'max_no_event_rate' => (float) config('scout.ai_training.monitoring.max_no_event_rate', 0.45),
            'min_avg_event_confidence' => (float) config('scout.ai_training.monitoring.min_avg_event_confidence', 0.55),
            'min_avg_events_per_analysis' => (float) config('scout.ai_training.monitoring.min_avg_events_per_analysis', 1.0),
            'max_failure_rate_increase' => (float) config('scout.ai_training.monitoring.max_failure_rate_increase', 0.20),
            'max_no_event_rate_increase' => (float) config('scout.ai_training.monitoring.max_no_event_rate_increase', 0.25),
            'max_confidence_drop' => (float) config('scout.ai_training.monitoring.max_confidence_drop', 0.15),
            'max_events_per_analysis_drop' => (float) config('scout.ai_training.monitoring.max_events_per_analysis_drop', 0.80),
        ];

        $sportOverrides = config('scout.ai_training.monitoring.sport_thresholds.'.trim(strtolower($sport)), []);
        if (! is_array($sportOverrides)) {
            return $defaults;
        }

        return array_merge($defaults, array_filter(
            $sportOverrides,
            static fn ($value): bool => $value !== null
        ));
    }

    private function maxCheck(float $value, float $threshold): array
    {
        return [
            'value' => round($value, 4),
            'threshold' => $threshold,
            'passed' => $value <= $threshold,
        ];
    }

    private function minCheck(float $value, float $threshold): array
    {
        return [
            'value' => round($value, 4),
            'threshold' => $threshold,
            'passed' => $value >= $threshold,
        ];
    }
}
