<?php

namespace App\Console\Commands;

use App\Models\AiTrainingRun;
use App\Services\AiModelRegistryService;
use Illuminate\Console\Command;

class PublishAiModel extends Command
{
    protected $signature = 'ai-model:publish
        {sport : football, basketball, or volleyball}
        {model_version : Model version to mark active}
        {--run-id= : Optional training run id}
        {--model-path= : Optional resolved model file path}
        {--notes= : Optional rollout note}';

    protected $description = 'Publish an active AI model version for a sport.';

    public function handle(AiModelRegistryService $registry): int
    {
        $sport = strtolower(trim((string) $this->argument('sport')));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            $this->error('Sport football, basketball veya volleyball olmali.');

            return self::FAILURE;
        }

        $modelVersion = trim((string) $this->argument('model_version'));
        $runId = $this->option('run-id');
        $run = is_numeric($runId) ? AiTrainingRun::query()->find((int) $runId) : null;
        $modelPath = trim((string) $this->option('model-path'));
        if ($modelPath === '') {
            $modelPath = $registry->resolveDefaultModelPath($sport);
        }

        $active = $registry->publish(
            $sport,
            $modelVersion,
            $modelPath,
            $run,
            $this->option('notes')
        );

        $this->info('Aktif model guncellendi.');
        $this->line('Sport: '.$active->sport);
        $this->line('Model version: '.$active->model_version);
        $this->line('Model path: '.($active->model_path ?? '-'));

        return self::SUCCESS;
    }
}
