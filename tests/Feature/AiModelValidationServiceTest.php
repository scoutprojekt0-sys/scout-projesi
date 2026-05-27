<?php

namespace Tests\Feature;

use App\Models\AiActiveModel;
use App\Models\AiTrainingRun;
use App\Services\AiModelValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_level_threshold_failure_blocks_rollout_decision(): void
    {
        $service = app(AiModelValidationService::class);

        $decision = $service->buildRolloutDecision('football', [
            'map50' => 0.80,
            'map50_95' => 0.60,
            'precision' => 0.78,
            'recall' => 0.74,
            'per_class' => [
                [
                    'class_id' => 0,
                    'class_name' => 'player',
                    'map50' => 0.75,
                    'map50_95' => 0.55,
                    'precision' => 0.70,
                    'recall' => 0.68,
                ],
                [
                    'class_id' => 1,
                    'class_name' => 'ball',
                    'map50' => 0.05,
                    'map50_95' => 0.02,
                    'precision' => 0.08,
                    'recall' => 0.07,
                ],
            ],
        ]);

        $this->assertFalse($decision['passed']);
        $this->assertFalse($decision['threshold_evaluation']['class_checks'][1]['passed']);
    }

    public function test_class_level_baseline_drop_blocks_rollout_decision(): void
    {
        $baselineRun = AiTrainingRun::query()->create([
            'sport' => 'football',
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'model_version' => 'football-baseline',
            'validation_passed' => true,
            'validation_summary' => [
                'passed' => true,
                'summary' => [
                    'map50' => 0.85,
                    'map50_95' => 0.65,
                    'precision' => 0.80,
                    'recall' => 0.78,
                    'per_class' => [
                        [
                            'class_id' => 0,
                            'class_name' => 'player',
                            'map50' => 0.82,
                            'map50_95' => 0.62,
                            'precision' => 0.79,
                            'recall' => 0.77,
                        ],
                        [
                            'class_id' => 1,
                            'class_name' => 'ball',
                            'map50' => 0.78,
                            'map50_95' => 0.58,
                            'precision' => 0.72,
                            'recall' => 0.70,
                        ],
                    ],
                ],
            ],
        ]);

        AiActiveModel::query()->create([
            'sport' => 'football',
            'model_version' => 'football-baseline',
            'model_path' => 'E:/models/football-baseline.pt',
            'ai_training_run_id' => $baselineRun->id,
            'activated_at' => now(),
        ]);

        $service = app(AiModelValidationService::class);
        $decision = $service->buildRolloutDecision('football', [
            'map50' => 0.84,
            'map50_95' => 0.64,
            'precision' => 0.79,
            'recall' => 0.77,
            'per_class' => [
                [
                    'class_id' => 0,
                    'class_name' => 'player',
                    'map50' => 0.81,
                    'map50_95' => 0.61,
                    'precision' => 0.78,
                    'recall' => 0.76,
                ],
                [
                    'class_id' => 1,
                    'class_name' => 'ball',
                    'map50' => 0.60,
                    'map50_95' => 0.40,
                    'precision' => 0.60,
                    'recall' => 0.58,
                ],
            ],
        ]);

        $this->assertFalse($decision['passed']);
        $this->assertFalse($decision['baseline_comparison']['class_checks'][1]['passed']);
    }

    public function test_event_level_threshold_failure_blocks_rollout_decision(): void
    {
        $service = app(AiModelValidationService::class);

        $decision = $service->buildRolloutDecision('football', [
            'map50' => 0.80,
            'map50_95' => 0.60,
            'precision' => 0.78,
            'recall' => 0.74,
            'per_class' => [
                [
                    'class_id' => 0,
                    'class_name' => 'player',
                    'map50' => 0.75,
                    'map50_95' => 0.55,
                    'precision' => 0.70,
                    'recall' => 0.68,
                ],
            ],
            'event_validation' => [
                'available' => true,
                'precision' => 0.22,
                'recall' => 0.18,
                'f1' => 0.19,
                'by_event_type' => [
                    [
                        'event_type' => 'pass',
                        'expected' => 12,
                        'predicted' => 10,
                        'matched' => 4,
                        'precision' => 0.40,
                        'recall' => 0.33,
                        'f1' => 0.36,
                    ],
                ],
            ],
        ]);

        $this->assertFalse($decision['passed']);
        $this->assertFalse($decision['threshold_evaluation']['event_checks']['passed']);
        $this->assertFalse($decision['threshold_evaluation']['event_checks']['checks']['recall']['passed']);
    }

    public function test_event_level_baseline_drop_blocks_rollout_decision(): void
    {
        $baselineRun = AiTrainingRun::query()->create([
            'sport' => 'football',
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'model_version' => 'football-events-baseline',
            'validation_passed' => true,
            'validation_summary' => [
                'passed' => true,
                'summary' => [
                    'map50' => 0.85,
                    'map50_95' => 0.65,
                    'precision' => 0.80,
                    'recall' => 0.78,
                    'per_class' => [
                        [
                            'class_id' => 0,
                            'class_name' => 'player',
                            'map50' => 0.82,
                            'map50_95' => 0.62,
                            'precision' => 0.79,
                            'recall' => 0.77,
                        ],
                    ],
                    'event_validation' => [
                        'available' => true,
                        'precision' => 0.72,
                        'recall' => 0.68,
                        'f1' => 0.70,
                        'by_event_type' => [
                            [
                                'event_type' => 'pass',
                                'expected' => 12,
                                'predicted' => 12,
                                'matched' => 9,
                                'precision' => 0.75,
                                'recall' => 0.75,
                                'f1' => 0.75,
                            ],
                            [
                                'event_type' => 'shot',
                                'expected' => 6,
                                'predicted' => 5,
                                'matched' => 4,
                                'precision' => 0.80,
                                'recall' => 0.67,
                                'f1' => 0.73,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        AiActiveModel::query()->create([
            'sport' => 'football',
            'model_version' => 'football-events-baseline',
            'model_path' => 'E:/models/football-events-baseline.pt',
            'ai_training_run_id' => $baselineRun->id,
            'activated_at' => now(),
        ]);

        $service = app(AiModelValidationService::class);
        $decision = $service->buildRolloutDecision('football', [
            'map50' => 0.84,
            'map50_95' => 0.64,
            'precision' => 0.79,
            'recall' => 0.77,
            'per_class' => [
                [
                    'class_id' => 0,
                    'class_name' => 'player',
                    'map50' => 0.81,
                    'map50_95' => 0.61,
                    'precision' => 0.78,
                    'recall' => 0.76,
                ],
            ],
            'event_validation' => [
                'available' => true,
                'precision' => 0.70,
                'recall' => 0.60,
                'f1' => 0.64,
                'by_event_type' => [
                    [
                        'event_type' => 'pass',
                        'expected' => 12,
                        'predicted' => 11,
                        'matched' => 7,
                        'precision' => 0.64,
                        'recall' => 0.58,
                        'f1' => 0.61,
                    ],
                    [
                        'event_type' => 'shot',
                        'expected' => 6,
                        'predicted' => 5,
                        'matched' => 4,
                        'precision' => 0.80,
                        'recall' => 0.67,
                        'f1' => 0.73,
                    ],
                ],
            ],
        ]);

        $this->assertFalse($decision['passed']);
        $this->assertFalse($decision['baseline_comparison']['event_checks']['passed']);
        $this->assertFalse($decision['baseline_comparison']['event_checks']['event_type_checks'][0]['passed']);
    }
}
