<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\TemplateImport;
use App\Models\ImportTemplate;
use App\Services\TemplateDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class SpreadsheetImportController extends Controller
{
    /**
     * Import a spreadsheet into MySQL.
     *
     * Two ways to call this:
     *  1. Pass `template_id` to reuse a saved template's mapping.
     *  2. Pass `template_name` + `header_row` + `columns` to create a new
     *     template on the fly and use it immediately (this is what the
     *     "Save as template & import" button in the UI does).
     *
     * Optionally pass `selected_row_indices` (JSON-encoded array of ints)
     * to import only specific data rows. These are 0-based ordinal
     * positions within the filtered, non-blank data rows below the header
     * — NOT raw spreadsheet row numbers — matching how the frontend's row
     * checkboxes are indexed. If omitted, every non-blank row is imported.
     *
     * Optionally pass `production_date` (Y-m-d) to also sync matched rows
     * into production_entries — see App\Services\ProductionEntrySync. This
     * only has an effect if at least one column has role=product_name.
     */
    public function store(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        Log::info('[imports] request received', [
            'has_file' => $request->hasFile('file'),
            'file_name' => $request->file('file')?->getClientOriginalName(),
            'file_size' => $request->file('file')?->getSize(),
            'file_mime' => $request->file('file')?->getMimeType(),
            'file_valid' => $request->file('file')?->isValid(),
            'sheet_name' => $request->input('sheet_name'),
            'template_id' => $request->input('template_id'),
            'template_name' => $request->input('template_name'),
            'header_row' => $request->input('header_row'),
            'production_date' => $request->input('production_date'),
            'column_count' => is_array($request->input('columns'))
                ? count($request->input('columns'))
                : 0,
            'has_selected_row_indices' => $request->filled('selected_row_indices'),
            'all_keys' => array_keys($request->all()),
        ]);

        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'sheet_name' => 'required|string',
                'selected_row_indices' => 'nullable|string', // JSON-encoded array of ints
                'production_date' => 'nullable|date',

                'template_id' => 'nullable|integer|exists:import_templates,id',

                'template_name' => 'required_without:template_id|string|max:255',
                'header_row' => 'required_without:template_id|integer|min:1',
                'columns' => 'required_without:template_id|array|min:1',
                'columns.*.letter' => 'required_without:template_id|string',
                'columns.*.label' => 'required_without:template_id|string',
                'columns.*.db_column' => 'required_without:template_id|string|regex:/^[a-z_][a-z0-9_]*$/',
                // Without this rule, Laravel's validate() silently strips
                // "role" out of every column before it ever reaches
                // ImportTemplate::create() — which is why templates created
                // through this endpoint previously had no role saved at all.
                'columns.*.role' => 'nullable|string|in:product_name,actual_output,target_output,dly_target,dly_yield,mtd_target,mtd_yield,remarks',
            ]);
        } catch (ValidationException $e) {
            Log::warning('[imports] validation failed', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        Log::info('[imports] validation passed', [
            'using_template_id' => $validated['template_id'] ?? null,
        ]);

        $selectedRowIndices = null;
        if ($request->filled('selected_row_indices')) {
            $decoded = json_decode($request->input('selected_row_indices'), true);
            if (is_array($decoded)) {
                $selectedRowIndices = array_values(array_map('intval', $decoded));
            } else {
                Log::warning('[imports] selected_row_indices was not valid JSON array, ignoring', [
                    'raw' => $request->input('selected_row_indices'),
                ]);
            }
        }

        $productionDate = $validated['production_date'] ?? null;

        try {
            $template = ($validated['template_id'] ?? null)
                ? ImportTemplate::findOrFail($validated['template_id'])
                : ImportTemplate::create([
                    'name' => $validated['template_name'],
                    'header_row' => $validated['header_row'],
                    'signature' => collect($validated['columns'])->pluck('label')->values()->all(),
                    'column_map' => $validated['columns'],
                ]);

            Log::info('[imports] template resolved', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'row_selection_count' => $selectedRowIndices !== null ? count($selectedRowIndices) : null,
                'production_date' => $productionDate,
                'has_product_name_role' => collect($template->column_map)
                    ->contains(fn ($c) => ($c['role'] ?? null) === 'product_name'),
            ]);

            $file = $request->file('file');

            $import = new TemplateImport(
                $template,
                $validated['sheet_name'],
                $file->getClientOriginalName(),
                null, // headerRowOverride — not used on this path, only by auto()
                $selectedRowIndices,
                $productionDate, // <-- this argument was missing before
            );
            Excel::import($import, $file);

            $count = $import->importedCount();
            $productionSync = $import->productionSyncStats();

            Log::info('[imports] import finished', [
                'template_id' => $template->id,
                'imported_rows' => $count,
                'production_sync' => $productionSync,
            ]);

            return response()->json([
                'template' => $template,
                'imported_rows' => $count,
                'production_sync' => $productionSync,
            ], 201);
        } catch (Throwable $e) {
            Log::error('[imports] failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fully unattended import: no sheet name, no template — just a file.
     * Scans every sheet against every saved template and imports the first
     * match it finds. Intended for automated pipelines (a watched folder,
     * an email-attachment webhook, a scheduled job) where nobody is
     * clicking through the UI to pick a sheet.
     *
     * There's no row-review step and no production_date here, so every
     * non-blank row is imported into import_rows only — no
     * production_entries sync on this path. Use the watch command / manual
     * flow if you need that.
     */
    public function auto(Request $request, TemplateDetector $detector)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $match = $detector->detect($path);

        if (! $match) {
            Log::info('[imports/auto] no template matched', [
                'sheets_scanned' => $detector->sheetNames($path),
            ]);

            return response()->json([
                'message' => 'No sheet in this file matched a saved template.',
                'sheets_scanned' => $detector->sheetNames($path),
            ], 422);
        }

        try {
            $import = new TemplateImport(
                $match['template'],
                $match['sheet'],
                $file->getClientOriginalName(),
                $match['headerRow'],
            );
            Excel::import($import, $file);

            return response()->json([
                'sheet_name' => $match['sheet'],
                'template' => $match['template'],
                'imported_rows' => $import->importedCount(),
            ], 201);
        } catch (Throwable $e) {
            Log::error('[imports/auto] failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}