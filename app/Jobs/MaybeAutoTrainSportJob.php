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
        if (! (bool) config('scout.ai_training.auto_train.enabled', true)) {
            return;
        }
        if (! $this->isInsideNightTrainingWindow()) {
            return;
        }

        $lock = Cache::lock('ai-dataset:auto-train:'.$sport, 43200);
        if (! $lock->get()) {
            return;
        }

        try {
            $runService->failStaleRunningRuns($sport, 6);

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
                '--min-images' => max(1, (int) config('scout.ai_training.auto_train.min_images', 100)),
                '--min-annotated' => max(1, (int) config('scout.ai_training.auto_train.min_annotated_frames', 100)),
                '--min-completion' => max(0, (float) config('scout.ai_training.auto_train.min_completion', 60)),
            ]);

            if ($readinessExit !== SymfonyCommand::SUCCESS) {
                return;
            }

            Artisan::call('ai-dataset:train-approved', [
                'sport' => $sport,
                '--device' => (string) config('scout.ai_training.auto_train.device', 'cpu'),
                '--epochs' => (string) max(1, (int) config('scout.ai_training.auto_train.epochs', 25)),
                '--imgsz' => (string) max(1, (int) config('scout.ai_training.auto_train.imgsz', 960)),
                '--batch' => (string) max(1, (int) config('scout.ai_training.auto_train.batch', 8)),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private function isInsideNightTrainingWindow(): bool
    {
        $dailyAt = trim((string) config('scout.ai_training.auto_train.daily_at', '02:00'));
        if (! preg_match('/^\d{2}:\d{2}$/', $dailyAt)) {
            $dailyAt = '02:00';
        }

        [$hour, $minute] = array_map('intval', explode(':', $dailyAt));
        $now = now();
        $start = $now->copy()->setTime($hour, $minute);
        if ($start->greaterThan($now)) {
            $start->subDay();
        }

        $windowMinutes = max(1, (int) config('scout.ai_training.auto_train.night_window_minutes', 90));
        $end = $start->copy()->addMinutes($windowMinutes);

        return $now->betweenIncluded($start, $end);
    }
}
