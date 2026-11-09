<?php

namespace App\Services\Inventory;

use App\Models\ProductBatch;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(private readonly StockMovementService $movements) {}

    public function transfer(
        string $batchId,
        string $destinationId,
        string $quantity,
        string $requestId,
        ?int $actorId,
    ): array {
        return DB::transaction(function () use ($batchId, $destinationId, $quantity, $requestId, $actorId): array {
            $sourceKey = 'mobile-transfer:'.$requestId.':out';
            $destinationKey = 'mobile-transfer:'.$requestId.':in';
            $existing = StockMovement::query()->where('idempotency_key', $sourceKey)->first();
            if ($existing) {
                $metadata = $existing->metadata ?? [];
                if ($existing->product_batch_id !== $batchId ||
                    ($metadata['destination_id'] ?? null) !== $destinationId ||
                    ! BigDecimal::of($existing->quantity_delta)->abs()->isEqualTo(BigDecimal::of($quantity))) {
                    throw ValidationException::withMessages(['request_id' => 'Transfer key was used for different details.']);
                }
                $incoming = StockMovement::query()->where('idempotency_key', $destinationKey)->firstOrFail();

                return ['source_movement_id' => $existing->id, 'destination_movement_id' => $incoming->id, 'replayed' => true];
            }

            $source = ProductBatch::query()->lockForUpdate()->findOrFail($batchId);
            $destination = StockLocation::query()->where('is_active', true)->findOrFail($destinationId);
            if ($source->stock_location_id === $destination->id ||
                $source->branch_id !== $destination->branch_id ||
                $source->status !== 'active' || $source->isExpired()) {
                throw ValidationException::withMessages(['destination_id' => 'Transfer requires a different active location in the same branch and a sellable source batch.']);
            }
            $amount = BigDecimal::of($quantity);
            if ($amount->isLessThanOrEqualTo(BigDecimal::zero()) ||
                $amount->getScale() > 4 ||
                $amount->isGreaterThan(BigDecimal::of($source->available_quantity))) {
                throw ValidationException::withMessages(['quantity' => 'Quantity must be positive, have at most four decimals, and not exceed available stock.']);
            }
            $target = ProductBatch::query()
                ->where('medicine_id', $source->medicine_id)
                ->where('stock_location_id', $destination->id)
                ->where('batch_key', $source->batch_key)
                ->lockForUpdate()->first();
            if (! $target) {
                $target = ProductBatch::query()->create([
                    'medicine_id' => $source->medicine_id,
                    'supplier_id' => $source->supplier_id,
                    'purchase_order_id' => $source->purchase_order_id,
                    'goods_receipt_id' => $source->goods_receipt_id,
                    'branch_id' => $destination->branch_id,
                    'stock_location_id' => $destination->id,
                    'batch_number' => $source->batch_number,
                    'batch_key' => $source->batch_key,
                    'manufactured_at' => $source->manufactured_at,
                    'expires_at' => $source->expires_at,
                    'status' => 'active',
                    'received_quantity' => '0.0000',
                    'available_quantity' => '0.0000',
                    'purchase_cost' => $source->purchase_cost,
                    'sale_price' => $source->sale_price,
                ]);
            }
            $previousQuantity = BigDecimal::of($target->available_quantity);
            $combinedQuantity = $previousQuantity->plus($amount);
            $weightedCost = $previousQuantity->multipliedBy(BigDecimal::of($target->purchase_cost))
                ->plus($amount->multipliedBy(BigDecimal::of($source->purchase_cost)))
                ->dividedBy($combinedQuantity, 4, RoundingMode::HalfUp);
            $target->update([
                'received_quantity' => (string) BigDecimal::of($target->received_quantity)->plus($amount)->toScale(4, RoundingMode::HalfUp),
                'purchase_cost' => (string) $weightedCost,
            ]);
            $out = $this->movements->record(
                $source, '-'.$amount, 'transfer_out', self::class, $requestId,
                $sourceKey, $actorId, null, 'Location transfer', (string) $source->purchase_cost,
                ['destination_id' => $destination->id],
            );
            $in = $this->movements->record(
                $target, (string) $amount, 'transfer_in', self::class, $requestId,
                $destinationKey, $actorId, null, 'Location transfer', (string) $source->purchase_cost,
                ['source_id' => $source->stock_location_id],
            );

            return ['source_movement_id' => $out->id, 'destination_movement_id' => $in->id, 'replayed' => false];
        }, 3);
    }
}
