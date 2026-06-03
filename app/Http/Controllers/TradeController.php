<?php
// app/Http/Controllers/TradeController.php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Services\TradeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TradeController extends Controller
{
    protected TradeService $tradeService;

    public function __construct(TradeService $tradeService)
    {
        $this->tradeService = $tradeService;
    }

    /**
     * Display a listing of trades (original method for backwards compatibility)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from'
        ]);

        $trades = $this->tradeService->getLatest(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json([
            'data' => $trades
        ]);
    }

    /**
     * Store a newly created trade (original method for backwards compatibility)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'market' => 'required|in:Export,Local',
            'counterparty' => 'nullable|string|max:255',
            'price_per_kg' => 'required|numeric|min:0',
            'quantity_kg' => 'required|numeric|min:0',
            'trade_date' => 'nullable|date|before_or_equal:today'
        ]);

        $trade = Trade::create([
            'product_id' => $request->product_id,
            'market' => $request->market,
            'counterparty' => $request->counterparty,
            'price_per_kg' => $request->price_per_kg,
            'quantity_kg' => $request->quantity_kg,
            'trade_date' => $request->trade_date ?? now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Trade created successfully',
            'data' => $trade->load('product')
        ], 201);
    }

    /**
     * Store multiple trades in bulk with a specific date
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.product_id' => 'required|exists:products,id',
            'rows.*.market' => 'required|in:Export,Local',
            'rows.*.counterparty' => 'nullable|string|max:255',
            'rows.*.price_per_kg' => 'required|numeric|min:0',
            'rows.*.quantity_kg' => 'required|numeric|min:0',
            'trade_date' => 'nullable|date|before_or_equal:today'
        ]);

        $trades = $this->tradeService->storeBulk(
            $request->input('rows'),
            $request->input('trade_date')
        );

        return response()->json([
            'message' => 'Trades saved successfully',
            'data' => $trades
        ]);
    }

    /**
     * Get latest trades with optional date range filtering
     */
    public function getLatest(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from'
        ]);

        $trades = $this->tradeService->getLatest(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json([
            'data' => $trades
        ]);
    }

    /**
     * Get trades summary with optional date range filtering
     */
    public function getSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from'
        ]);

        $summary = $this->tradeService->getSummary(
            $request->input('from'),
            $request->input('to')
        );

        return response()->json([
            'data' => $summary
        ]);
    }

    /**
     * Display the specified trade.
     */
    public function show(int $id): JsonResponse
    {
        $trade = Trade::with('product')->findOrFail($id);
        
        return response()->json([
            'data' => $trade
        ]);
    }

    /**
     * Update the specified trade.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'market' => 'sometimes|required|in:Export,Local',
            'counterparty' => 'nullable|string|max:255',
            'price_per_kg' => 'sometimes|required|numeric|min:0',
            'quantity_kg' => 'sometimes|required|numeric|min:0',
        ]);

        $trade = $this->tradeService->updateTrade($id, $request->all());

        return response()->json([
            'message' => 'Trade updated successfully',
            'data' => $trade
        ]);
    }

    /**
     * Remove the specified trade.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->tradeService->deleteTrade($id);

        return response()->json([
            'message' => 'Trade deleted successfully'
        ]);
    }
}