<?php

// app/Http/Controllers/SaleController.php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SaleController extends Controller
{
    protected SaleService $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * Display a listing of sales (original method for backwards compatibility)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $sales = $this->saleService->getLatest(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json([
            'data' => $sales,
        ]);
    }

    /**
     * Store a newly created sale (original method for backwards compatibility)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'market' => 'required|in:Export,Local',
            'asp_per_kg' => 'required|numeric|min:0',
            'quantity_kg' => 'required|numeric|min:0',
            'sale_date' => 'nullable|date|before_or_equal:today',
        ]);

        $sale = Sale::create([
            'product_id' => $request->product_id,
            'market' => $request->market,
            'asp_per_kg' => $request->asp_per_kg,
            'quantity_kg' => $request->quantity_kg,
            'sale_date' => $request->sale_date ?? now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Sale created successfully',
            'data' => $sale->load('product'),
        ], 201);
    }

    /**
     * Store multiple sales in bulk with a specific date
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.product_id' => 'required|exists:products,id',
            'rows.*.market' => 'required|in:Export,Local',
            'rows.*.asp_per_kg' => 'required|numeric|min:0',
            'rows.*.quantity_kg' => 'required|numeric|min:0',
            'sale_date' => 'nullable|date|before_or_equal:today',
        ]);

        $sales = $this->saleService->storeBulk(
            $request->input('rows'),
            $request->input('sale_date')
        );

        return response()->json([
            'message' => 'Sales saved successfully',
            'data' => $sales,
        ]);
    }

    /**
     * Get latest sales with optional date range filtering
     */
    public function getLatest(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $sales = $this->saleService->getLatest(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json([
            'data' => $sales,
        ]);
    }

    /**
     * Get sales summary with optional date range filtering
     */
    // In SaleController.php
    public function getSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'month' => 'nullable|date_format:Y-m', // Accepts "2026-06"
        ]);

        // If month is provided, override from/to
        if ($request->has('month')) {
            $from = Carbon::parse($request->month)->startOfMonth()->toDateString();
            $to = Carbon::parse($request->month)->endOfMonth()->toDateString();
        } else {
            $from = $request->input('from');
            $to = $request->input('to');
        }

        $summary = $this->saleService->getSummary($from, $to);

        return response()->json([
            'data' => $summary,
        ]);
    }
}
