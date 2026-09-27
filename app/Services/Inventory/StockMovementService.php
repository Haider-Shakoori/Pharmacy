<?php

namespace App\Services\Inventory;

use App\Models\ProductBatch;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function record(
        ProductBatch $batch,
        string $quantityDelta,
        string $movementType,
        string $sourceType,
        string $sourceId,
        string $idempotencyKey,
        ?int $actorId,
        ?string $sourceLineId = null,
        ?string $reason = null,
        ?string $unitCost = null,
        array $metadata = [],
    ): StockMovement {
        return DB::transaction(function () use (
            $batch,
            $quantityDelta,
            $movementType,
            $sourceType,
            $sourceId,
            $idempotencyKey,
            $actorId,
            $sourceLineId,
            $reason,
            $unitCost,
            $metadata,
        ): StockMovement {
            $existing = StockMovement::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $sameOperation = $existing->product_batch_id === $batch->id
                    && $existing->source_type === $sourceType
                    && $existing->source_id === $sourceId
                    && BigDecimal::of($existing->quantity_delta)->isEqualTo(BigDecimal::of($quantityDelta));

                if (! $sameOperation) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This inventory idempotency key was already used for another operation.',
                    ]);
                }

                return $existing;
            }

            $locked = ProductBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $current = BigDecimal::of($locked->available_quantity);
            $delta = BigDecimal::of($quantityDelta);
            $after = $current->plus($delta);

            if ($after->isNegative()) {
                throw ValidationException::withMessages([
                    'quantity' => 'This stock movement would make the batch quantity negative.',
                ]);
            }

            $scaledAfter = (string) $after->toScale(4, RoundingMode::HalfUp);

            $locked->update([
                'available_quantity' => $scaledAfter,
                'last_movement_at' => now(),
                'status' => $after->isZero() && $locked->status === 'active'
                    ? 'depleted'
                    : $locked->status,
            ]);

            return StockMovement::query()->create([
                'product_batch_id' => $locked->id,
                'medicine_id' => $locked->medicine_id,
                'branch_id' => $locked->branch_id,
                'stock_location_id' => $locked->stock_location_id,
                'movement_type' => $movementType,
                'quantity_delta' => (string) $delta->toScale(4, RoundingMode::HalfUp),
                'balance_after' => $scaledAfter,
                'unit_cost' => $unitCost,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'actor_id' => $actorId,
                'occurred_at' => now(),
                'metadata' => $metadata ?: null,
            ]);
        });
    }
}
