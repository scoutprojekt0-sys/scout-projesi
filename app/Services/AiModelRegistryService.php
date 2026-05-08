<?php

namespace App\Services;

use App\Models\AiActiveModel;
use App\Models\AiModelRollout;
use App\Models\AiTrainingRun;

class AiModelRegistryService
{
    public function publish(string $sport, string $modelVersion, ?string $modelPath = null, ?AiTrainingRun $run = null, ?string $notes = null): AiActiveModel
    {
        $current = AiActiveModel::query()->where('sport', $sport)->first();

        $active = AiActiveModel::query()->updateOrCreate(
            ['sport' => $sport],
            [
                'model_version' => $modelVersion,
                'model_path' => $modelPath,
                'ai_training_run_id' => $run?->id,
                'activated_at' => now(),
            ]
        );

        AiModelRollout::query()->create([
            'sport' => $sport,
            'from_model_version' => $current?->model_version,
            'to_model_version' => $modelVersion,
            'action' => 'publish',
            'model_path' => $modelPath,
            'ai_training_run_id' => $run?->id,
            'notes' => $notes,
            'rolled_out_at' => now(),
        ]);

        return $active;
    }

    public function rollback(string $sport, string $targetModelVersion, ?string $notes = null): AiActiveModel
    {
        $current = AiActiveModel::query()->where('sport', $sport)->firstOrFail();

        $rollout = AiModelRollout::query()
            ->where('sport', $sport)
            ->where('to_model_version', $targetModelVersion)
            ->latest('id')
            ->first();

        $active = AiActiveModel::query()->updateOrCreate(
            ['sport' => $sport],
            [
                'model_version' => $targetModelVersion,
                'model_path' => $rollout?->model_path,
                'ai_training_run_id' => $rollout?->ai_training_run_id,
                'activated_at' => now(),
            ]
        );

        AiModelRollout::query()->create([
            'sport' => $sport,
            'from_model_version' => $current->model_version,
            'to_model_version' => $targetModelVersion,
            'action' => 'rollback',
            'model_path' => $rollout?->model_path,
            'ai_training_run_id' => $rollout?->ai_training_run_id,
            'notes' => $notes,
            'rolled_out_at' => now(),
        ]);

        return $active;
    }

    public function resolveDefaultModelPath(string $sport): string
    {
        return match ($sport) {
            'football' => base_path('ai-worker/models/football_player_ball.pt'),
            'basketball' => base_path('ai-worker/models/basketball_player_ball.pt'),
            'volleyball' => base_path('ai-worker/models/volleyball_player_ball.pt'),
            default => base_path('ai-worker/models/player_ball.pt'),
        };
    }
}
