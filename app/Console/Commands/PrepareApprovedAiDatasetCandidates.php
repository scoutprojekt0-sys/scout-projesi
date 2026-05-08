<?php

namespace App\Console\Commands;

use App\Services\AiDatasetExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PrepareApprovedAiDatasetCandidates extends Command
{
    protected $signature = 'ai-dataset:prepare-approved
        {sport : football, basketball, or volleyball}
        {--limit= : Optional approved candidate export limit}
        {--export-only : Only export approved videos, skip dataset prep}
        {--sample-every-seconds=1 : Frame sample interval forwarded to ai:prepare-dataset}
        {--max-seconds=180 : Max seconds per video forwarded to ai:prepare-dataset}';

    protected $description = 'Export approved dataset candidates and prepare dataset frames/label queue.';

    public function handle(AiDatasetExportService $exportService): int
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

        $prepareExit = Artisan::call('ai:prepare-dataset', [
            'sport' => $sport,
            '--skip-sync' => true,
            '--sample-every-seconds' => (string) $this->option('sample-every-seconds'),
            '--max-seconds' => (string) $this->option('max-seconds'),
        ]);
        $this->output->write(Artisan::output());

        if ($prepareExit !== SymfonyCommand::SUCCESS) {
            $this->error('Dataset prep basarisiz oldu.');

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
