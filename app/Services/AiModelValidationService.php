<?php

namespace App\Services;

use App\Models\AiActiveModel;
use App\Models\AiTrainingRun;

class AiModelValidationService
{
    private const METRICS = ['map50', 'map50_95', 'precision', 'recall'];

    private const EVENT_METRICS = ['precision', 'recall', 'f1'];

    public function thresholds(string $sport): array
    {
        $default = (array) config('scout.ai_training.validation.default_thresholds', []);
        $sportSpecific = (array) config("scout.ai_training.validation.sport_thresholds.{$sport}", []);

        return array_merge([
            'map50' => 0.45,
            'map50_95' => 0.25,
            'precision' => 0.45,
            'recall' => 0.40,
        ], $default, $sportSpecific);
    }

    public function classThresholds(string $sport): array
    {
        $default = (array) config('scout.ai_training.validation.default_class_thresholds', []);
        $sportSpecific = (array) config("scout.ai_training.validation.sport_class_thresholds.{$sport}", []);

        return array_merge([
            'map50' => 0.20,
            'map50_95' => 0.10,
            'precision' => 0.20,
            'recall' => 0.20,
        ], $default, $sportSpecific);
    }

    public function pseudoLabelThreshold(): float
    {
        return (float) config('scout.ai_training.pseudo_label.min_confidence', 0.65);
    }

    public function eventThresholds(string $sport): array
    {
        $default = (array) config('scout.ai_training.validation.default_event_thresholds', []);
        $sportSpecific = (array) config("scout.ai_training.validation.sport_event_thresholds.{$sport}", []);

        return array_merge([
            'precision' => 0.35,
            'recall' => 0.30,
            'f1' => 0.30,
        ], $default, $sportSpecific);
    }

    public function eventTypeThresholds(string $sport): array
    {
        $default = (array) config('scout.ai_training.validation.default_event_type_thresholds', []);
        $sportSpecific = (array) config("scout.ai_training.validation.sport_event_type_thresholds.{$sport}", []);

        return array_merge([
            'precision' => 0.20,
            'recall' => 0.20,
            'f1' => 0.20,
        ], $default, $sportSpecific);
    }

