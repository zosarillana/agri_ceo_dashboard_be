<?php

namespace App\Console\Commands;

use App\Models\WatchedImportFile;
use App\Services\ImportFileWatcher;
use Illuminate\Console\Command;

class SyncWatchedCommand extends Command
{
    protected $signature = 'imports:sync-watched {--force : Force sync even if file hasn\'t changed}';
    protected $description = 'Sync all watched import files';

    public function handle(ImportFileWatcher $watcher)
    {
        $force = $this->option('force');
        
        $watchedFiles = WatchedImportFile::all();

        if ($watchedFiles->isEmpty()) {
            $this->info('No watched files found.');
            return 0;
        }

        foreach ($watchedFiles as $watched) {
            $this->info("--- {$watched->path} ---");
            
            $result = $watcher->check($watched, $force);
            
            if ($result['status'] === 'unchanged') {
                $this->info("No changes detected.");
            } elseif ($result['status'] === 'missing') {
                $this->error("File missing: {$watched->path}");
            } elseif ($result['status'] === 'error') {
                $this->error("Error: " . ($result['sheets'][0]['message'] ?? 'Unknown error'));
            } else {
                foreach ($result['sheets'] as $sheet) {
                    $status = $sheet['status'] ?? 'unknown';
                    $message = $sheet['message'] ?? '';
                    
                    if ($status === 'success') {
                        $this->info("  {$sheet['sheet']} — imported {$sheet['imported_rows']} rows");
                    } elseif ($status === 'partial') {
                        $this->warn("  {$sheet['sheet']} — partially imported ({$sheet['imported_rows']} rows)");
                    } elseif ($status === 'skipped') {
                        $this->comment("  {$sheet['sheet']} — skipped: {$message}");
                    } else {
                        $this->error("  {$sheet['sheet']} — {$status}: {$message}");
                    }
                }
            }
        }

        return 0;
    }
}