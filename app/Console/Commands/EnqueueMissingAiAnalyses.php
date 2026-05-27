<?php

namespace App\Console\Commands;

use App\Models\AiDatasetCandidate;
use App\Models\VideoAnalysis;
use App\Services\AiContinuousLearningService;
use Illuminate\Console\Command;

class EnqueueMissingAiAnalyses extends Command
{
    protected $signature = 'ai-learning:enqueue-missing-analyses
        {--limit=100 : Maximum candidates to inspect}
        {--dry-run : Show missing analyses without dispatching jobs}';

    protected $description = 'Enqueue continuous-learning video analyses for dataset candidates missing analysis rows.';

    public function handle(AiContinuousLearningService $continuousLearningService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $candidates = AiDatasetCandidate::query()
            ->with('videoClip')
            ->whereIn('status', [
                AiDatasetCandidate::STATUS_QUEUED,
                AiDatasetCandidate::STATUS_LABELING,
                AiDatasetCandidate::STATUS_LABELED,
            ])
            ->whereDoesntHave('videoClip.analyses', function ($query) {
                $query
                    ->where('analysis_type', 'continuous_learning')
                    ->whereIn('status', ['queued', 'processing', 'completed']);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $enqueued = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $clip = $candidate->videoClip;
            if (! $clip) {
                $skipped++;
                $this->line("SKIP candidate={$candidate->id} reason=missing_video_clip");
                continue;
            }

            $failedAnalysisExists = VideoAnalysis::query()
                ->where('video_clip_id', $clip->id)
                ->where('target_player_id', $clip->user_id)
                ->where('analysis_type', 'continuous_learning')
                ->where('status', 'failed')
                ->exists();

            if ($failedAnalysisExists) {
                $skipped++;
                $this->line("SKIP candidate={$candidate->id} clip={$clip->id} reason=failed_analysis_exists");
                continue;
            }

            if ($dryRun) {
                $enqueued++;
                $this->line("DRY candidate={$candidate->id} clip={$clip->id} sport={$candidate->sport}");
                continue;
            }

            $continuousLearningService->onVideoUploaded($clip, $candidate);
            $enqueued++;
            $this->line("ENQUEUE candidate={$candidate->id} clip={$clip->id} sport={$candidate->sport}");
        }

        $this->info("Eksik AI analiz kontrolu tamamlandi. enqueued={$enqueued} skipped={$skipped}");

        return self::SUCCESS;
    }
}
