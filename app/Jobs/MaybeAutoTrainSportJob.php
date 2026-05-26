<?php

namespace App\Jobs;

use App\Models\AiTrainingRun;
use App\Services\AiTrainingRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class MaybeAutoTrainSportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $sport,
    ) {
    }

    public function handle(AiTrainingRunService $runService): void
    {
        $sport = strtolower(trim($this->sport));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            return;
        }

        $lock = Cache::lock('ai-dataset:auto-train:'.$sport, 3600);
        if (! $lock->get()) {
            return;
        }

        try {
            $runningExists = AiTrainingRun::query()
                ->where('sport', $sport)
                ->where('status', AiTrainingRun::STATUS_RUNNING)
                ->exists();

            if ($runningExists) {
                return;
            }

            Artisan::call('ai-dataset:sync-label-status', [
                'sport' => $sport,
            ]);

            if ($runService->candidateIdsForSport($sport) === []) {
                return;
            }

            $readinessExit = Artisan::call('ai:training-readiness', [
                'sport' => $sport,
            ]);

            if ($readinessExit !== SymfonyCommand::SUCCESS) {
                return;
            }

            Artisan::call('ai-dataset:train-approved', [
                'sport' => $sport,
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
