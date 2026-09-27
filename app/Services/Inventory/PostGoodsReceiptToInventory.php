<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceipt;
use App\Models\ProductBatch;
use App\Models\StockLocation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostGoodsReceiptToInventory
{
    public function __construct(private readonly StockMovementService $movements) {}

    public function post(GoodsReceipt $receipt, StockLocation $location, ?int $actorId): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $location, $actorId): GoodsReceipt {
            $lockedReceipt = GoodsReceipt::query()
                ->with(['lines.orderLine', 'lines.medicine', 'purchaseOrder.lines'])
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($lockedReceipt->inventory_posted_at !== null) {
                return $lockedReceipt;
            }

            $location = StockLocation::query()
                ->with('branch')
                ->whereKey($location->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($lockedReceipt->lines as $receiptLine) {
                $orderLine = $receiptLine->orderLine;
                $received = BigDecimal::of($receiptLine->received_quantity);
                $bonus = BigDecimal::of($receiptLine->bonus_quantity);
                $incomingQuantity = $received->plus($bonus);

                if ($incomingQuantity->isLessThanOrEqualTo(BigDecimal::zero())) {
                    continue;
                }

                $batchKey = $this->batchKey(
                    $receiptLine->batch_number,
                    $receiptLine->expires_at?->toDateString(),
                    $receiptLine->id,
                );

                $batch = ProductBatch::query()
                    ->where('medicine_id', $receiptLine->medicine_id)
                    ->where('stock_location_id', $location->id)
                    ->where('batch_key', $batchKey)
                    ->lockForUpdate()
                    ->first();

                if (! $batch) {
                    $batch = ProductBatch::query()->create([
                        'medicine_id' => $receiptLine->medicine_id,
                        'supplier_id' => $lockedReceipt->supplier_id,
                        'purchase_order_id' => $lockedReceipt->purchase_order_id,
                        'goods_receipt_id' => $lockedReceipt->id,
                        'branch_id' => $location->branch_id,
                        'stock_location_id' => $location->id,
                        'batch_number' => $receiptLine->batch_number,
                        'batch_key' => $batchKey,
                        'manufactured_at' => $receiptLine->manufactured_at,
                        'expires_at' => $receiptLine->expires_at,
                        'status' => 'active',
                        'received_quantity' => '0.0000',
                        'available_quantity' => '0.0000',
                        'purchase_cost' => '0.0000',
                        'sale_price' => $receiptLine->sale_price,
                    ]);
                }

                $batch = ProductBatch::query()->lockForUpdate()->findOrFail($batch->id);
                $incomingCostTotal = $this->incomingCostTotal($receiptLine, $orderLine);
                $oldReceived = BigDecimal::of($batch->received_quantity);
                $oldCostTotal = $oldReceived->multipliedBy($batch->purchase_cost);
                $newReceived = $oldReceived->plus($incomingQuantity);
                $weightedCost = $newReceived->isZero()
                    ? BigDecimal::zero()
                    : $oldCostTotal->plus($incomingCostTotal)
                        ->dividedBy($newReceived, 8, RoundingMode::HalfUp);

                $effectiveIncomingCost = $incomingCostTotal
                    ->dividedBy($incomingQuantity, 8, RoundingMode::HalfUp);

                $batch->update([
                    'received_quantity' => (string) $newReceived->toScale(4, RoundingMode::HalfUp),
                    'purchase_cost' => (string) $weightedCost->toScale(4, RoundingMode::HalfUp),
                    'sale_price' => $receiptLine->sale_price ?? $batch->sale_price,
                    'status' => $batch->status === 'depleted' ? 'active' : $batch->status,
                ]);

                $this->movements->record(
                    $batch,
                    (string) $incomingQuantity->toScale(4, RoundingMode::HalfUp),
                    'purchase_receipt',
                    GoodsReceipt::class,
                    $lockedReceipt->id,
                    "grn:{$lockedReceipt->id}:{$receiptLine->id}",
                    $actorId,
                    $receiptLine->id,
                    'Goods received from supplier',
                    (string) $effectiveIncomingCost->toScale(4, RoundingMode::HalfUp),
                    ['bonus_quantity' => (string) $bonus->toScale(4, RoundingMode::HalfUp)],
                );

                $newOrderReceived = BigDecimal::of($orderLine->received_quantity)->plus($received);
                if ($newOrderReceived->isGreaterThan(BigDecimal::of($orderLine->ordered_quantity))) {
                    throw ValidationException::withMessages([
                        'receipt' => 'Posting this receipt would exceed the purchase-order quantity.',
                    ]);
                }

                $orderLine->update([
                    'received_quantity' => (string) $newOrderReceived->toScale(4, RoundingMode::HalfUp),
                ]);
            }

            $lockedReceipt->update([
                'stock_location_id' => $location->id,
                'status' => 'posted',
                'inventory_posted_at' => now(),
            ]);

            $purchaseOrder = $lockedReceipt->purchaseOrder()->with('lines')->firstOrFail();
            $fullyReceived = $purchaseOrder->lines->every(
                fn ($line) => BigDecimal::of($line->received_quantity)
                    ->isGreaterThanOrEqualTo(BigDecimal::of($line->ordered_quantity)),
            );

            $purchaseOrder->update(['status' => $fullyReceived ? 'received' : 'partially_received']);

            return $lockedReceipt->fresh(['lines', 'purchaseOrder', 'supplier']);
        });
    }

    private function incomingCostTotal($receiptLine, $orderLine): BigDecimal
    {
        $received = BigDecimal::of($receiptLine->received_quantity);
        $ordered = BigDecimal::of($orderLine->ordered_quantity);

        if ($ordered->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw ValidationException::withMessages(['receipt' => 'Purchase-order quantity must be greater than zero.']);
        }

        $ratio = $received->dividedBy($ordered, 12, RoundingMode::HalfUp);
        $base = $received->multipliedBy($receiptLine->unit_cost);
        $discount = BigDecimal::of($orderLine->discount_amount)->multipliedBy($ratio);
        $landed = BigDecimal::of($orderLine->landed_cost_allocated)->multipliedBy($ratio);
        $total = $base->minus($discount)->plus($landed);

        if ($total->isNegative()) {
            throw ValidationException::withMessages(['receipt' => 'Allocated receipt cost cannot be negative.']);
        }

        return $total;
    }

    private function batchKey(?string $batchNumber, ?string $expiresAt, string $fallback): string
    {
        $identity = filled($batchNumber)
            ? Str::lower(trim($batchNumber)).'|'.($expiresAt ?? 'no-expiry')
            : 'unbatched|'.($expiresAt ?? $fallback);

        return hash('sha256', $identity);
    }
}
