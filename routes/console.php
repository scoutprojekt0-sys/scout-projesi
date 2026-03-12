<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('security:revoke-legacy-tokens {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $legacyTokenIds = [];

    DB::table('personal_access_tokens')
        ->select(['id', 'abilities'])
        ->orderBy('id')
        ->chunkById(500, function ($tokens) use (&$legacyTokenIds): void {
            foreach ($tokens as $token) {
                $abilities = json_decode((string) $token->abilities, true);
                if (is_array($abilities) && in_array('*', $abilities, true)) {
                    $legacyTokenIds[] = (int) $token->id;
                }
            }
        });

    $count = count($legacyTokenIds);

    if (! $dryRun && $count > 0) {
        DB::table('personal_access_tokens')->whereIn('id', $legacyTokenIds)->delete();
    }

    $this->info(($dryRun ? 'Dry run: ' : '').$count.' legacy wildcard token bulundu'.($dryRun ? '.' : ' ve iptal edildi.'));
})->purpose('Revoke legacy Sanctum tokens that use wildcard (*) ability');

Schedule::command('security:revoke-legacy-tokens')->dailyAt('03:00');
