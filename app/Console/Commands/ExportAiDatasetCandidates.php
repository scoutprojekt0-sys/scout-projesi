<?php

namespace App\Console\Commands;

use App\Services\AiDatasetExportService;
use Illuminate\Console\Command;

class ExportAiDatasetCandidates extends Command
{
    protected $signature = 'ai-dataset:export-candidates
        {sport? : Optional sport filter (football, basketball, volleyball)}
        {--limit= : Optional candidate limit}';

    protected $description = 'Export approved AI dataset candidates into raw_videos manifests.';

    public function handle(AiDatasetExportService $exportService): int
    {
        $sport = $this->argument('sport');
        if ($sport !== null && ! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            $this->error('Sport football, basketball veya volleyball olmali.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limitValue = is_numeric($limit) ? (int) $limit : null;

        $result = $exportService->exportApprovedCandidates($sport, $limitValue);

        $this->table(
            ['candidate_id', 'video_clip_id', 'sport', 'split', 'result', 'reason'],
            array_map(static fn (array $row) => [
                $row['candidate_id'] ?? '',
                $row['video_clip_id'] ?? '',
                $row['sport'] ?? '',
                $row['split'] ?? '',
                $row['result'] ?? '',
                $row['reason'] ?? '',
            ], $result['rows'])
        );

        $this->info('Exported: '.$result['exported']);
        $this->info('Skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
