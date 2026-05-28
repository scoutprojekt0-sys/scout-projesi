<?php

namespace App\Jobs;

use App\Models\AiDatasetCandidate;
use App\Services\AiDatasetExportService;
use App\Services\AiPseudoLabelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PrepareAiDatasetForSportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $sport,
    ) {
    }

    public function handle(AiDatasetExportService $exportService, AiPseudoLabelService $pseudoLabelService): void
    {
        $sport = strtolower(trim($this->sport));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            return;
        }

        $lock = Cache::lock('ai-dataset:prepare:'.$sport, 900);
        if (! $lock->get()) {
            return;
        }

        try {
            $exportedIds = AiDatasetCandidate::query()
                ->where('sport', $sport)
                ->whereIn('status', [
                    AiDatasetCandidate::STATUS_QUEUED,
                    AiDatasetCandidate::STATUS_APPROVED,
                ])
                ->orderBy('id')
                ->get()
                ->map(function (AiDatasetCandidate $candidate) use ($exportService): ?int {
                    $result = $exportService->exportOne($candidate);

                    return ($result['result'] ?? null) === 'exported'
                        ? (int) ($result['candidate_id'] ?? 0)
                        : null;
                })
                ->filter(static fn (?int $id): bool => $id !== null && $id > 0)
                ->values()
                ->all();

            if ($exportedIds === []) {
                return;
            }

            $prepareExit = Artisan::call('ai:prepare-dataset', [
                'sport' => $sport,
                '--skip-sync' => true,
            ]);

            if ($prepareExit !== SymfonyCommand::SUCCESS) {
                return;
            }

            $pseudoLabelService->writeLabelsForSport($sport);
            $exportService->markCandidatesAsLabeling($exportedIds);
        } finally {
            optional($lock)->release();
        }
    }
}
