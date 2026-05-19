<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredListings extends Command
{
    protected $signature = 'listings:purge-expired {--dry-run : Show how many rows would be deleted without deleting them}';

    protected $description = 'Delete expired listings from the opportunities table.';

    public function handle(): int
    {
        if (! Schema::hasTable('opportunities')) {
            $this->info('Opportunities table not found.');

            return self::SUCCESS;
        }

        $query = DB::table('opportunities')
            ->when(
                Schema::hasColumn('opportunities', 'expires_at'),
                fn ($builder) => $builder
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()),
                fn ($builder) => $builder->where('created_at', '<=', now()->subDays(30))
            );

        $count = (clone $query)->count();

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('%d ilan kaydi silinecek.', $count));

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Silinecek ilan kaydi bulunmadi.');

            return self::SUCCESS;
        }

        $deleted = 0;

        $query
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$deleted): void {
                $ids = $rows->pluck('id')->all();
                if ($ids === []) {
                    return;
                }

                $deleted += DB::table('opportunities')
                    ->whereIn('id', $ids)
                    ->delete();
            });

        $cacheKey = 'opportunities:index:cache_version';
        if (! Cache::has($cacheKey)) {
            Cache::forever($cacheKey, 1);
        }
        Cache::increment($cacheKey);

        $this->info(sprintf('%d ilan kaydi silindi.', $deleted));

        return self::SUCCESS;
    }
}
