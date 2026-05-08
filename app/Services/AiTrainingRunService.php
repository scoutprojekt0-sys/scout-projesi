<?php

namespace App\Services;

use App\Models\AiDatasetCandidate;
use App\Models\AiTrainingRun;

class AiTrainingRunService
{
    /**
     * @param  list<int>  $candidateIds
     */
    public function createRun(
        string $sport,
        string $modelVersion,
        string $device,
        int $epochs,
        int $imgsz,
        int $batch,
        bool $forced,
        array $candidateIds
    ): AiTrainingRun {
        $run = AiTrainingRun::query()->create([
            'sport' => $sport,
            'status' => AiTrainingRun::STATUS_RUNNING,
            'model_version' => $modelVersion,
            'device' => $device,
            'epochs' => $epochs,
            'imgsz' => $imgsz,
            'batch' => $batch,
            'forced' => $forced,
            'candidate_count' => count($candidateIds),
            'candidate_ids' => $candidateIds,
            'started_at' => now(),
        ]);

        if ($candidateIds !== []) {
            $run->candidates()->syncWithoutDetaching($candidateIds);
        }

        return $run;
    }

    public function markCompleted(AiTrainingRun $run, string $outputLog = ''): AiTrainingRun
    {
        $run->forceFill([
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'completed_at' => now(),
            'output_log' => $this->mergeOutputLog($run->output_log, $outputLog),
        ])->save();

        return $run->fresh('candidates');
    }

    public function markFailed(AiTrainingRun $run, string $outputLog = ''): AiTrainingRun
    {
        $run->forceFill([
            'status' => AiTrainingRun::STATUS_FAILED,
            'failed_at' => now(),
            'output_log' => $this->mergeOutputLog($run->output_log, $outputLog),
        ])->save();

        return $run->fresh('candidates');
    }

    /**
     * @return list<int>
     */
    public function candidateIdsForSport(string $sport): array
    {
        return AiDatasetCandidate::query()
            ->where('sport', $sport)
            ->whereIn('status', [
                AiDatasetCandidate::STATUS_LABELED,
                AiDatasetCandidate::STATUS_APPROVED,
            ])
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function mergeOutputLog(?string $current, string $new): string
    {
        $current = trim((string) $current);
        $new = trim($new);

        if ($current === '') {
            return $new;
        }
        if ($new === '') {
            return $current;
        }

        return $current.PHP_EOL.PHP_EOL.$new;
    }
}
