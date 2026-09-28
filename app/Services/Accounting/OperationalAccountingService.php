<?php

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\DailyClosing;
use App\Models\DailyClosingEvent;
use App\Models\InventoryAdjustment;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class OperationalAccountingService
{
    public function __construct(
        private readonly AccountingProvisioner $accounts,
        private readonly LedgerPostingService $ledger,
    ) {}

    public function postSale(Sale $sale): JournalEntry
    {
        $sale->loadMissing(['lines', 'payments']);
        $lines = [];
        $cash = BigDecimal::zero();
        $bank = BigDecimal::zero();
        $mobile = BigDecimal::zero();
        $credit = BigDecimal::zero();

        foreach ($sale->payments as $payment) {
            $amount = BigDecimal::of($payment->amount);

            match ($payment->method) {
                'cash' => $cash = $cash->plus($amount),
                'bank' => $bank = $bank->plus($amount),
                'mobile' => $mobile = $mobile->plus($amount),
                'credit' => $credit = $credit->plus($amount),
                default => throw ValidationException::withMessages(['accounting' => "Unsupported sale payment method {$payment->method}."]),
            };
        }

        $cash = $cash->minus(BigDecimal::of($sale->change_total));
        $this->appendDebit($lines, 'cash_on_hand', $cash, $sale->stock_location_id, 'POS cash collected');
        $this->appendDebit($lines, 'bank', $bank, $sale->stock_location_id, 'POS bank collected');
        $this->appendDebit($lines, 'mobile_money', $mobile, $sale->stock_location_id, 'POS mobile collected');
        $this->appendDebit(
            $lines,
            'accounts_receivable',
            $credit,
            $sale->stock_location_id,
            'Customer credit sale',
            $sale->customer_id ? Customer::class : null,
            $sale->customer_id,
        );

        $discount = BigDecimal::of($sale->discount_total);
        $subtotal = BigDecimal::of($sale->subtotal);
        $tax = BigDecimal::of($sale->tax_total);
        $cost = $sale->lines->reduce(
            fn (BigDecimal $carry, $line) => $carry->plus(BigDecimal::of($line->cost_total)),
            BigDecimal::zero(),
        );

        $this->appendDebit($lines, 'sales_discounts', $discount, $sale->stock_location_id, 'POS discount');
        $this->appendCredit($lines, 'sales_revenue', $subtotal, $sale->stock_location_id, 'POS sales revenue');
        $this->appendCredit($lines, 'sales_tax_payable', $tax, $sale->stock_location_id, 'Sales tax payable');
        $this->appendDebit($lines, 'cost_of_goods_sold', $cost, $sale->stock_location_id, 'Cost of goods sold');
        $this->appendCredit($lines, 'inventory', $cost, $sale->stock_location_id, 'Inventory relieved at recorded batch cost');

        return $this->ledger->post([
            'business_date' => $sale->business_date->toDateString(),
            'occurred_at' => $sale->completed_at ?? now(),
            'currency' => $sale->currency,
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'source_event' => 'completed',
            'source_number' => $sale->sale_number,
            'idempotency_key' => 'accounting:sale:'.$sale->id,
            'description' => 'POS sale '.$sale->sale_number,
            'posted_by' => $sale->created_by,
        ], $lines);
    }

    public function postSaleReturn(SaleReturn $return): JournalEntry
    {
        $return->loadMissing(['refunds', 'lines.allocations.originalAllocation']);
        $lines = [];
        $refundTotal = BigDecimal::zero();

        foreach ($return->refunds as $refund) {
            $amount = BigDecimal::of($refund->amount);
            $refundTotal = $refundTotal->plus($amount);
            $key = $this->paymentAccountKey($refund->method, true);
            $this->appendCredit(
                $lines,
                $key,
                $amount,
                $return->stock_location_id,
                'Sale return refund',
                $refund->method === 'credit' && $return->sale?->customer_id ? Customer::class : null,
                $refund->method === 'credit' ? $return->sale?->customer_id : null,
            );
        }

        $this->appendDebit($lines, 'sales_returns', $refundTotal, $return->stock_location_id, 'Sales return');

        $restockedCost = BigDecimal::zero();
        foreach ($return->lines as $returnLine) {
            foreach ($returnLine->allocations as $allocation) {
                if (! $allocation->restocked) {
                    continue;
                }

                $restockedCost = $restockedCost->plus(
                    BigDecimal::of($allocation->quantity)
                        ->multipliedBy(BigDecimal::of($allocation->originalAllocation->unit_cost)),
                );
            }
        }

        $this->appendDebit($lines, 'inventory', $restockedCost, $return->stock_location_id, 'Returned sellable stock restored');
        $this->appendCredit($lines, 'cost_of_goods_sold', $restockedCost, $return->stock_location_id, 'Reverse COGS for restocked return');

        return $this->ledger->post([
            'business_date' => $return->business_date->toDateString(),
            'occurred_at' => $return->completed_at,
            'currency' => 'AFN',
            'source_type' => SaleReturn::class,
            'source_id' => $return->id,
            'source_event' => 'completed',
            'source_number' => $return->return_number,
            'idempotency_key' => 'accounting:sale-return:'.$return->id,
            'description' => 'Sale return '.$return->return_number,
            'posted_by' => $return->created_by,
        ], $lines);
    }

    public function postPurchaseInvoice(PurchaseInvoice $invoice): JournalEntry
    {
        $amount = BigDecimal::of($invoice->grand_total);
        $lines = [];

        $this->appendDebit($lines, 'inventory', $amount, null, 'Inventory acquisition', Supplier::class, $invoice->supplier_id);
        $this->appendCredit($lines, 'accounts_payable', $amount, null, 'Supplier payable', Supplier::class, $invoice->supplier_id);

        return $this->ledger->post([
            'business_date' => $invoice->invoice_date->toDateString(),
            'occurred_at' => $invoice->created_at ?? now(),
            'currency' => $invoice->currency,
            'source_type' => PurchaseInvoice::class,
            'source_id' => $invoice->id,
            'source_event' => 'recorded',
            'source_number' => $invoice->invoice_number,
            'idempotency_key' => 'accounting:purchase-invoice:'.$invoice->id,
            'description' => 'Supplier invoice '.$invoice->invoice_number,
            'posted_by' => $invoice->created_by,
        ], $lines);
    }

    public function postSupplierPayment(SupplierPayment $payment): JournalEntry
    {
        $amount = BigDecimal::of($payment->amount);
        $lines = [];
        $key = $this->paymentAccountKey($payment->method);

        $this->appendDebit($lines, 'accounts_payable', $amount, null, 'Supplier liability settled', Supplier::class, $payment->supplier_id);
        $this->appendCredit($lines, $key, $amount, null, 'Supplier payment', Supplier::class, $payment->supplier_id);

        return $this->ledger->post([
            'business_date' => $payment->paid_at->toDateString(),
            'occurred_at' => $payment->paid_at,
            'currency' => $payment->currency,
            'source_type' => SupplierPayment::class,
            'source_id' => $payment->id,
            'source_event' => 'paid',
            'source_number' => $payment->payment_number,
            'idempotency_key' => 'accounting:supplier-payment:'.$payment->id,
            'reference' => $payment->reference,
            'description' => 'Supplier payment '.$payment->payment_number,
            'posted_by' => $payment->created_by,
        ], $lines);
    }

    public function postInventoryAdjustment(InventoryAdjustment $adjustment): ?JournalEntry
    {
        $adjustment->loadMissing('lines.batch');
        $increase = BigDecimal::zero();
        $decrease = BigDecimal::zero();

        foreach ($adjustment->lines as $line) {
            $value = BigDecimal::of($line->quantity_delta)->multipliedBy(BigDecimal::of($line->batch->purchase_cost));
            if ($value->isPositive()) {
                $increase = $increase->plus($value);
            } elseif ($value->isNegative()) {
                $decrease = $decrease->plus($value->abs());
            }
        }

        $lines = [];
        if ($increase->isPositive()) {
            $this->appendDebit($lines, 'inventory', $increase, $adjustment->stock_location_id, 'Inventory adjustment increase');
            $this->appendCredit($lines, 'inventory_adjustment', $increase, $adjustment->stock_location_id, 'Inventory adjustment gain');
        }
        if ($decrease->isPositive()) {
            $this->appendDebit($lines, 'inventory_adjustment', $decrease, $adjustment->stock_location_id, 'Inventory adjustment loss');
            $this->appendCredit($lines, 'inventory', $decrease, $adjustment->stock_location_id, 'Inventory adjustment decrease');
        }

        if ($lines === []) {
            return null;
        }

        return $this->ledger->post([
            'business_date' => $adjustment->posted_at?->toDateString() ?? now()->toDateString(),
            'occurred_at' => $adjustment->posted_at ?? now(),
            'currency' => 'AFN',
            'source_type' => InventoryAdjustment::class,
            'source_id' => $adjustment->id,
            'source_event' => 'posted',
            'source_number' => $adjustment->number,
            'idempotency_key' => 'accounting:inventory-adjustment:'.$adjustment->id,
            'description' => 'Inventory adjustment '.$adjustment->number,
            'posted_by' => $adjustment->posted_by,
        ], $lines);
    }

    public function postDailyClosingVariance(DailyClosing $closing, DailyClosingEvent $event): ?JournalEntry
    {
        $variance = BigDecimal::of($closing->variance ?? '0');
        if ($variance->isZero()) {
            return null;
        }

        $amount = $variance->abs();
        $lines = [];

        if ($variance->isPositive()) {
            $this->appendDebit($lines, 'cash_on_hand', $amount, $closing->stock_location_id, 'Cash over at Daily Closing');
            $this->appendCredit($lines, 'cash_over_short', $amount, $closing->stock_location_id, 'Cash over at Daily Closing');
        } else {
            $this->appendDebit($lines, 'cash_over_short', $amount, $closing->stock_location_id, 'Cash shortage at Daily Closing');
            $this->appendCredit($lines, 'cash_on_hand', $amount, $closing->stock_location_id, 'Cash shortage at Daily Closing');
        }

        return $this->ledger->post([
            'business_date' => $closing->business_date->toDateString(),
            'occurred_at' => $event->occurred_at,
            'currency' => 'AFN',
            'source_type' => DailyClosing::class,
            'source_id' => $closing->id,
            'source_event' => 'variance:'.$event->id,
            'source_number' => $closing->business_date->toDateString(),
            'idempotency_key' => 'accounting:daily-closing-variance:'.$event->id,
            'description' => 'Daily Closing cash variance '.$closing->business_date->toDateString(),
            'posted_by' => $event->actor_id,
        ], $lines);
    }

    public function reverseDailyClosingVariance(DailyClosing $closing, string $reason, ?int $actorId): void
    {
        JournalEntry::query()
            ->where('source_type', DailyClosing::class)
            ->where('source_id', $closing->id)
            ->where('source_event', 'like', 'variance:%')
            ->where('status', 'posted')
            ->get()
            ->each(fn (JournalEntry $entry) => $this->ledger->reverse($entry, $reason, $actorId));
    }

    private function paymentAccountKey(string $method, bool $allowCredit = false): string
    {
        return match ($method) {
            'cash' => 'cash_on_hand',
            'bank' => 'bank',
            'mobile' => 'mobile_money',
            'hawala' => 'hawala_clearing',
            'other' => 'other_clearing',
            'credit' => $allowCredit ? 'accounts_receivable' : throw ValidationException::withMessages(['accounting' => 'Credit is not a cash settlement account.']),
            default => throw ValidationException::withMessages(['accounting' => "Unsupported payment method {$method}."]),
        };
    }

    private function appendDebit(
        array &$lines,
        string $accountKey,
        BigDecimal $amount,
        ?string $locationId,
        string $memo,
        ?string $counterpartyType = null,
        ?string $counterpartyId = null,
    ): void {
        if ($amount->isZero()) {
            return;
        }

        $lines[] = [
            'account' => $this->accounts->account($accountKey),
            'stock_location_id' => $locationId,
            'debit' => $this->decimal($amount),
            'credit' => '0.0000',
            'counterparty_type' => $counterpartyType,
            'counterparty_id' => $counterpartyId,
            'memo' => $memo,
        ];
    }

    private function appendCredit(
        array &$lines,
        string $accountKey,
        BigDecimal $amount,
        ?string $locationId,
        string $memo,
        ?string $counterpartyType = null,
        ?string $counterpartyId = null,
    ): void {
        if ($amount->isZero()) {
            return;
        }

        $lines[] = [
            'account' => $this->accounts->account($accountKey),
            'stock_location_id' => $locationId,
            'debit' => '0.0000',
            'credit' => $this->decimal($amount),
            'counterparty_type' => $counterpartyType,
            'counterparty_id' => $counterpartyId,
            'memo' => $memo,
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
