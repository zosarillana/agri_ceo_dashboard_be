<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionEntryRequest;
use App\Http\Requests\UpdateProductionEntryRequest;
use App\Models\ProductionEntry;
use App\Services\ProductionEntryService;
use Illuminate\Http\Request;

class ProductionEntryController extends Controller
{
    public function __construct(
        protected ProductionEntryService $productionEntryService
    ) {}

    public function index(Request $request)
    {
        // If a ?date= param is provided, return only that day's entries.
        // Otherwise return all entries (existing behaviour).
        if ($request->filled('date')) {
            return response()->json(
                $this->productionEntryService->getByDate($request->input('date'))
            );
        }

        return response()->json(
            $this->productionEntryService->getAll()
        );
    }

    public function store(StoreProductionEntryRequest $request)
    {
        $entry = $this->productionEntryService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Production entry created successfully.',
            'data'    => $entry,
        ], 201);
    }

    public function show(ProductionEntry $productionEntry)
    {
        return response()->json(
            $productionEntry->load('product')
        );
    }

    public function update(
        UpdateProductionEntryRequest $request,
        ProductionEntry $productionEntry
    ) {
        $entry = $this->productionEntryService->update(
            $productionEntry,
            $request->validated()
        );

        return response()->json([
            'message' => 'Production entry updated successfully.',
            'data'    => $entry,
        ]);
    }

    public function destroy(ProductionEntry $productionEntry)
    {
        $this->productionEntryService->delete($productionEntry);

        return response()->json([
            'message' => 'Production entry deleted successfully.',
        ]);
    }
}