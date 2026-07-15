<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Checks every watched Excel file's modified time and, if changed, scans
// and imports its matched sheets. See App\Services\ImportFileWatcher and
// App\Console\Commands\SyncWatchedCommand.
//
// This line alone does nothing on its own — Laravel's scheduler only
// fires when something calls `php artisan schedule:run` on a loop. On
// Windows there's no cron, so that has to come from Task Scheduler
// instead (see the setup steps already covered).
Schedule::command('imports:sync-watched')->everyMinute()->withoutOverlapping();
