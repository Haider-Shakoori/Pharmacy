<?php

namespace App\Services\Sales;

use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\SaleReturnAllocation;
use App\Models\SaleReturnLine;
use App\Models\SaleReturnRefund;
use App\Models\User;
use App\Services\Accounting\OperationalAccountingService;
use App\Services\DailyClosing\DailyClosingService;
use App\Services\Inventory\StockMovementService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleReturnService
{
    public function __construct(
        private readonly StockMovementService $movements,
        private readonly OperationalAccountingService $accounting,
        private readonly DailyClosingService $dailyClosing,
    ) {}

    public function process(Sale $sale, User $user, array $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $user, $data): SaleReturn {
            $existing = SaleReturn::query()->where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing) {
                return $existing->load(['lines.allocations', 'refunds']);
            }

            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages(['sale' => 'Only completed sales can be returned.']);
            }

            if ($this->dailyClosing->salesBlocked($sale->location)) {
                throw ValidationException::withMessages([
                    'closing' => 'This business day is finalized. Reopen Daily Closing before posting a return.',
                ]);
            }

            $saleReturn = SaleReturn::query()->create([
                'return_number' => 'RET-'.Str::upper(Str::ulid()),
                'sale_id' => $sale->id,
                'stock_location_id' => $sale->stock_location_id,
                'business_date' => $this->dailyClosing->businessDate(),
                'status' => 'processing',
                'refund_total' => 0,
                'idempotency_key' => $data['idempotency_key'],
                'reason' => $data['reason'],
                'created_by' => $user->id,
                'completed_at' => now(),
            ]);

            $refundTotal = BigDecimal::zero();

            foreach ($data['lines'] as $index => $input) {
                $line = SaleLine::query()
                    ->where('sale_id', $sale->id)
                    ->lockForUpdate()
                    ->findOrFail($input['sale_line_id']);

                $quantity = BigDecimal::of((string) $input['quantity']);

                if ($quantity->isZero()) {
                    continue;
                }

                $alreadyReturned = BigDecimal::of((string) SaleReturnLine::query()
                    ->where('sale_line_id', $line->id)
                    ->whereHas('saleReturn', fn ($query) => $query->where('status', 'completed'))
                    ->sum('quantity'));

                $remainingReturnable = BigDecimal::of($line->quantity)->minus($alreadyReturned);

                if ($quantity->isGreaterThan($remainingReturnable)) {
                    throw ValidationException::withMessages([
                        "lines.$index.quantity" => 'Return quantity exceeds the remaining sold quantity.',
                    ]);
                }

                $unitNet = BigDecimal::of($line->line_total)
                    ->dividedBy(BigDecimal::of($line->quantity), 8, RoundingMode::HalfUp);
                $refundAmount = $unitNet->multipliedBy($quantity);

                $returnLine = SaleReturnLine::query()->create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_line_id' => $line->id,
                    'medicine_id' => $line->medicine_id,
                    'quantity' => $this->decimal($quantity),
                    'refund_amount' => $this->decimal($refundAmount),
                    'disposition' => 'restock',
                ]);

                $remaining = $quantity;
                $restockedAll = true;

                foreach ($line->allocations()->with('batch')->lockForUpdate()->get() as $allocation) {
                    if ($remaining->isZero()) {
                        break;
                    }

                    $previouslyReturned = BigDecimal::of((string) SaleReturnAllocation::query()
                        ->where('sale_batch_allocation_id', $allocation->id)
                        ->sum('quantity'));
                    $availableFromAllocation = BigDecimal::of($allocation->quantity)->minus($previouslyReturned);

                    if ($availableFromAllocation->isLessThanOrEqualTo(BigDecimal::zero())) {
                        continue;
                    }

                    $take = $availableFromAllocation->isLessThan($remaining) ? $availableFromAllocation : $remaining;
                    $batch = ProductBatch::query()->lockForUpdate()->findOrFail($allocation->product_batch_id);
                    $eligibleToRestock = in_array($batch->status, ['active', 'depleted'], true) && ! $batch->isExpired();
                    $movement = null;

                    if ($eligibleToRestock) {
                        $movement = $this->movements->record(
                            $batch,
                            $this->decimal($take),
                            'sale_return',
                            'sale_return',
                            $saleReturn->id,
                            "return:{$saleReturn->id}:allocation:{$allocation->id}",
                            $user->id,
                            $returnLine->id,
                            "Return {$saleReturn->return_number} for {$sale->sale_number}",
                            $allocation->unit_cost,
                        );
                    } else {
                        $restockedAll = false;
                    }

                    SaleReturnAllocation::query()->create([
                        'sale_return_line_id' => $returnLine->id,
                        'sale_batch_allocation_id' => $allocation->id,
                        'product_batch_id' => $batch->id,
                        'stock_movement_id' => $movement?->id,
                        'quantity' => $this->decimal($take),
                        'restocked' => $movement !== null,
                    ]);

                    $remaining = $remaining->minus($take);
                }

                if (! $remaining->isZero()) {
                    throw ValidationException::withMessages([
                        "lines.$index.quantity" => 'Original batch allocation could not cover this return quantity.',
                    ]);
                }

                if (! $restockedAll) {
                    $returnLine->update(['disposition' => 'not_restocked']);
                }

                $refundTotal = $refundTotal->plus($refundAmount);
            }

            if ($refundTotal->isZero()) {
                throw ValidationException::withMessages([
                    'lines' => 'Select at least one item quantity to return.',
                ]);
            }

            $submittedRefund = BigDecimal::zero();

            foreach ($data['refunds'] as $refund) {
                $amount = BigDecimal::of((string) $refund['amount']);
                $submittedRefund = $submittedRefund->plus($amount);

                SaleReturnRefund::query()->create([
                    'sale_return_id' => $saleReturn->id,
                    'method' => $refund['method'],
                    'amount' => $this->decimal($amount),
                    'currency' => 'AFN',
                    'reference' => $refund['reference'] ?? null,
                ]);
            }

            if (! $submittedRefund->isEqualTo($refundTotal)) {
                throw ValidationException::withMessages([
                    'refunds' => 'Refund settlement must equal the calculated return amount.',
                ]);
            }

            $saleReturn->update([
                'status' => 'completed',
                'refund_total' => $this->decimal($refundTotal),
            ]);

            $this->accounting->postSaleReturn($saleReturn->fresh(['sale', 'lines.allocations.originalAllocation', 'refunds']));

            return $saleReturn->fresh(['lines.allocations', 'refunds']);
        });
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
