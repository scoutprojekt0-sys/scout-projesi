<?php

namespace App\Console\Commands;

use App\Services\AiDatasetExportService;
use Illuminate\Console\Command;

class PurgeUnannotatedAiDatasetFrames extends Command
{
    protected $signature = 'ai-dataset:purge-unannotated
        {sport : football, basketball, volleyball, or all}';

    protected $description = 'Remove dataset frames whose label files are empty and rewrite dataset manifests.';

    public function handle(AiDatasetExportService $exportService): int
    {
        $sport = strtolower(trim((string) $this->argument('sport')));
        $sports = $sport === 'all' ? ['football', 'basketball', 'volleyball'] : [$sport];

        foreach ($sports as $currentSport) {
            if (! in_array($currentSport, ['football', 'basketball', 'volleyball'], true)) {
                $this->error('Sport football, basketball, volleyball veya all olmali.');

                return self::FAILURE;
            }
        }

        foreach ($sports as $currentSport) {
            $result = $exportService->purgeUnannotatedFrames($currentSport);

            $this->line(sprintf(
                '%s: kept=%d removed=%d manifest=%s',
                $currentSport,
                (int) $result['kept'],
                (int) $result['removed'],
                (string) ($result['manifest'] ?? '-')
            ));
        }

        return self::SUCCESS;
    }
}
