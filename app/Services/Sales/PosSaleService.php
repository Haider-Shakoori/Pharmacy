<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleBatchAllocation;
use App\Models\SaleLine;
use App\Models\SalePayment;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\DailyClosing\BusinessDateResolver;
use App\Services\Inventory\FefoAllocator;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    public function __construct(
        private readonly FefoAllocator $allocator,
        private readonly BusinessDateResolver $businessDates,
        private readonly TenantContext $tenantContext,
    ) {}

    public function checkout(User $user, array $data): Sale
    {
        return DB::transaction(function () use ($user, $data): Sale {
            $existing = Sale::query()->where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing) {
                return $existing->load(['lines.allocations.batch', 'payments', 'customer', 'location']);
            }

            $tenant = $this->tenantContext->tenant();
            $businessDate = $this->businessDates->resolve($tenant);
            $location = StockLocation::query()->where('is_active', true)->findOrFail($data['stock_location_id']);
            $customer = isset($data['customer_id'])
                ? Customer::query()->where('is_active', true)->findOrFail($data['customer_id'])
                : null;

            $sale = Sale::query()->create([
                'sale_number' => $this->saleNumber($businessDate),
                'stock_location_id' => $location->id,
                'customer_id' => $customer?->id,
                'business_date' => $businessDate,
                'status' => 'processing',
                'currency' => 'AFN',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
                'paid_total' => 0,
                'due_total' => 0,
                'change_total' => 0,
                'payment_status' => 'unpaid',
                'idempotency_key' => $data['idempotency_key'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $subtotal = BigDecimal::zero();
            $discountTotal = BigDecimal::zero();

            foreach ($data['lines'] as $index => $input) {
                $medicine = Medicine::query()->where('is_active', true)->findOrFail($input['medicine_id']);
                $quantity = BigDecimal::of((string) $input['quantity']);
                $basePrice = $this->fefoSalePrice($medicine, $location);
                $requestedPrice = isset($input['unit_price'])
                    ? BigDecimal::of((string) $input['unit_price'])
                    : $basePrice;

                if (! $requestedPrice->isEqualTo($basePrice) && ! $user->hasPermission('pos.price_override')) {
                    throw ValidationException::withMessages([
                        "lines.$index.unit_price" => 'You do not have permission to override the sale price.',
                    ]);
                }

                $lineSubtotal = $quantity->multipliedBy($requestedPrice);
                $discount = BigDecimal::of((string) ($input['discount_amount'] ?? '0'));

                if ($discount->isGreaterThan(BigDecimal::zero()) && ! $user->hasPermission('pos.discount')) {
                    throw ValidationException::withMessages([
                        "lines.$index.discount_amount" => 'You do not have permission to apply discounts.',
                    ]);
                }

                if ($discount->isGreaterThan($lineSubtotal)) {
                    throw ValidationException::withMessages([
                        "lines.$index.discount_amount" => 'Discount cannot exceed the line subtotal.',
                    ]);
                }

                $lineTotal = $lineSubtotal->minus($discount);
                $line = SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'medicine_id' => $medicine->id,
                    'description' => trim($medicine->brand_name.' '.($medicine->strength ?? '')),
                    'sale_unit' => $medicine->sale_unit,
                    'quantity' => $this->decimal($quantity),
                    'unit_price' => $this->decimal($requestedPrice),
                    'discount_amount' => $this->decimal($discount),
                    'tax_amount' => '0.0000',
                    'line_total' => $this->decimal($lineTotal),
                    'cost_total' => '0.0000',
                    'prescription_required' => $medicine->prescription_required,
                ]);

                $allocations = $this->allocator->consume(
                    $medicine,
                    $location,
                    $this->decimal($quantity),
                    'sale',
                    $sale->id,
                    "sale:{$sale->id}:line:{$line->id}",
                    $user->id,
                    "POS {$sale->sale_number}",
                );

                $costTotal = BigDecimal::zero();

                foreach ($allocations as $allocation) {
                    $costTotal = $costTotal->plus(
                        BigDecimal::of($allocation['quantity'])->multipliedBy(BigDecimal::of($allocation['unit_cost'])),
                    );

                    SaleBatchAllocation::query()->create([
                        'sale_line_id' => $line->id,
                        ...$allocation,
                    ]);
                }

                $line->update(['cost_total' => $this->decimal($costTotal)]);
                $subtotal = $subtotal->plus($lineSubtotal);
                $discountTotal = $discountTotal->plus($discount);
            }

            $grandTotal = $subtotal->minus($discountTotal);
            [$paidTotal, $dueTotal, $changeTotal] = $this->postSettlements(
                $sale,
                $user,
                $customer,
                $data['payments'],
                $grandTotal,
            );

            $sale->update([
                'subtotal' => $this->decimal($subtotal),
                'discount_total' => $this->decimal($discountTotal),
                'grand_total' => $this->decimal($grandTotal),
                'paid_total' => $this->decimal($paidTotal),
                'due_total' => $this->decimal($dueTotal),
                'change_total' => $this->decimal($changeTotal),
                'payment_status' => $dueTotal->isZero() ? 'paid' : ($paidTotal->isZero() ? 'credit' : 'partial'),
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $sale->fresh(['lines.allocations.batch', 'payments', 'customer', 'location.branch']);
        });
    }

    private function fefoSalePrice(Medicine $medicine, StockLocation $location): BigDecimal
    {
        $batch = ProductBatch::query()
            ->where('medicine_id', $medicine->id)
            ->where('stock_location_id', $location->id)
            ->where('status', 'active')
            ->where('available_quantity', '>', 0)
            ->whereNotNull('sale_price')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->first();

        if (! $batch) {
            throw ValidationException::withMessages([
                'lines' => "No sellable priced stock is available for {$medicine->brand_name}.",
            ]);
        }

        return BigDecimal::of($batch->sale_price);
    }

    private function postSettlements(Sale $sale, User $user, ?Customer $customer, array $payments, BigDecimal $grandTotal): array
    {
        $paid = BigDecimal::zero();
        $credit = BigDecimal::zero();
        $hasCash = false;

        foreach ($payments as $payment) {
            $amount = BigDecimal::of((string) $payment['amount']);

            if ($payment['method'] === 'credit') {
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'A customer is required for credit sales.']);
                }

                $credit = $credit->plus($amount);
            } else {
                $paid = $paid->plus($amount);
                $hasCash = $hasCash || $payment['method'] === 'cash';
            }

            SalePayment::query()->create([
                'sale_id' => $sale->id,
                'payment_number' => 'PAY-'.Str::upper(Str::ulid()),
                'method' => $payment['method'],
                'amount' => $this->decimal($amount),
                'currency' => 'AFN',
                'reference' => $payment['reference'] ?? null,
                'paid_at' => now(),
                'created_by' => $user->id,
            ]);
        }

        $settled = $paid->plus($credit);

        if ($settled->isLessThan($grandTotal)) {
            throw ValidationException::withMessages(['payments' => 'Payments and credit do not cover the sale total.']);
        }

        if ($credit->isGreaterThan(BigDecimal::zero())) {
            if ($credit->isGreaterThan(BigDecimal::of($customer->credit_limit))) {
                throw ValidationException::withMessages(['payments' => 'Credit amount exceeds this customer credit limit.']);
            }

            if ($settled->isGreaterThan($grandTotal)) {
                throw ValidationException::withMessages(['payments' => 'Credit sales cannot exceed the sale total.']);
            }
        }

        $change = $settled->minus($grandTotal);

        if ($change->isGreaterThan(BigDecimal::zero()) && ! $hasCash) {
            throw ValidationException::withMessages(['payments' => 'Overpayment can only be returned as cash change.']);
        }

        return [$grandTotal->minus($credit), $credit, $change];
    }

    private function saleNumber(string $businessDate): string
    {
        return 'POS-'.str_replace('-', '', $businessDate).'-'.Str::upper(substr((string) Str::ulid(), -10));
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
