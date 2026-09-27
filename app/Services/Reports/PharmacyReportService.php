<?php

namespace App\Services\Reports;

use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnAllocation;
use App\Models\SaleReturnRefund;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PharmacyReportService
{
    public function range(?string $from, ?string $to): array
    {
        $end = $to ? CarbonImmutable::parse($to)->startOfDay() : today()->toImmutable();
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->startOfMonth();

        if ($start->isAfter($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    public function summary(string $from, string $to): array
    {
        $sales = Sale::query()
            ->where('status', 'completed')
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);

        $returns = SaleReturn::query()
            ->where('status', 'completed')
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);

        $salesTotal = BigDecimal::of((string) $sales->clone()->sum('grand_total'));
        $returnsTotal = BigDecimal::of((string) $returns->clone()->sum('refund_total'));
        $discounts = BigDecimal::of((string) $sales->clone()->sum('discount_total'));
        $cogs = BigDecimal::of((string) SaleLine::query()
            ->whereHas('sale', fn (Builder $query) => $query
                ->where('status', 'completed')
                ->whereDate('business_date', '>=', $from)
                ->whereDate('business_date', '<=', $to))
            ->sum('cost_total'));

        $returnedCost = SaleReturnAllocation::query()
            ->join('sale_return_lines', 'sale_return_lines.id', '=', 'sale_return_allocations.sale_return_line_id')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_lines.sale_return_id')
            ->join('sale_batch_allocations', 'sale_batch_allocations.id', '=', 'sale_return_allocations.sale_batch_allocation_id')
            ->where('sale_returns.status', 'completed')
            ->whereDate('sale_returns.business_date', '>=', $from)
            ->whereDate('sale_returns.business_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(sale_return_allocations.quantity * sale_batch_allocations.unit_cost), 0) as total')
            ->value('total');

        $netRevenue = $salesTotal->minus($returnsTotal);
        $netCogs = $cogs->minus(BigDecimal::of((string) $returnedCost));

        $collections = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
            ->where('sale_payments.method', '!=', 'credit')
            ->sum('sale_payments.amount');

        $refundCashLike = SaleReturnRefund::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_refunds.sale_return_id')
            ->where('sale_returns.status', 'completed')
            ->whereDate('sale_returns.business_date', '>=', $from)
            ->whereDate('sale_returns.business_date', '<=', $to)
            ->where('sale_return_refunds.method', '!=', 'credit')
            ->sum('sale_return_refunds.amount');

        return [
            'sales' => $this->decimal($salesTotal),
            'returns' => $this->decimal($returnsTotal),
            'net_sales' => $this->decimal($netRevenue),
            'discounts' => $this->decimal($discounts),
            'gross_profit' => $this->decimal($netRevenue->minus($netCogs)),
            'collections' => $this->decimal(BigDecimal::of((string) $collections)->minus(BigDecimal::of((string) $refundCashLike))),
            'purchases' => $this->decimal(BigDecimal::of((string) PurchaseInvoice::query()->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)->sum('grand_total'))),
            'receivables' => $this->decimal(BigDecimal::of((string) Sale::query()->where('status', 'completed')->where('due_total', '>', 0)->sum('due_total'))),
            'payables' => $this->decimal(BigDecimal::of((string) PurchaseInvoice::query()->where('balance_due', '>', 0)->sum('balance_due'))),
            'stock_value' => $this->decimal(BigDecimal::of((string) ProductBatch::query()->where('available_quantity', '>', 0)->selectRaw('COALESCE(SUM(available_quantity * purchase_cost), 0) as total')->value('total'))),
        ];
    }

    public function sales(string $from, string $to)
    {
        return Sale::query()->with(['customer:id,name', 'cashier:id,name', 'location:id,name'])
            ->where('status', 'completed')->whereDate('business_date', '>=', $from)->whereDate('business_date', '<=', $to)
            ->latest('completed_at')->paginate(25, ['*'], 'sales_page')->withQueryString();
    }

    public function returns(string $from, string $to)
    {
        return SaleReturn::query()->with(['sale:id,sale_number'])->where('status', 'completed')
            ->whereDate('business_date', '>=', $from)->whereDate('business_date', '<=', $to)
            ->latest('completed_at')->paginate(25, ['*'], 'returns_page')->withQueryString();
    }

    public function purchases(string $from, string $to)
    {
        return PurchaseInvoice::query()->with('supplier:id,name')
            ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
            ->latest('invoice_date')->paginate(25, ['*'], 'purchases_page')->withQueryString();
    }

    public function nearExpiry(int $days = 90): Collection
    {
        return ProductBatch::query()->with(['medicine:id,brand_name,strength', 'location:id,name'])
            ->where('available_quantity', '>', 0)->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', today())->whereDate('expires_at', '<=', today()->addDays($days))
            ->orderBy('expires_at')->limit(100)->get();
    }

    public function lowStock(): Collection
    {
        return Medicine::query()->where('is_active', true)
            ->withSum(['batches as available_stock' => fn ($query) => $query->where('available_quantity', '>', 0)], 'available_quantity')
            ->orderBy('brand_name')->get()
            ->filter(fn (Medicine $medicine) => BigDecimal::of((string) ($medicine->available_stock ?? 0))->isLessThanOrEqualTo(BigDecimal::of($medicine->reorder_level)))
            ->take(100)->values();
    }

    public function movements(string $from, string $to)
    {
        return StockMovement::query()->with(['medicine:id,brand_name', 'batch:id,batch_number', 'location:id,name', 'actor:id,name'])
            ->whereDate('occurred_at', '>=', $from)->whereDate('occurred_at', '<=', $to)
            ->latest('occurred_at')->paginate(50, ['*'], 'movements_page')->withQueryString();
    }

    public function exportRows(string $type, string $from, string $to): array
    {
        return match ($type) {
            'sales' => Sale::query()->where('status', 'completed')->whereDate('business_date', '>=', $from)->whereDate('business_date', '<=', $to)
                ->orderBy('business_date')->get()->map(fn (Sale $sale) => [$sale->sale_number, $sale->business_date->toDateString(), $sale->grand_total, $sale->discount_total, $sale->paid_total, $sale->due_total, $sale->payment_status])->all(),
            'returns' => SaleReturn::query()->where('status', 'completed')->whereDate('business_date', '>=', $from)->whereDate('business_date', '<=', $to)
                ->orderBy('business_date')->get()->map(fn (SaleReturn $return) => [$return->return_number, $return->business_date->toDateString(), $return->refund_total, $return->reason])->all(),
            'purchases' => PurchaseInvoice::query()->with('supplier:id,name')->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
                ->orderBy('invoice_date')->get()->map(fn (PurchaseInvoice $invoice) => [$invoice->invoice_number, $invoice->supplier?->name, $invoice->invoice_date->toDateString(), $invoice->grand_total, $invoice->paid_total, $invoice->balance_due])->all(),
            'movements' => StockMovement::query()->with(['medicine:id,brand_name', 'batch:id,batch_number'])->whereDate('occurred_at', '>=', $from)->whereDate('occurred_at', '<=', $to)
                ->orderBy('occurred_at')->get()->map(fn (StockMovement $movement) => [$movement->occurred_at->toDateTimeString(), $movement->medicine?->brand_name, $movement->batch?->batch_number, $movement->movement_type, $movement->quantity_delta, $movement->balance_after, $movement->source_type, $movement->source_id])->all(),
            default => [],
        };
    }

    public function exportHeaders(string $type): array
    {
        return match ($type) {
            'sales' => ['Sale Number', 'Business Date', 'Total', 'Discount', 'Paid', 'Due', 'Payment Status'],
            'returns' => ['Return Number', 'Business Date', 'Refund Total', 'Reason'],
            'purchases' => ['Invoice Number', 'Supplier', 'Invoice Date', 'Total', 'Paid', 'Balance Due'],
            'movements' => ['Occurred At', 'Medicine', 'Batch', 'Movement Type', 'Quantity', 'Balance After', 'Source Type', 'Source ID'],
            default => [],
        };
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
