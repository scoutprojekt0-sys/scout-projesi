<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\RollbackAiModel::class,
        \App\Console\Commands\PublishAiModel::class,
        \App\Console\Commands\TrainApprovedAiDatasetCandidates::class,
        \App\Console\Commands\SyncAiDatasetLabelStatus::class,
        \App\Console\Commands\PrepareApprovedAiDatasetCandidates::class,
        \App\Console\Commands\ExportAiDatasetCandidates::class,
        \App\Console\Commands\ResetLegacyPasswords::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Scheduled commands are registered in routes/console.php via the Schedule facade.
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
