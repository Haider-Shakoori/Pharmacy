<?php

namespace App\Services\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\ProductBatch;
use App\Services\Accounting\OperationalAccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(
        private readonly StockMovementService $movements,
        private readonly OperationalAccountingService $accounting,
    ) {}

    public function post(
        ProductBatch $batch,
        string $quantityDelta,
        string $reasonCode,
        string $reason,
        int $userId,
    ): InventoryAdjustment {
        return DB::transaction(function () use ($batch, $quantityDelta, $reasonCode, $reason, $userId): InventoryAdjustment {
            $locked = ProductBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($locked->status === 'recalled') {
                throw ValidationException::withMessages([
                    'product_batch_id' => 'Recalled stock cannot be adjusted until its status is reviewed.',
                ]);
            }

            $adjustment = InventoryAdjustment::query()->create([
                'number' => 'ADJ-'.now()->format('Ymd').'-'.Str::upper(Str::ulid()->toBase32()),
                'stock_location_id' => $locked->stock_location_id,
                'reason_code' => $reasonCode,
                'status' => 'draft',
                'notes' => $reason,
                'created_by' => $userId,
            ]);

            $line = $adjustment->lines()->create([
                'product_batch_id' => $locked->id,
                'quantity_delta' => $quantityDelta,
                'reason' => $reason,
            ]);

            $this->movements->record(
                $locked,
                $quantityDelta,
                'adjustment',
                InventoryAdjustment::class,
                $adjustment->id,
                "adjustment:{$adjustment->id}:{$line->id}",
                $userId,
                $line->id,
                $reason,
                $locked->purchase_cost,
                ['reason_code' => $reasonCode],
            );

            $adjustment->update([
                'status' => 'posted',
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            $this->accounting->postInventoryAdjustment($adjustment->fresh(['lines.batch']));

            return $adjustment->fresh(['lines']);
        });
    }
}
