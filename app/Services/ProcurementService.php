<?php

namespace App\Services;

use App\Models\Procurement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProcurementService
{
    public function getAll(?string $from = null, ?string $to = null): Collection
    {
        return Procurement::with('product')
            ->between($from, $to)
            ->orderByDesc('procurement_date')
            ->get();
    }

    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $items = $this->getAll($from, $to);

        return [
            'total_items' => $items->count(),
            'received'    => $items->where('status', 'received')->count(),
            'delayed'     => $items->where('status', 'delayed')->count(),
            'pending'     => $items->where('status', 'pending')->count(),
            'from'        => $from,
            'to'          => $to,
        ];
    }

    public function store(array $data): Procurement
    {
        return Procurement::create([
            'product_id'       => $data['product_id'] ?? null,
            'item_name'        => $data['item_name'],
            'supplier'         => $data['supplier'] ?? null,
            'quantity'         => $data['quantity'],
            'unit'             => $data['unit'] ?? 'kg',
            'status'           => $data['status'] ?? 'pending',
            'procurement_date' => $data['procurement_date'] ?? now()->toDateString(),
        ]);
    }

    public function update(int $id, array $data): Procurement
    {
        $procurement = Procurement::findOrFail($id);
        
        $procurement->update([
            'product_id'       => $data['product_id'] ?? $procurement->product_id,
            'item_name'        => $data['item_name'] ?? $procurement->item_name,
            'supplier'         => $data['supplier'] ?? $procurement->supplier,
            'quantity'         => $data['quantity'] ?? $procurement->quantity,
            'unit'             => $data['unit'] ?? $procurement->unit,
            'status'           => $data['status'] ?? $procurement->status,
            'procurement_date' => $data['procurement_date'] ?? $procurement->procurement_date,
        ]);
        
        return $procurement->fresh();
    }

    public function storeBulk(array $rows, ?string $date = null): Collection
    {
        $procurementDate = $date ?? now()->toDateString();
        $saved = collect();

        foreach ($rows as $row) {
            // Check if this is an update (has 'id' field)
            if (isset($row['id']) && !empty($row['id'])) {
                // Update existing record
                $procurement = $this->update($row['id'], [
                    'product_id'       => $row['product_id'] ?? null,
                    'item_name'        => $row['item_name'],
                    'supplier'         => $row['supplier'] ?? null,
                    'quantity'         => $row['quantity'],
                    'unit'             => $row['unit'] ?? 'kg',
                    'status'           => $row['status'] ?? 'pending',
                    'procurement_date' => $procurementDate,
                ]);
                $saved->push($procurement);
            } else {
                // Create new record
                $saved->push(Procurement::create([
                    'product_id'       => $row['product_id'] ?? null,
                    'item_name'        => $row['item_name'],
                    'supplier'         => $row['supplier'] ?? null,
                    'quantity'         => $row['quantity'],
                    'unit'             => $row['unit'] ?? 'kg',
                    'status'           => $row['status'] ?? 'pending',
                    'procurement_date' => $procurementDate,
                ]));
            }
        }

        return $saved;
    }

    public function delete(int $id): bool
    {
        return Procurement::findOrFail($id)->delete();
    }
}