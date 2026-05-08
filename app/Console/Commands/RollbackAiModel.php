<?php

namespace App\Console\Commands;

use App\Services\AiModelRegistryService;
use Illuminate\Console\Command;

class RollbackAiModel extends Command
{
    protected $signature = 'ai-model:rollback
        {sport : football, basketball, or volleyball}
        {model_version : Previously published model version}
        {--notes= : Optional rollback note}';

    protected $description = 'Rollback active AI model to a previously published version.';

    public function handle(AiModelRegistryService $registry): int
    {
        $sport = strtolower(trim((string) $this->argument('sport')));
        if (! in_array($sport, ['football', 'basketball', 'volleyball'], true)) {
            $this->error('Sport football, basketball veya volleyball olmali.');

            return self::FAILURE;
        }

        $active = $registry->rollback(
            $sport,
            trim((string) $this->argument('model_version')),
            $this->option('notes')
        );

        $this->info('Rollback tamamlandi.');
        $this->line('Sport: '.$active->sport);
        $this->line('Model version: '.$active->model_version);

        return self::SUCCESS;
    }
}
