<?php

namespace App\Console\Commands;

use App\Services\AiDatasetExportService;
use App\Services\AiModelRegistryService;
use App\Services\AiModelValidationService;
use App\Services\AiTrainingRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class TrainApprovedAiDatasetCandidates extends Command
{
    protected $signature = 'ai-dataset:train-approved
        {sport : football, basketball, or volleyball}
        {--device=cpu}
        {--epochs=60}
        {--imgsz=960}
        {--batch=8}
        {--force}
        {--model-version= : Optional explicit model version label}';

    protected $description = 'Run sport training and mark dataset candidates as trained on success.';

    public function handle(
        AiDatasetExportService $service,
        AiTrainingRunService $runService,
        AiModelRegistryService $registry,
        AiModelValidationService $validationService
    ): int
    {
        $sport = strtolower(trim((string) $this->argument('sport')));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            $this->error('Sport football, basketball veya volleyball olmali.');

            return self::FAILURE;
        }

        $syncExit = Artisan::call('ai-dataset:sync-label-status', [
            'sport' => $sport,
        ]);
        $this->output->write(Artisan::output());

        if ($syncExit !== SymfonyCommand::SUCCESS) {
            $this->error('Label sync basarisiz oldu.');

            return self::FAILURE;
        }

        $modelVersion = trim((string) $this->option('model-version'));
        if ($modelVersion === '') {
            $modelVersion = sprintf('%s-%s', $sport, now()->format('YmdHis'));
        }

        $candidateIds = $runService->candidateIdsForSport($sport);
        $run = $runService->createRun(
            $sport,
            $modelVersion,
            (string) $this->option('device'),
            (int) $this->option('epochs'),
            (int) $this->option('imgsz'),
            (int) $this->option('batch'),
            (bool) $this->option('force'),
            $candidateIds
        );

        $trainExit = Artisan::call('ai:train-model', [
            'sport' => $sport,
            '--device' => (string) $this->option('device'),
            '--epochs' => (string) $this->option('epochs'),
            '--imgsz' => (string) $this->option('imgsz'),
            '--batch' => (string) $this->option('batch'),
            '--force' => (bool) $this->option('force'),
            '--model-version' => $modelVersion,
        ]);
        $output = Artisan::output();
        $this->output->write($output);

        if ($trainExit !== SymfonyCommand::SUCCESS) {
            $runService->markFailed($run, $output);
            $this->error('Training basarisiz oldu.');

            return self::FAILURE;
        }

        $updated = $service->markSportCandidatesAsTrained($sport, $modelVersion);
        $run = $runService->markCompleted($run, $output);
        $validationSummary = $this->extractValidationSummary($output);
        $run = $runService->attachValidation(
            $run,
            $validationSummary !== null
                ? $validationService->buildRolloutDecision($sport, $validationSummary)
                : [
                    'passed' => false,
                    'checks' => [],
                    'summary' => [],
                    'reason' => 'validation_summary_missing',
                ]
        );
        preg_match('/published_model_path=(.+)/', $output, $publishedModelPathMatch);
        $publishedModelPath = trim((string) ($publishedModelPathMatch[1] ?? ''));
        if (! $registry->canPublish($run)) {
            $this->warn('Validation gate gecilemedi. Model aktif edilmedi.');
            $this->line('Training run id: '.$run->id);
            $this->line('Model version: '.$modelVersion);
            $this->line('Trained olarak isaretlenen aday sayisi: '.$updated);

            return self::SUCCESS;
        }
        if ($publishedModelPath === '') {
            $publishedModelPath = $registry->stageLatestRunModelForInference($sport, $modelVersion);
        }
        $registry->publish(
            $sport,
            $modelVersion,
            $publishedModelPath,
            $run,
            'Auto-published after successful ai-dataset:train-approved run.'
        );

        $this->info('Training tamamlandi.');
        $this->line('Training run id: '.$run->id);
        $this->line('Model version: '.$modelVersion);
        $this->line('Trained olarak isaretlenen aday sayisi: '.$updated);

        return self::SUCCESS;
    }

    private function extractValidationSummary(string $output): ?array
    {
        if (preg_match('/validation_summary=(.+)/', $output, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode(trim((string) $matches[1]), true);

        return is_array($decoded) ? $decoded : null;
    }
}
