<?php

namespace App\Jobs;

use App\Models\VideoAnalysis;
use App\Services\MockVideoAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunVideoAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $videoAnalysisId,
    ) {
    }

    public function handle(MockVideoAnalysisService $analysisService): void
    {
        $analysis = VideoAnalysis::find($this->videoAnalysisId);

        if (! $analysis) {
            return;
        }

        $analysisService->run($analysis);
    }
}
