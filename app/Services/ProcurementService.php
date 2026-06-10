<?php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\Procurement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcurementService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

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

    public function store(array $data, bool $silent = false): Procurement
    {
        $procurement = Procurement::create([
            'product_id'       => $data['product_id'] ?? null,
            'item_name'        => $data['item_name'],
            'supplier'         => $data['supplier'] ?? null,
            'quantity'         => $data['quantity'],
            'unit'             => $data['unit'] ?? 'kg',
            'status'           => $data['status'] ?? 'pending',
            'procurement_date' => $data['procurement_date'] ?? now()->toDateString(),
        ]);

        if (! $silent) {
            $this->realtime->emit(
                RealtimeModule::PROCUREMENT,
                RealtimeAction::CREATED,
                ['id' => $procurement->id]
            );
        }

        return $procurement;
    }

    public function update(int $id, array $data, bool $silent = false): Procurement
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

        $updated = $procurement->fresh();

        if (! $silent) {
            $this->realtime->emit(
                RealtimeModule::PROCUREMENT,
                RealtimeAction::UPDATED,
                ['id' => $updated->id]
            );
        }

        return $updated;
    }

    public function storeBulk(array $rows, ?string $date = null): Collection
    {
        $procurementDate = $date ?? now()->toDateString();

        $saved = DB::transaction(function () use ($rows, $procurementDate) {
            $result = collect();

            foreach ($rows as $row) {
                $row['procurement_date'] = $procurementDate;

                if (isset($row['id']) && ! empty($row['id'])) {
                    $result->push($this->update($row['id'], $row, silent: true));
                } else {
                    $result->push($this->store($row, silent: true));
                }
            }

            return $result;
        });

        $this->realtime->emit(
            RealtimeModule::PROCUREMENT,
            RealtimeAction::BULK_CREATED,
            [
                'count' => $saved->count(),
                'ids'   => $saved->pluck('id')->values(),
            ]
        );

        return $saved;
    }

    public function delete(int $id): bool
    {
        $procurement = Procurement::findOrFail($id);
        $deleted = $procurement->delete();

        $this->realtime->emit(
            RealtimeModule::PROCUREMENT,
            RealtimeAction::DELETED,
            ['id' => $id]
        );

        return $deleted;
    }
}