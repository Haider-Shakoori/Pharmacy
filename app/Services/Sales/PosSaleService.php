<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\SaleBatchAllocation;
use App\Models\SaleLine;
use App\Models\SalePayment;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Accounting\OperationalAccountingService;
use App\Services\DailyClosing\BusinessDateResolver;
use App\Services\DailyClosing\DailyClosingService;
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
        private readonly OperationalAccountingService $accounting,
        private readonly BusinessDateResolver $businessDates,
        private readonly DailyClosingService $dailyClosing,
        private readonly TenantContext $tenantContext,
    ) {}

    public function checkout(User $user, array $data): Sale
    {
        $allowInactiveReferences = (bool) ($data['_allow_inactive_references'] ?? false);

        return DB::transaction(function () use ($user, $data, $allowInactiveReferences): Sale {
            $existing = Sale::query()->where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing) {
                return $existing->load(['lines.allocations.batch', 'payments', 'customer', 'location']);
            }

            $tenant = $this->tenantContext->tenant();
            $businessDate = $this->businessDates->resolve($tenant);
            $locationQuery = StockLocation::query();
            if (! $allowInactiveReferences) {
                $locationQuery->where('is_active', true);
            }
            $location = $locationQuery->findOrFail($data['stock_location_id']);
            if ($this->dailyClosing->salesBlocked($location, $businessDate)) {
                throw ValidationException::withMessages([
                    'closing' => 'Sales are blocked because this business day is finalized. Reopen Daily Closing before posting another sale.',
                ]);
            }

            $customer = null;
            if (isset($data['customer_id'])) {
                $customerQuery = Customer::query();
                if (! $allowInactiveReferences) {
                    $customerQuery->where('is_active', true);
                }
                $customer = $customerQuery->findOrFail($data['customer_id']);
            }

            $hasPrescriptionItem = Medicine::query()
                ->whereIn('id', collect($data['lines'])->pluck('medicine_id'))
                ->where('prescription_required', true)
                ->exists();

            if ($hasPrescriptionItem && blank($data['prescription_reference'] ?? null)) {
                throw ValidationException::withMessages([
                    'prescription_reference' => 'A prescription reference is required for prescription-only medicines.',
                ]);
            }

            $sale = Sale::query()->create([
                'sale_number' => $this->saleNumber($businessDate),
                'stock_location_id' => $location->id,
                'customer_id' => $customer?->id,
                'prescription_reference' => $data['prescription_reference'] ?? null,
                'prescriber_name' => $data['prescriber_name'] ?? null,
                'prescription_date' => $data['prescription_date'] ?? null,
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
                $medicineQuery = Medicine::query();
                if (! $allowInactiveReferences) {
                    $medicineQuery->where('is_active', true);
                }
                $medicine = $medicineQuery->findOrFail($input['medicine_id']);
                $quantity = BigDecimal::of((string) $input['quantity']);
                $discount = BigDecimal::of((string) ($input['discount_amount'] ?? '0'));
                $overridePrice = (bool) ($input['override_price'] ?? false);

                if ($overridePrice && ! $user->hasPermission('pos.price_override')) {
                    throw ValidationException::withMessages([
                        "lines.$index.unit_price" => 'You do not have permission to override the sale price.',
                    ]);
                }

                if ($overridePrice && ! isset($input['unit_price'])) {
                    throw ValidationException::withMessages([
                        "lines.$index.unit_price" => 'Enter the override price.',
                    ]);
                }

                if ($discount->isGreaterThan(BigDecimal::zero()) && ! $user->hasPermission('pos.discount')) {
                    throw ValidationException::withMessages([
                        "lines.$index.discount_amount" => 'You do not have permission to apply discounts.',
                    ]);
                }

                $line = SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'medicine_id' => $medicine->id,
                    'description' => trim($medicine->brand_name.' '.($medicine->strength ?? '')),
                    'sale_unit' => $medicine->sale_unit,
                    'quantity' => $this->decimal($quantity),
                    'unit_price' => '0.0000',
                    'discount_amount' => '0.0000',
                    'tax_amount' => '0.0000',
                    'line_total' => '0.0000',
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
                    true,
                );

                $costTotal = BigDecimal::zero();
                $lineSubtotal = BigDecimal::zero();
                $overrideUnitPrice = $overridePrice ? BigDecimal::of((string) $input['unit_price']) : null;

                foreach ($allocations as $allocation) {
                    $allocationQuantity = BigDecimal::of($allocation['quantity']);
                    $costTotal = $costTotal->plus(
                        $allocationQuantity->multipliedBy(BigDecimal::of($allocation['unit_cost'])),
                    );

                    $chargedUnitPrice = $overrideUnitPrice ?? BigDecimal::of((string) $allocation['unit_price']);
                    $allocationTotal = $allocationQuantity->multipliedBy($chargedUnitPrice);
                    $lineSubtotal = $lineSubtotal->plus($allocationTotal);

                    SaleBatchAllocation::query()->create([
                        'sale_line_id' => $line->id,
                        'product_batch_id' => $allocation['product_batch_id'],
                        'stock_movement_id' => $allocation['stock_movement_id'],
                        'quantity' => $allocation['quantity'],
                        'unit_cost' => $allocation['unit_cost'],
                        'unit_price' => $this->decimal($chargedUnitPrice),
                        'line_total' => $this->decimal($allocationTotal),
                    ]);
                }

                if ($discount->isGreaterThan($lineSubtotal)) {
                    throw ValidationException::withMessages([
                        "lines.$index.discount_amount" => 'Discount cannot exceed the line subtotal.',
                    ]);
                }

                $lineTotal = $lineSubtotal->minus($discount);
                $weightedUnitPrice = $lineSubtotal->dividedBy($quantity, 4, RoundingMode::HalfUp);
                $line->update([
                    'unit_price' => $this->decimal($weightedUnitPrice),
                    'discount_amount' => $this->decimal($discount),
                    'line_total' => $this->decimal($lineTotal),
                    'cost_total' => $this->decimal($costTotal),
                ]);

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

            $this->accounting->postSale($sale->fresh(['lines', 'payments']));

            return $sale->fresh(['lines.allocations.batch', 'payments', 'customer', 'location.branch']);
        });
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