    public function evaluate(string $sport, array $summary): array
    {
        $thresholds = $this->thresholds($sport);
        $checks = [];

        foreach (self::METRICS as $metric) {
            $value = isset($summary[$metric]) ? (float) $summary[$metric] : null;
            $threshold = isset($thresholds[$metric]) ? (float) $thresholds[$metric] : null;
            $checks[$metric] = [
                'value' => $value,
                'threshold' => $threshold,
                'passed' => $value !== null && $threshold !== null ? $value >= $threshold : false,
            ];
        }

        $classThresholds = $this->classThresholds($sport);
        $classChecks = [];
        foreach ((array) ($summary['per_class'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $metricChecks = [];
            foreach (self::METRICS as $metric) {
                $value = isset($row[$metric]) ? (float) $row[$metric] : null;
                $threshold = isset($classThresholds[$metric]) ? (float) $classThresholds[$metric] : null;
                $metricChecks[$metric] = [
                    'value' => $value,
                    'threshold' => $threshold,
                    'passed' => $value !== null && $threshold !== null ? $value >= $threshold : false,
                ];
            }

            $classChecks[] = [
                'class_id' => $row['class_id'] ?? null,
                'class_name' => $row['class_name'] ?? null,
                'metrics' => $metricChecks,
                'passed' => collect($metricChecks)->every(static fn (array $check): bool => $check['passed'] === true),
            ];
        }

        $eventEvaluation = $this->evaluateEventValidation($sport, (array) ($summary['event_validation'] ?? []));

        return [
            'passed' => collect($checks)->every(static fn (array $check): bool => $check['passed'] === true)
                && collect($classChecks)->every(static fn (array $check): bool => $check['passed'] === true)
                && ($eventEvaluation['passed'] ?? true),
            'checks' => $checks,
            'class_checks' => $classChecks,
            'event_checks' => $eventEvaluation,
            'summary' => $summary,
        ];
    }

    public function evaluatePseudoLabelConfidence(?float $confidence): array
    {
        $threshold = $this->pseudoLabelThreshold();

        return [
            'confidence' => $confidence,
            'threshold' => $threshold,
            'promotion_eligible' => $confidence !== null && $confidence >= $threshold,
            'requires_manual_review' => $confidence === null || $confidence < $threshold,
        ];
    }

    public function rolloutTolerance(string $sport): array
    {
        $default = (array) config('scout.ai_training.rollout.default_tolerance', []);
        $sportSpecific = (array) config("scout.ai_training.rollout.sport_tolerance.{$sport}", []);

        return array_merge([
            'map50' => 0.02,
            'map50_95' => 0.02,
            'precision' => 0.03,
            'recall' => 0.03,
        ], $default, $sportSpecific);
    }

    public function classRolloutTolerance(string $sport): array
    {
        $default = (array) config('scout.ai_training.rollout.default_class_tolerance', []);
        $sportSpecific = (array) config("scout.ai_training.rollout.sport_class_tolerance.{$sport}", []);

        return array_merge([
            'map50' => 0.05,
            'map50_95' => 0.05,
            'precision' => 0.07,
            'recall' => 0.07,
        ], $default, $sportSpecific);
    }

    public function eventRolloutTolerance(string $sport): array
    {
        $default = (array) config('scout.ai_training.rollout.default_event_tolerance', []);
        $sportSpecific = (array) config("scout.ai_training.rollout.sport_event_tolerance.{$sport}", []);

        return array_merge([
            'precision' => 0.08,
            'recall' => 0.08,
            'f1' => 0.08,
        ], $default, $sportSpecific);
    }

    public function eventTypeRolloutTolerance(string $sport): array
    {
        $default = (array) config('scout.ai_training.rollout.default_event_type_tolerance', []);
        $sportSpecific = (array) config("scout.ai_training.rollout.sport_event_type_tolerance.{$sport}", []);

        return array_merge([
            'precision' => 0.12,
            'recall' => 0.12,
            'f1' => 0.12,
        ], $default, $sportSpecific);
    }

    public function compareAgainstActiveBaseline(string $sport, array $candidateValidation): array
    {
        $active = AiActiveModel::query()
            ->with('trainingRun')
            ->where('sport', $sport)
            ->first();

        $baselineRun = $active?->trainingRun;
        $baselineSummary = is_array($baselineRun?->validation_summary)
            ? (array) ($baselineRun->validation_summary['summary'] ?? [])
            : [];

        if ($baselineRun === null || $baselineSummary === []) {
            return [
                'passed' => true,
                'reason' => 'no_active_baseline',
                'checks' => [],
                'class_checks' => [],
            ];
        }

        $candidateSummary = (array) ($candidateValidation['summary'] ?? []);
        $tolerance = $this->rolloutTolerance($sport);
        $checks = [];

        foreach (self::METRICS as $metric) {
            $candidateValue = isset($candidateSummary[$metric]) ? (float) $candidateSummary[$metric] : null;
            $baselineValue = isset($baselineSummary[$metric]) ? (float) $baselineSummary[$metric] : null;
            $allowedDrop = isset($tolerance[$metric]) ? (float) $tolerance[$metric] : 0.0;

            $checks[$metric] = [
                'candidate' => $candidateValue,
                'baseline' => $baselineValue,
                'allowed_drop' => $allowedDrop,
                'passed' => $candidateValue !== null && $baselineValue !== null
                    ? $candidateValue >= ($baselineValue - $allowedDrop)
                    : true,
            ];
        }

        $baselinePerClass = [];
        foreach ((array) ($baselineSummary['per_class'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['class_name'])) {
                continue;
            }
            $baselinePerClass[(string) $row['class_name']] = $row;
        }

        $classTolerance = $this->classRolloutTolerance($sport);
        $classChecks = [];
        foreach ((array) ($candidateSummary['per_class'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['class_name'])) {
                continue;
            }
            $className = (string) $row['class_name'];
            $baselineRow = $baselinePerClass[$className] ?? null;
            if (! is_array($baselineRow)) {
                continue;
            }

            $metricChecks = [];
            foreach (self::METRICS as $metric) {
                $candidateValue = isset($row[$metric]) ? (float) $row[$metric] : null;
                $baselineValue = isset($baselineRow[$metric]) ? (float) $baselineRow[$metric] : null;
                $allowedDrop = isset($classTolerance[$metric]) ? (float) $classTolerance[$metric] : 0.0;
                $metricChecks[$metric] = [
                    'candidate' => $candidateValue,
                    'baseline' => $baselineValue,
                    'allowed_drop' => $allowedDrop,
                    'passed' => $candidateValue !== null && $baselineValue !== null
                        ? $candidateValue >= ($baselineValue - $allowedDrop)
                        : true,
                ];
            }

            $classChecks[] = [
                'class_name' => $className,
                'class_id' => $row['class_id'] ?? null,
                'metrics' => $metricChecks,
                'passed' => collect($metricChecks)->every(static fn (array $check): bool => $check['passed'] === true),
            ];
        }

        $eventComparison = $this->compareEventValidationAgainstBaseline(
            $sport,
            (array) ($candidateSummary['event_validation'] ?? []),
            (array) ($baselineSummary['event_validation'] ?? [])
        );

        return [
            'passed' => collect($checks)->every(static fn (array $check): bool => $check['passed'] === true)
                && collect($classChecks)->every(static fn (array $check): bool => $check['passed'] === true)
                && ($eventComparison['passed'] ?? true),
            'reason' => 'active_baseline_comparison',
            'checks' => $checks,
            'class_checks' => $classChecks,
            'event_checks' => $eventComparison,
            'baseline_model_version' => $active?->model_version,
            'baseline_run_id' => $baselineRun?->id,
        ];
    }

    public function buildRolloutDecision(string $sport, array $summary): array
    {
        $thresholdEvaluation = $this->evaluate($sport, $summary);
        $baselineComparison = $this->compareAgainstActiveBaseline($sport, $thresholdEvaluation);

        return [
            'passed' => ($thresholdEvaluation['passed'] ?? false) && ($baselineComparison['passed'] ?? false),
            'threshold_evaluation' => $thresholdEvaluation,
            'baseline_comparison' => $baselineComparison,
            'summary' => $summary,
        ];
    }

    private function evaluateEventValidation(string $sport, array $eventValidation): array
    {
        if (($eventValidation['available'] ?? false) !== true) {
            return [
                'available' => false,
                'passed' => true,
                'reason' => 'event_validation_unavailable',
                'checks' => [],
                'event_type_checks' => [],
            ];
        }

        $thresholds = $this->eventThresholds($sport);
        $checks = [];
        foreach (self::EVENT_METRICS as $metric) {
            $value = isset($eventValidation[$metric]) ? (float) $eventValidation[$metric] : null;
            $threshold = isset($thresholds[$metric]) ? (float) $thresholds[$metric] : null;
            $checks[$metric] = [
                'value' => $value,
                'threshold' => $threshold,
                'passed' => $value !== null && $threshold !== null ? $value >= $threshold : false,
            ];
        }

        $eventTypeThresholds = $this->eventTypeThresholds($sport);
        $eventTypeChecks = [];
        foreach ((array) ($eventValidation['by_event_type'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['event_type'])) {
                continue;
            }

            $metricChecks = [];
            foreach (self::EVENT_METRICS as $metric) {
                $value = isset($row[$metric]) ? (float) $row[$metric] : null;
                $threshold = isset($eventTypeThresholds[$metric]) ? (float) $eventTypeThresholds[$metric] : null;
                $metricChecks[$metric] = [
                    'value' => $value,
                    'threshold' => $threshold,
                    'passed' => $value !== null && $threshold !== null ? $value >= $threshold : false,
                ];
            }

            $eventTypeChecks[] = [
                'event_type' => (string) $row['event_type'],
                'expected' => isset($row['expected']) ? (int) $row['expected'] : null,
                'predicted' => isset($row['predicted']) ? (int) $row['predicted'] : null,
                'matched' => isset($row['matched']) ? (int) $row['matched'] : null,
                'metrics' => $metricChecks,
                'passed' => collect($metricChecks)->every(static fn (array $check): bool => $check['passed'] === true),
            ];
        }

        return [
            'available' => true,
            'passed' => collect($checks)->every(static fn (array $check): bool => $check['passed'] === true)
                && collect($eventTypeChecks)->every(static fn (array $check): bool => $check['passed'] === true),
            'reason' => 'event_validation_available',
            'checks' => $checks,
            'event_type_checks' => $eventTypeChecks,
        ];
    }

    private function compareEventValidationAgainstBaseline(string $sport, array $candidate, array $baseline): array
    {
        if (($candidate['available'] ?? false) !== true || ($baseline['available'] ?? false) !== true) {
            return [
                'available' => false,
                'passed' => true,
                'reason' => 'event_validation_unavailable',
                'checks' => [],
                'event_type_checks' => [],
            ];
        }

        $tolerance = $this->eventRolloutTolerance($sport);
        $checks = [];
        foreach (self::EVENT_METRICS as $metric) {
            $candidateValue = isset($candidate[$metric]) ? (float) $candidate[$metric] : null;
            $baselineValue = isset($baseline[$metric]) ? (float) $baseline[$metric] : null;
            $allowedDrop = isset($tolerance[$metric]) ? (float) $tolerance[$metric] : 0.0;
            $checks[$metric] = [
                'candidate' => $candidateValue,
                'baseline' => $baselineValue,
                'allowed_drop' => $allowedDrop,
                'passed' => $candidateValue !== null && $baselineValue !== null
                    ? $candidateValue >= ($baselineValue - $allowedDrop)
                    : true,
            ];
        }

        $baselineRows = [];
        foreach ((array) ($baseline['by_event_type'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['event_type'])) {
                continue;
            }
            $baselineRows[(string) $row['event_type']] = $row;
        }

        $eventTypeTolerance = $this->eventTypeRolloutTolerance($sport);
        $eventTypeChecks = [];
        foreach ((array) ($candidate['by_event_type'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['event_type'])) {
                continue;
            }

            $eventType = (string) $row['event_type'];
            $baselineRow = $baselineRows[$eventType] ?? null;
            if (! is_array($baselineRow)) {
                continue;
            }

            $metricChecks = [];
            foreach (self::EVENT_METRICS as $metric) {
                $candidateValue = isset($row[$metric]) ? (float) $row[$metric] : null;
                $baselineValue = isset($baselineRow[$metric]) ? (float) $baselineRow[$metric] : null;
                $allowedDrop = isset($eventTypeTolerance[$metric]) ? (float) $eventTypeTolerance[$metric] : 0.0;
                $metricChecks[$metric] = [
                    'candidate' => $candidateValue,
                    'baseline' => $baselineValue,
                    'allowed_drop' => $allowedDrop,
                    'passed' => $candidateValue !== null && $baselineValue !== null
                        ? $candidateValue >= ($baselineValue - $allowedDrop)
                        : true,
                ];
            }

            $eventTypeChecks[] = [
                'event_type' => $eventType,
                'matched' => isset($row['matched']) ? (int) $row['matched'] : null,
                'expected' => isset($row['expected']) ? (int) $row['expected'] : null,
                'predicted' => isset($row['predicted']) ? (int) $row['predicted'] : null,
                'metrics' => $metricChecks,
                'passed' => collect($metricChecks)->every(static fn (array $check): bool => $check['passed'] === true),
            ];
        }

        return [
            'available' => true,
            'passed' => collect($checks)->every(static fn (array $check): bool => $check['passed'] === true)
                && collect($eventTypeChecks)->every(static fn (array $check): bool => $check['passed'] === true),
            'reason' => 'event_validation_baseline_comparison',
            'checks' => $checks,
            'event_type_checks' => $eventTypeChecks,
        ];
    }
}
