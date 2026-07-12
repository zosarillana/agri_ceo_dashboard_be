<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportRow;
use App\Models\ImportTemplate;
use Illuminate\Http\Request;

class ImportRowController extends Controller
{
    /**
     * Get all imported rows with optional filtering
     */
    public function index(Request $request)
    {
        $query = ImportRow::with('template')
            ->orderBy('created_at', 'desc');

        // Filter by template
        if ($request->has('template_id') && $request->template_id) {
            $query->where('import_template_id', $request->template_id);
        }

        // Filter by sheet name
        if ($request->has('sheet_name') && $request->sheet_name) {
            $query->where('sheet_name', 'like', '%' . $request->sheet_name . '%');
        }

        // Search in data (JSON column)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sheet_name', 'like', '%' . $search . '%')
                  ->orWhere('source_file', 'like', '%' . $search . '%')
                  ->orWhere('row_number', 'like', '%' . $search . '%');
            });
        }

        // Date range filter
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('imported_at', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('imported_at', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 20);
        return $query->paginate($perPage);
    }

    /**
     * Get a specific imported row
     */
    public function show($id)
    {
        $row = ImportRow::with('template')->findOrFail($id);
        return response()->json($row);
    }

    /**
     * Get all rows for a specific template
     */
    public function byTemplate($templateId)
    {
        $template = ImportTemplate::findOrFail($templateId);
        $rows = ImportRow::where('import_template_id', $templateId)
            ->orderBy('row_number')
            ->get();
        
        return response()->json([
            'template' => $template,
            'rows' => $rows,
        ]);
    }

    /**
     * Get summary statistics
     */
    public function summary()
    {
        $totalRows = ImportRow::count();
        $totalTemplates = ImportTemplate::count();
        $recentImports = ImportRow::select('import_template_id', 'sheet_name', 'source_file', 'imported_at')
            ->with('template')
            ->orderBy('imported_at', 'desc')
            ->limit(10)
            ->get();

        $templateStats = ImportTemplate::withCount('rows')
            ->orderBy('rows_count', 'desc')
            ->get();

        return response()->json([
            'total_rows' => $totalRows,
            'total_templates' => $totalTemplates,
            'recent_imports' => $recentImports,
            'template_stats' => $templateStats,
        ]);
    }
}