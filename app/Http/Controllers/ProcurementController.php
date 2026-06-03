<?php

namespace App\Http\Controllers;

use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    public function __construct(protected ProcurementService $procurementService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json([
            'data' => $this->procurementService->getAll(
                $request->input('from'),
                $request->input('to')
            )
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json([
            'data' => $this->procurementService->getSummary(
                $request->input('from'),
                $request->input('to')
            )
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_name'        => 'required|string|max:255',
            'product_id'       => 'nullable|exists:products,id',
            'supplier'         => 'nullable|string|max:255',
            'quantity'         => 'required|numeric|min:0',
            'unit'             => 'required|string|max:20',
            'status'           => 'required|in:received,pending,delayed',
            'procurement_date' => 'nullable|date',
        ]);

        $procurement = $this->procurementService->store($data);

        return response()->json($procurement, 201);
    }

    /**
     * Update a single procurement record
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'item_name'        => 'sometimes|required|string|max:255',
            'product_id'       => 'nullable|exists:products,id',
            'supplier'         => 'nullable|string|max:255',
            'quantity'         => 'sometimes|required|numeric|min:0',
            'unit'             => 'sometimes|required|string|max:20',
            'status'           => 'sometimes|required|in:received,pending,delayed',
            'procurement_date' => 'nullable|date',
        ]);

        $procurement = $this->procurementService->update($id, $data);

        return response()->json([
            'data' => $procurement,
            'message' => 'Procurement record updated successfully'
        ]);
    }

    public function storeBulk(Request $request): JsonResponse
    {
        $request->validate([
            'rows'               => 'required|array|min:1',
            'rows.*.id'          => 'nullable|integer|exists:procurements,id', // Allow ID for updates
            'rows.*.item_name'   => 'required|string',
            'rows.*.supplier'    => 'nullable|string',
            'rows.*.quantity'    => 'required|numeric',
            'rows.*.unit'        => 'nullable|string',
            'rows.*.status'      => 'required|in:received,pending,delayed',
            'procurement_date'   => 'nullable|date|before_or_equal:today',
        ]);

        $records = $this->procurementService->storeBulk(
            $request->input('rows'),
            $request->input('procurement_date')
        );

        $updateCount = collect($request->input('rows'))->whereNotNull('id')->count();
        $newCount = collect($request->input('rows'))->whereNull('id')->count();
        
        $message = [];
        if ($newCount > 0) $message[] = "{$newCount} new record(s) created";
        if ($updateCount > 0) $message[] = "{$updateCount} record(s) updated";
        
        return response()->json([
            'data' => $records,
            'message' => implode(' and ', $message) . ' successfully'
        ]);
    }
}