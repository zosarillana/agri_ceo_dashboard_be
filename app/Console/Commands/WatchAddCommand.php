<?php

namespace App\Console\Commands;

use App\Models\WatchedImportFile;
use Illuminate\Console\Command;

/**
 * Registers a file (on local disk / a mounted network share) to be
 * auto-synced whenever its contents change. Run once per file:
 *
 *   php artisan imports:watch-add "/mnt/shared/DPR Updates_2026.xlsx" --label="Daily Production Tracker"
 *
 * After that, the scheduled `imports:sync-watched` command checks it on
 * every tick and imports any sheet whose tab name resolves to a date and
 * matches a saved template.
 */
class WatchAddCommand extends Command
{
    protected $signature = 'imports:watch-add {path} {--label=}';

    protected $description = 'Register a spreadsheet file to be auto-synced when it changes';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("No file found at: {$path}");
            $this->line('Double check the path is reachable from the Laravel server itself, not just your own machine.');
            return self::FAILURE;
        }

        $watched = WatchedImportFile::updateOrCreate(
            ['path' => $path],
            [
                'label' => $this->option('label') ?: basename($path),
                'is_active' => true,
            ],
        );

        $this->info("Watching: {$watched->path}");
        $this->line('Run `php artisan imports:sync-watched` to check it now, or wait for the scheduler.');

        return self::SUCCESS;
    }
}