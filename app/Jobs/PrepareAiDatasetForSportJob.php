<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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

    public function handle(): void
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
            Artisan::call('ai:prepare-dataset', [
                'sport' => $sport,
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
