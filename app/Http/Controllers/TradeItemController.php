<?php

namespace App\Http\Controllers;

use App\Services\TradeItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeItemController extends Controller
{
    public function __construct(
        private TradeItemService $tradeItemService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->tradeItemService->getAll());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->tradeItemService->findById($id));
    }

    public function trades(Request $request, int $id): JsonResponse
    {
        $item = $this->tradeItemService->getWithTrades(
            $id,
            $request->query('from'),
            $request->query('to')
        );

        return response()->json($item);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'code'   => ['required', 'string', 'max:50', 'unique:trade_items,code'],
            'input'  => ['nullable', 'string', 'max:255'],
            'output' => ['nullable', 'string', 'max:255'],
            'market' => ['nullable', 'in:Export,Local,CWC'],
        ]);

        $item = $this->tradeItemService->create($validated);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'code'   => ['required', 'string', 'max:50', "unique:trade_items,code,{$id}"],
            'input'  => ['nullable', 'string', 'max:255'],
            'output' => ['nullable', 'string', 'max:255'],
            'market' => ['nullable', 'in:Export,Local,CWC'],
        ]);

        $item = $this->tradeItemService->update($id, $validated);

        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->tradeItemService->delete($id);

        return response()->json(['message' => 'Trade item deleted successfully.']);
    }
}