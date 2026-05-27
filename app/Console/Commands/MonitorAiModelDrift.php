<?php

namespace App\Console\Commands;

use App\Services\AiModelMonitoringService;
use Illuminate\Console\Command;

class MonitorAiModelDrift extends Command
{
    protected $signature = 'ai-models:monitor-drift
        {sport? : football|basketball|volleyball}
        {--lookback-hours= : Monitoring penceresi saat cinsinden}
        {--rollback : Drift durumunda otomatik rollback dene}';

    protected $description = 'Aktif AI modelleri icin production drift metriklerini olcer ve gerekirse rollback tetikler.';

    public function handle(AiModelMonitoringService $monitoringService): int
    {
        $sport = $this->argument('sport');
        $sports = $sport ? [$sport] : ['football', 'basketball', 'volleyball'];
        $lookbackHours = $this->option('lookback-hours') !== null
            ? (int) $this->option('lookback-hours')
            : null;
        $allowRollback = (bool) $this->option('rollback');

        foreach ($sports as $currentSport) {
            $result = $monitoringService->monitorSport($currentSport, $lookbackHours, $allowRollback);
            if (($result['ok'] ?? false) !== true) {
                $this->warn($currentSport.': '.($result['reason'] ?? 'monitoring_failed'));
                continue;
            }

            $snapshot = $result['snapshot'];
            $this->info(sprintf(
                '%s model=%s sample=%d drift=%s',
                $currentSport,
                $result['active_model_version'],
                (int) $snapshot->sample_count,
                $snapshot->drift_detected ? 'yes' : 'no'
            ));

            $rollback = $result['rollback'] ?? null;
            if (is_array($rollback) && ($rollback['executed'] ?? false) === true) {
                $this->warn(sprintf(
                    '%s rollback executed -> %s',
                    $currentSport,
                    (string) ($rollback['target_model_version'] ?? 'unknown')
                ));
            }
        }

        return self::SUCCESS;
    }
}
