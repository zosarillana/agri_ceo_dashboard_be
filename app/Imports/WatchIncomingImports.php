<?php

namespace App\Console\Commands;

use App\Imports\TemplateImport;
use App\Services\TemplateDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Scans a folder for spreadsheet files, imports any that match a saved
 * template, and moves them out of the way afterwards. Schedule this in
 * routes/console.php (or app/Console/Kernel.php on older Laravel):
 *
 *   Schedule::command('imports:watch')->everyFiveMinutes();
 *
 * This is the "zero clicks" path: drop a file into storage/app/incoming
 * (e.g. from an email-to-folder rule, an SFTP drop, a shared drive sync)
 * and it gets imported automatically the next time this runs, with no one
 * touching the UI.
 */
class WatchIncomingImports extends Command
{
    protected $signature = 'imports:watch {--path=} {--processed-path=} {--failed-path=}';

    protected $description = 'Import any spreadsheet in the incoming folder that matches a saved template';

    public function handle(TemplateDetector $detector): int
    {
        $incomingPath = $this->option('path') ?: storage_path('app/incoming');
        $processedPath = $this->option('processed-path') ?: storage_path('app/incoming/processed');
        $failedPath = $this->option('failed-path') ?: storage_path('app/incoming/unmatched');

        File::ensureDirectoryExists($incomingPath);
        File::ensureDirectoryExists($processedPath);
        File::ensureDirectoryExists($failedPath);

        $files = collect(File::files($incomingPath))
            ->filter(fn ($f) => in_array(strtolower($f->getExtension()), ['xlsx', 'xls', 'csv']));

        if ($files->isEmpty()) {
            $this->info('No files waiting in ' . $incomingPath);
            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $path = $file->getPathname();
            $this->line("Scanning {$file->getFilename()}...");

            $match = $detector->detect($path);

            if (! $match) {
                $this->warn("  No template matched — moved to unmatched/");
                File::move($path, $failedPath . '/' . $file->getFilename());
                continue;
            }

            $import = new TemplateImport(
                $match['template'],
                $match['sheet'],
                $file->getFilename(),
                $match['headerRow'],
            );
            Excel::import($import, $path);

            $this->info(sprintf(
                '  Matched "%s" on sheet "%s" — imported %d rows',
                $match['template']->name,
                $match['sheet'],
                $import->importedCount(),
            ));

            File::move($path, $processedPath . '/' . $file->getFilename());
        }

        return self::SUCCESS;
    }
}