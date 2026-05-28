<?php
// app/Http/Controllers/QcController.php

namespace App\Http\Controllers;

use App\Models\QcRecord;
use App\Services\QcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QcController extends Controller
{
    public function __construct(protected QcService $qcService) {}

    /**
     * List QC records with optional date range.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $records = $this->qcService->getLatest(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json(['data' => $records]);
    }

    /**
     * Store a single QC record.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'tested'     => 'required|integer|min:1',
            'passed'     => 'required|integer|min:0|lte:tested',
            'qc_date'    => 'nullable|date|before_or_equal:today',
        ]);

        $record = QcRecord::create([
            'product_id' => $request->product_id,
            'tested'     => $request->tested,
            'passed'     => $request->passed,
            'qc_date'    => $request->qc_date ?? now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'QC record created successfully',
            'data'    => $record->load('product'),
        ], 201);
    }

    /**
     * Bulk upsert QC records for a given date.
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $request->validate([
            'rows'               => 'required|array|min:1',
            'rows.*.product_id'  => 'required|exists:products,id',
            'rows.*.tested'      => 'required|integer|min:1',
            'rows.*.passed'      => 'required|integer|min:0|lte:rows.*.tested',
            'qc_date'            => 'nullable|date|before_or_equal:today',
        ]);

        $records = $this->qcService->storeBulk(
            $request->input('rows'),
            $request->input('qc_date')
        );

        return response()->json([
            'message' => 'QC records saved successfully',
            'data'    => $records,
        ]);
    }

    /**
     * Latest QC record per product with optional date range.
     */
    public function getLatest(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $records = $this->qcService->getLatest(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json(['data' => $records]);
    }

    /**
     * Aggregate summary with optional date range.
     */
    public function getSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $summary = $this->qcService->getSummary(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json(['data' => $summary]);
    }
}