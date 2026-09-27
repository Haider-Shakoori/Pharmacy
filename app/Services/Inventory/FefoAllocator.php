<?php

namespace App\Services\Inventory;

use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\StockLocation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FefoAllocator
{
    public function __construct(private readonly StockMovementService $movements) {}

    public function consume(
        Medicine $medicine,
        StockLocation $location,
        string $quantity,
        string $sourceType,
        string $sourceId,
        string $idempotencyPrefix,
        ?int $actorId,
        ?string $reason = null,
    ): array {
        return DB::transaction(function () use (
            $medicine,
            $location,
            $quantity,
            $sourceType,
            $sourceId,
            $idempotencyPrefix,
            $actorId,
            $reason,
        ): array {
            $remaining = BigDecimal::of($quantity);

            if ($remaining->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(['quantity' => 'Requested stock quantity must be greater than zero.']);
            }

            $batches = ProductBatch::query()
                ->where('medicine_id', $medicine->id)
                ->where('stock_location_id', $location->id)
                ->where('status', 'active')
                ->where('available_quantity', '>', 0)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhereDate('expires_at', '>=', today());
                })
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $allocations = [];

            foreach ($batches as $batch) {
                if ($remaining->isZero()) {
                    break;
                }

                $available = BigDecimal::of($batch->available_quantity);
                $take = $available->isLessThan($remaining) ? $available : $remaining;

                $movement = $this->movements->record(
                    $batch,
                    '-'.(string) $take->toScale(4, RoundingMode::HalfUp),
                    'sale',
                    $sourceType,
                    $sourceId,
                    "{$idempotencyPrefix}:{$batch->id}",
                    $actorId,
                    null,
                    $reason ?? 'FEFO stock allocation',
                    $batch->purchase_cost,
                );

                $allocations[] = [
                    'product_batch_id' => $batch->id,
                    'quantity' => (string) $take->toScale(4, RoundingMode::HalfUp),
                    'stock_movement_id' => $movement->id,
                    'unit_cost' => $batch->purchase_cost,
                ];

                $remaining = $remaining->minus($take);
            }

            if (! $remaining->isZero()) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient eligible stock. Expired, quarantined and recalled batches are excluded.',
                ]);
            }

            return $allocations;
        });
    }
}
