<?php

namespace App\Console\Commands;

use App\Models\ScoutTip;
use Illuminate\Console\Command;

class PurgeExpiredScoutTips extends Command
{
    protected $signature = 'scout-tips:purge-expired {--days=30 : Delete scout tips older than this many days} {--dry-run : Show how many rows would be deleted without deleting them}';

    protected $description = 'Delete scout tips that have passed the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = ScoutTip::query()->where('created_at', '<=', $cutoff);
        $count = (clone $query)->count();

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf(
                '%d scout tip kaydi %s tarihinden eski oldugu icin silinecek.',
                $count,
                $cutoff->format('d.m.Y H:i')
            ));

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Silinecek scout tip kaydi bulunmadi.');

            return self::SUCCESS;
        }

        $deleted = 0;

        $query
            ->orderBy('id')
            ->chunkById(200, function ($tips) use (&$deleted): void {
                foreach ($tips as $tip) {
                    $tip->delete();
                    $deleted++;
                }
            });

        $this->info(sprintf(
            '%d scout tip kaydi silindi. Retention penceresi: %d gun.',
            $deleted,
            $days
        ));

        return self::SUCCESS;
    }
}
