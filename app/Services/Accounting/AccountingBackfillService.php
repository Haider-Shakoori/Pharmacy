<?php

namespace App\Services\Accounting;

use App\Models\DailyClosing;
use App\Models\InventoryAdjustment;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SupplierPayment;
use Brick\Math\BigDecimal;

class AccountingBackfillService
{
    public function __construct(
        private readonly AccountingProvisioner $provisioner,
        private readonly OperationalAccountingService $accounting,
    ) {}

    public function backfill(): array
    {
        $this->provisioner->ensureDefaults();
        $counts = [
            'sales' => 0,
            'returns' => 0,
            'purchase_invoices' => 0,
            'supplier_payments' => 0,
            'inventory_adjustments' => 0,
            'daily_closings' => 0,
        ];

        Sale::query()->where('status', 'completed')->orderBy('completed_at')->chunkById(100, function ($sales) use (&$counts): void {
            foreach ($sales as $sale) {
                $this->accounting->postSale($sale);
                $counts['sales']++;
            }
        });

        SaleReturn::query()->where('status', 'completed')->orderBy('completed_at')->chunkById(100, function ($returns) use (&$counts): void {
            foreach ($returns as $return) {
                $this->accounting->postSaleReturn($return);
                $counts['returns']++;
            }
        });

        PurchaseInvoice::query()->where('status', '!=', 'cancelled')->orderBy('invoice_date')->chunkById(100, function ($invoices) use (&$counts): void {
            foreach ($invoices as $invoice) {
                $this->accounting->postPurchaseInvoice($invoice);
                $counts['purchase_invoices']++;
            }
        });

        SupplierPayment::query()->orderBy('paid_at')->chunkById(100, function ($payments) use (&$counts): void {
            foreach ($payments as $payment) {
                $this->accounting->postSupplierPayment($payment);
                $counts['supplier_payments']++;
            }
        });

        InventoryAdjustment::query()->where('status', 'posted')->orderBy('posted_at')->chunkById(100, function ($adjustments) use (&$counts): void {
            foreach ($adjustments as $adjustment) {
                $this->accounting->postInventoryAdjustment($adjustment);
                $counts['inventory_adjustments']++;
            }
        });

        DailyClosing::query()
            ->whereIn('status', ['finalized', 'approved'])
            ->whereNotNull('variance')
            ->orderBy('business_date')
            ->chunkById(100, function ($closings) use (&$counts): void {
                foreach ($closings as $closing) {
                    if (BigDecimal::of($closing->variance)->isZero()) {
                        continue;
                    }

                    $event = $closing->events()->where('event_type', 'finalized')->latest('occurred_at')->first();
                    if ($event) {
                        $this->accounting->postDailyClosingVariance($closing, $event);
                        $counts['daily_closings']++;
                    }
                }
            });

        return $counts;
    }
}
