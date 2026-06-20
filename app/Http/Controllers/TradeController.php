<?php

namespace App\Http\Controllers;

use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(
        private TradeService $tradeService
    ) {}

    /**
     * GET /api/trades?from=Y-m-d&to=Y-m-d
     * Returns ALL trades in range (not "latest per item")
     */
    public function index(Request $request): JsonResponse
    {
        $trades = $this->tradeService->getBetween(
            $request->query('from'),
            $request->query('to')
        );

        return response()->json($trades);
    }

    /**
     * GET /api/trades/summary?from=Y-m-d&to=Y-m-d
     * Returns aggregated totals
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->tradeService->getSummary(
            $request->query('from'),
            $request->query('to')
        );

        return response()->json($summary);
    }

    /**
     * OPTIONAL shortcut endpoint:
     * GET /api/trades/month-to-date
     */
    public function monthToDateSummary(): JsonResponse
    {
        $summary = $this->tradeService->getMonthToDateSummary();

        return response()->json($summary);
    }

    /**
     * POST /api/trades/bulk
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trade_date'              => ['nullable', 'date'],
            'rows'                    => ['required', 'array', 'min:1'],
            'rows.*.trade_item_id'    => ['required', 'integer', 'exists:trade_items,id'],
            'rows.*.market'           => ['required', 'in:Export,Local'],
            'rows.*.counterparty'     => ['nullable', 'string', 'max:255'],
            'rows.*.input_kg'         => ['required', 'numeric', 'min:0'],
            'rows.*.output_kg'        => ['required', 'numeric', 'min:0'],
        ]);

        $saved = $this->tradeService->storeBulk(
            $validated['rows'],
            $validated['trade_date'] ?? null
        );

        return response()->json($saved, 201);
    }

    /**
     * PUT /api/trades/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'market'       => ['required', 'in:Export,Local'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'input_kg'     => ['required', 'numeric', 'min:0'],
            'output_kg'    => ['required', 'numeric', 'min:0'],
        ]);

        $trade = $this->tradeService->updateTrade($id, $validated);

        return response()->json($trade);
    }

    /**
     * DELETE /api/trades/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->tradeService->deleteTrade($id);

        return response()->json(['message' => 'Trade deleted successfully.']);
    }
}