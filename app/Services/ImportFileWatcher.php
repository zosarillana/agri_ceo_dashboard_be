<?php

namespace App\Services;

use App\Imports\TemplateImport;
use App\Models\ImportTemplate;
use App\Models\WatchedImportFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use Throwable;

class ImportFileWatcher
{
    public function check(WatchedImportFile $watched, bool $force = false): array
    {
        $path = $watched->path;

        if (! is_file($path) || ! is_readable($path)) {
            $watched->update(['last_status' => 'missing', 'last_error' => "File not found: {$path}"]);

            return ['status' => 'missing', 'sheets' => []];
        }

        $mtime = filemtime($path);
        if ($mtime === false) {
            $watched->update(['last_status' => 'error', 'last_error' => 'Could not read mtime.']);

            return ['status' => 'error', 'sheets' => []];
        }

        $mtimeCarbon = Carbon::createFromTimestamp($mtime);

        if (! $force && $watched->last_modified_at && $mtimeCarbon->lessThanOrEqualTo($watched->last_modified_at)) {
            return ['status' => 'unchanged', 'sheets' => []];
        }

        try {
            $sheetResults = $this->syncAllSheets($path);
        } catch (Throwable $e) {
            Log::error('[ImportFileWatcher] failed to open/scan file', [
                'path' => $path, 'message' => $e->getMessage(),
            ]);
            $watched->update(['last_status' => 'error', 'last_error' => $e->getMessage()]);

            return ['status' => 'error', 'sheets' => []];
        }

        $overallStatus = collect($sheetResults)->contains(fn ($s) => $s['status'] === 'error')
            ? 'error' : 'ok';

        $watched->update([
            'last_modified_at' => $mtimeCarbon,
            'last_synced_at' => now(),
            'last_status' => $overallStatus,
            'last_error' => null,
            'last_summary' => $sheetResults,
        ]);

        return ['status' => $overallStatus, 'sheets' => $sheetResults];
    }

    protected function syncAllSheets(string $path): array
    {
        $sheetNames = IOFactory::createReaderForFile($path)->listWorksheetNames($path);

        Log::info('[ImportFileWatcher] starting scan', ['sheet_count' => count($sheetNames)]);

        $results = [];

        foreach ($sheetNames as $sheetName) {
            $rawRows = $this->scanSingleSheet($path, $sheetName);

            $headerRowIndex = HeaderRowGuesser::guess($rawRows);
            $headerRow = $rawRows[$headerRowIndex] ?? [];
            $headerLabels = array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), $headerRow),
                fn ($v) => $v !== '',
            ));

            $template = ImportTemplate::findMatch($headerLabels);

            if (! $template) {
                $results[] = ['sheet' => $sheetName, 'status' => 'skipped', 'message' => 'No template matched.'];

                continue;
            }

            $date = SheetDateGuesser::guess($sheetName);

            if (! $date) {
                $results[] = [
                    'sheet' => $sheetName,
                    'status' => 'skipped',
                    'message' => "Matched \"{$template->name}\" but no date in sheet name.",
                ];

                continue;
            }

            try {
                $results[] = $this->importMatchedSheet($path, $template, $sheetName, $headerRowIndex, $date);
            } catch (Throwable $e) {
                Log::error('[ImportFileWatcher] sheet import failed', [
                    'sheet' => $sheetName, 'message' => $e->getMessage(),
                ]);
                $results[] = [
                    'sheet' => $sheetName, 'status' => 'error',
                    'template' => $template->name, 'date' => $date,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Runs the exact same import pipeline the manual UI upload uses:
     * TemplateImport / Excel::import(). An earlier version of this method
     * tried to save memory by manually reading only the template's mapped
     * columns, but that broke formula calculation (a formula's dependency
     * cells outside the mapped set were never loaded, so it silently
     * resolved to 0) and produced values that didn't match what the
     * manual import got for the exact same sheet. Only sheets that
     * actually match a template reach this method — the earlier memory
     * crashes were from scanning ALL ~186 sheets, not from fully loading
     * the handful that match — so a full, correct load here is the right
     * tradeoff.
     */
    protected function importMatchedSheet(
        string $path,
        ImportTemplate $template,
        string $sheetName,
        int $headerRowIndex,
        string $date,
    ): array {
        $import = new TemplateImport(
            $template,
            $sheetName,
            basename($path),
            $headerRowIndex + 1, // TemplateImport expects 1-indexed
            null, // selectedRowIndices — every non-blank row, unattended
            $date,
        );

        Log::info('[Memory] Before import', [
            'sheet' => $sheetName,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        Excel::import($import, $path);

        Log::info('[Memory] After import', [
            'sheet' => $sheetName,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        // Get everything you need BEFORE unsetting
        $syncStats = $import->productionSyncStats();
        $importedRows = $import->importedCount();

        // Now it's safe to release it
        unset($import);

        gc_collect_cycles();

        Log::info('[Memory] After GC', [
            'sheet' => $sheetName,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        return [
            'sheet' => $sheetName,
            'status' => ! empty($syncStats['errors']) ? 'partial' : 'success',
            'template' => $template->name,
            'date' => $date,
            'imported_rows' => $importedRows,
            'production_sync' => $syncStats,
        ];
    }

    protected function scanSingleSheet(string $path, string $sheetName): array
    {
        $readFilter = new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = '')
            {
                return $row <= 40;
            }
        };

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($sheetName);
        $reader->setReadFilter($readFilter);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (! $sheet) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);
            unset($import);

            gc_collect_cycles();

            Log::info('[Memory] After GC', [
                'sheet' => $sheetName,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return [];
        }

        $highestRow = min($sheet->getHighestRow(), 40);
        $highestColumn = $sheet->getHighestColumn();

        $rawRows = $sheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, false);

        $spreadsheet->disconnectWorksheets();
        unset($sheet, $spreadsheet, $reader);
        unset($import);

        gc_collect_cycles();

        Log::info('[Memory] After GC', [
            'sheet' => $sheetName,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        return $rawRows;
    }
}
