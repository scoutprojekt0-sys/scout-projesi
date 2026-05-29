<?php

namespace App\Console\Commands;

use App\Services\AiDatasetExportService;
use App\Services\AiPseudoLabelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PrepareApprovedAiDatasetCandidates extends Command
{
    protected $signature = 'ai-dataset:prepare-approved
        {sport : football, basketball, or volleyball}
        {--limit= : Optional approved candidate export limit}
        {--export-only : Only export approved videos, skip dataset prep}
        {--sample-every-seconds= : Frame sample interval forwarded to ai:prepare-dataset}
        {--max-seconds= : Max seconds per video forwarded to ai:prepare-dataset}';

    protected $description = 'Export approved dataset candidates and prepare dataset frames/label queue.';

    public function handle(AiDatasetExportService $exportService, AiPseudoLabelService $pseudoLabelService): int
    {
        $sport = strtolower(trim((string) $this->argument('sport')));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            $this->error('Sport football, basketball veya volleyball olmali.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limitValue = is_numeric($limit) ? (int) $limit : null;

        $this->info('1/2 Approved aday videolar export ediliyor...');
        $exportResult = $exportService->exportApprovedCandidates($sport, $limitValue);

        $this->table(
            ['candidate_id', 'video_clip_id', 'split', 'result', 'reason'],
            array_map(static fn (array $row) => [
                $row['candidate_id'] ?? '',
                $row['video_clip_id'] ?? '',
                $row['split'] ?? '',
                $row['result'] ?? '',
                $row['reason'] ?? '',
            ], $exportResult['rows'])
        );

        $this->line('Exported: '.$exportResult['exported']);
        $this->line('Skipped: '.$exportResult['skipped']);

        if ($this->option('export-only')) {
            $this->info('Export-only modu: dataset prep atlandi.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('2/2 Dataset prep ve label queue yenileme basliyor...');

        $sampleEverySeconds = $this->option('sample-every-seconds');
        $sampleEverySeconds = is_numeric($sampleEverySeconds)
            ? (int) $sampleEverySeconds
            : (int) config('scout.ai_training.continuous_learning.dataset_sample_every_seconds', 2);
        $maxSeconds = $this->option('max-seconds');
        $maxSeconds = is_numeric($maxSeconds)
            ? (int) $maxSeconds
            : (int) config('scout.ai_training.continuous_learning.dataset_max_seconds', 60);

        $prepareExit = Artisan::call('ai:prepare-dataset', [
            'sport' => $sport,
            '--skip-sync' => true,
            '--sample-every-seconds' => (string) max(1, $sampleEverySeconds),
            '--max-seconds' => (string) max(1, $maxSeconds),
        ]);
        $this->output->write(Artisan::output());

        if ($prepareExit !== SymfonyCommand::SUCCESS) {
            $this->error('Dataset prep basarisiz oldu.');

            return self::FAILURE;
        }

        $pseudoResult = $pseudoLabelService->writeLabelsForSport($sport, $limitValue);
        $this->line('Pseudo-label yazilan aday sayisi: '.$pseudoResult['updated']);
        $purgeResult = $exportService->purgeUnannotatedFrames($sport);
        $this->line('Annotated frame korundu: '.$purgeResult['kept']);
        $this->line('Bos label frame silindi: '.$purgeResult['removed']);

        $queueExit = Artisan::call('ai:dataset-label-queue', [
            'sport' => $sport,
            '--split' => 'all',
        ]);
        $this->output->write(Artisan::output());

        if ($queueExit !== SymfonyCommand::SUCCESS) {
            $this->error('Cleanup sonrasi label queue yenilenemedi.');

            return self::FAILURE;
        }

        $exportedIds = collect($exportResult['rows'])
            ->filter(static fn (array $row): bool => ($row['result'] ?? '') === 'exported')
            ->map(static fn (array $row): int => (int) $row['candidate_id'])
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $updated = $exportService->markCandidatesAsLabeling($exportedIds);

        $this->newLine();
        $this->info('Hazirlandi.');
        $this->line('Labeling durumuna cekilen aday sayisi: '.$updated);

        return self::SUCCESS;
    }
}
