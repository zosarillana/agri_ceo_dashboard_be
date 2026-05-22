<?php
// app/Http/Controllers/EnergyController.php

namespace App\Http\Controllers;

use App\Services\EnergyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnergyController extends Controller
{
    protected EnergyService $energyService;

    public function __construct(EnergyService $energyService)
    {
        $this->energyService = $energyService;
    }

    /**
     * Get all records.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->energyService->getAll()
        ]);
    }

    /**
     * Store bulk energy rows.
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => 'required|array|min:1',

            'rows.*.account' => 'required|in:account2,account3',

            'rows.*.month' => 'required|date_format:Y-m',

            'rows.*.kw' => 'required|numeric|min:0',

            'rows.*.demand' => 'required|numeric|min:0',

            'rows.*.billedAmount' => 'required|numeric|min:0',
        ]);

        $saved = $this->energyService->storeBulk(
            $request->input('rows')
        );

        return response()->json([
            'message' => 'Energy records saved successfully',
            'data'    => $saved,
        ]);
    }

    /**
     * Get records by month.
     */
    public function getByMonth(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        return response()->json([
            'data' => $this->energyService->getByMonth(
                $request->input('month')
            )
        ]);
    }

    /**
     * Get dashboard summary.
     */
    public function getSummary(): JsonResponse
    {
        return response()->json([
            'data' => $this->energyService->getSummary()
        ]);
    }
}