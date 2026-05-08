<?php

namespace App\Console\Commands;

use App\Services\AiDatasetExportService;
use Illuminate\Console\Command;

class SyncAiDatasetLabelStatus extends Command
{
    protected $signature = 'ai-dataset:sync-label-status {sport : football, basketball, or volleyball}';

    protected $description = 'Sync AI dataset candidate statuses from dataset manifest and label files.';

    public function handle(AiDatasetExportService $service): int
    {
        $sport = strtolower(trim((string) $this->argument('sport')));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            $this->error('Sport football, basketball veya volleyball olmali.');

            return self::FAILURE;
        }

        $result = $service->syncLabeledCandidates($sport);

        $this->table(
            ['candidate_id', 'result', 'annotated_frames', 'total_frames', 'reason'],
            array_map(static fn (array $row) => [
                $row['candidate_id'] ?? '',
                $row['result'] ?? '',
                $row['annotated_frames'] ?? '',
                $row['total_frames'] ?? '',
                $row['reason'] ?? '',
            ], $result['rows'])
        );

        $this->info('Labeled olarak guncellenen aday sayisi: '.$result['updated']);

        return self::SUCCESS;
    }
}
