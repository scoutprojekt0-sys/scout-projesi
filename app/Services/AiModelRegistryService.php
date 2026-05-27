<?php

namespace App\Services;

use App\Models\AiActiveModel;
use App\Models\AiModelRollout;
use App\Models\AiTrainingRun;
use Illuminate\Support\Facades\File;

class AiModelRegistryService
{
    public function __construct(
        private readonly AiModelValidationService $validationService,
    ) {
    }

    public function canPublish(?AiTrainingRun $run): bool
    {
        if ($run === null) {
            return true;
        }

        if ($run->validation_passed !== true) {
            return false;
        }

        $validationSummary = is_array($run->validation_summary)
            ? (array) ($run->validation_summary['summary'] ?? [])
            : [];

        if ($validationSummary === []) {
            return false;
        }

        $comparison = $this->validationService->compareAgainstActiveBaseline($run->sport, [
            'summary' => $validationSummary,
        ]);

        return (bool) ($comparison['passed'] ?? false);
    }

    public function publish(string $sport, string $modelVersion, ?string $modelPath = null, ?AiTrainingRun $run = null, ?string $notes = null): AiActiveModel
    {
        if (! $this->canPublish($run)) {
            throw new \RuntimeException('Model validation gate gecilemedi. Publish engellendi.');
        }

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
            'football' => 'ai-worker/models/football_player_ball.pt',
            'basketball' => 'ai-worker/models/basketball_player_ball.pt',
            'volleyball' => 'ai-worker/models/volleyball_player_ball.pt',
            default => 'ai-worker/models/player_ball.pt',
        };
    }

    public function resolveInferenceModelPath(string $sport): string
    {
        $active = AiActiveModel::query()->where('sport', $sport)->first();
        $activePath = trim((string) $active?->model_path);
        if ($activePath !== '' && $this->backendPathExists($activePath)) {
            return $activePath;
        }

        $latestRunPath = $this->resolveLatestRunBestPath($sport);
        if ($latestRunPath !== null) {
            return $this->toRelativeBasePath($latestRunPath);
        }

        return $this->resolveDefaultModelPath($sport);
    }

    public function resolveInferenceModelVersion(string $sport): ?string
    {
        $active = AiActiveModel::query()->where('sport', $sport)->first();
        $modelVersion = trim((string) $active?->model_version);

        return $modelVersion !== '' ? $modelVersion : null;
    }

    public function stageLatestRunModelForInference(string $sport, string $modelVersion): string
    {
        $latestRunPath = $this->resolveLatestRunBestPath($sport);
        if ($latestRunPath === null) {
            return $this->resolveDefaultModelPath($sport);
        }

        return $this->stageModelForInference($sport, $modelVersion, $latestRunPath);
    }

    public function resolveLatestRunBestPath(string $sport): ?string
    {
        $runRoot = base_path('runs/'.$sport);
        if (! File::exists($runRoot)) {
            return null;
        }

        $bestPaths = [];
        foreach (File::directories($runRoot) as $runDir) {
            $bestPath = $runDir.DIRECTORY_SEPARATOR.'weights'.DIRECTORY_SEPARATOR.'best.pt';
            if (File::exists($bestPath)) {
                $bestPaths[] = $bestPath;
            }
        }

        if ($bestPaths === []) {
            return null;
        }

        usort($bestPaths, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $bestPaths[0];
    }

    public function stageModelForInference(string $sport, string $modelVersion, string $sourcePath): string
    {
        $resolvedSourcePath = $this->resolveBackendPath($sourcePath);
        if ($resolvedSourcePath === null || ! File::exists($resolvedSourcePath)) {
            return $this->resolveDefaultModelPath($sport);
        }

        $relativeTargetPath = 'storage/app/ai-models/'.$sport.'/'.$modelVersion.'.pt';
        $targetPath = base_path($relativeTargetPath);
        File::ensureDirectoryExists(dirname($targetPath));
        File::copy($resolvedSourcePath, $targetPath);

        return str_replace('\\', '/', $relativeTargetPath);
    }

    private function backendPathExists(string $path): bool
    {
        $resolved = $this->resolveBackendPath($path);

        return $resolved !== null && File::exists($resolved);
    }

    private function resolveBackendPath(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (File::exists($trimmed)) {
            return $trimmed;
        }

        $baseRelative = base_path($trimmed);
        if (File::exists($baseRelative)) {
            return $baseRelative;
        }

        return null;
    }

    private function toRelativeBasePath(string $path): string
    {
        $normalizedBase = str_replace('\\', '/', base_path());
        $normalizedPath = str_replace('\\', '/', $path);

        if (str_starts_with($normalizedPath, $normalizedBase.'/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        return $normalizedPath;
    }
}
