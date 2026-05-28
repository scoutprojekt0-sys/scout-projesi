<?php

namespace App\Console\Commands;

use App\Services\AiPseudoLabelService;
use Illuminate\Console\Command;

class WriteAiPseudoLabels extends Command
{
    protected $signature = 'ai-dataset:pseudo-label
        {sport : football, basketball, or volleyball}
        {--limit= : Optional candidate limit}';

    protected $description = 'Write safe pseudo-labels from completed video analysis events into dataset label files.';

    public function handle(AiPseudoLabelService $service): int
    {
        $sport = strtolower(trim((string) $this->argument('sport')));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            $this->error('Sport football, basketball veya volleyball olmali.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $result = $service->writeLabelsForSport($sport, is_numeric($limit) ? (int) $limit : null);

        $this->table(
            ['candidate_id', 'result', 'written_labels', 'total_frames', 'reason'],
            array_map(static fn (array $row) => [
                $row['candidate_id'] ?? '',
                $row['result'] ?? '',
                $row['written_labels'] ?? '',
                $row['total_frames'] ?? '',
                $row['reason'] ?? '',
            ], $result['rows'])
        );

        $this->info('Pseudo-label yazilan aday sayisi: '.$result['updated']);

        return self::SUCCESS;
    }
}
