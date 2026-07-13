<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportTemplate;
use Illuminate\Http\Request;

class ImportTemplateController extends Controller
{
    public function index()
    {
        return ImportTemplate::orderByDesc('updated_at')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'header_row' => 'required|integer|min:1',
            'columns' => 'required|array|min:1',
            'columns.*.letter' => 'required|string',
            'columns.*.label' => 'required|string',
            'columns.*.db_column' => 'required|string|regex:/^[a-z_][a-z0-9_]*$/',
        ]);

        $template = ImportTemplate::create([
            'name' => $validated['name'],
            'header_row' => $validated['header_row'],
            'signature' => collect($validated['columns'])->pluck('label')->values()->all(),
            'column_map' => $validated['columns'],
        ]);

        return response()->json($template, 201);
    }

    /**
     * Check whether any saved template matches a set of header labels.
     * Used by the frontend right after parsing a new file, before the
     * person manually picks a header row — if this returns a match, the
     * UI can skip straight to importing.
     */
    public function detect(Request $request)
    {
        $validated = $request->validate([
            'header_labels' => 'required|array|min:1',
            'header_labels.*' => 'nullable|string',
        ]);

        $match = ImportTemplate::findMatch($validated['header_labels']);

        return response()->json(['template' => $match]);
    }
}