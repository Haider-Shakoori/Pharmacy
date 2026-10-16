<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Supplier;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Subscriptions\SubscriptionHealthService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $tenantContext,
        SubscriptionHealthService $health,
        OperationalAlertService $alerts,
    ): View {
        $tenant = $tenantContext->tenant();
        $snapshot = $alerts->snapshot($tenant);
        $businessDate = $snapshot['business_date'];
        $todayTransactions = Sale::query()->where('status', 'completed')->whereDate('business_date', $businessDate)->count();
        $outstandingCredit = (string) (Sale::query()->where('status', 'completed')->sum('due_total') ?? '0');
        $activeCustomers = Customer::query()->where('is_active', true)->count();
        $stockValue = (string) (ProductBatch::query()->where('status', 'active')->where('available_quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(available_quantity * purchase_cost), 0) as value')->value('value') ?? '0');

        $recentSales = Sale::query()
            ->where('status', 'completed')
            ->with('customer:id,name')
            ->orderByDesc('completed_at')
            ->limit(6)
            ->get(['id', 'sale_number', 'customer_id', 'grand_total', 'payment_status', 'completed_at']);

        $salesByDay = Sale::query()
            ->where('status', 'completed')
            ->whereDate('business_date', '>=', CarbonImmutable::parse($businessDate)->subDays(6)->toDateString())
            ->whereDate('business_date', '<=', $businessDate)
            ->selectRaw('business_date, COUNT(*) as invoices, COALESCE(SUM(grand_total), 0) as revenue')
            ->groupBy('business_date')
            ->get()->keyBy(fn ($row) => $row->business_date->toDateString());
        $salesTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($businessDate, $salesByDay): array {
            $date = CarbonImmutable::parse($businessDate)->subDays($daysAgo)->toDateString();
            $row = $salesByDay->get($date);

            return ['date' => $date, 'revenue' => (float) ($row?->revenue ?? 0), 'invoices' => (int) ($row?->invoices ?? 0)];
        })->all();

        $purchaseOrdersToday = PurchaseOrder::query()->whereDate('order_date', $businessDate)->count();

        $selectedPeriod = (string) $request->query('period', 'week');
        if (in_array($selectedPeriod, ['today', 'week', 'month', 'year'], true) === false) {
            $selectedPeriod = 'week';
        }
        $date = CarbonImmutable::parse($businessDate);
        $periodStart = match ($selectedPeriod) {
            'today' => $date,
            'week' => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            'year' => $date->startOfYear(),
        };
        $periodTotals = Sale::query()->where('status', 'completed')
            ->whereDate('business_date', '>=', $periodStart->toDateString())
            ->whereDate('business_date', '<=', $businessDate)
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(grand_total), 0) as revenue')
            ->first();
        $overviewMedicines = Medicine::query()->count();
        $overviewBatches = ProductBatch::query()->count();
        $overviewSuppliers = Supplier::query()->count();
        $overviewMonthSales = (float) (Sale::query()->where('status', 'completed')
            ->whereDate('business_date', '>=', $date->startOfMonth()->toDateString())
            ->whereDate('business_date', '<=', $businessDate)
            ->sum('grand_total') ?? 0);
        $overviewMonthPurchases = PurchaseOrder::query()
            ->whereDate('order_date', '>=', $date->startOfMonth()->toDateString())
            ->whereDate('order_date', '<=', $businessDate)->count();

        return view('pharmacy.dashboard', [
            'selectedPeriod' => $selectedPeriod,
            'periodSales' => (float) ($periodTotals?->revenue ?? 0),
            'periodInvoiceCount' => (int) ($periodTotals?->invoices ?? 0),
            'overviewMedicines' => $overviewMedicines,
            'overviewBatches' => $overviewBatches,
            'overviewSuppliers' => $overviewSuppliers,
            'overviewMonthSales' => $overviewMonthSales,
            'overviewMonthPurchases' => $overviewMonthPurchases,
            'recentSales' => $recentSales,
            'salesTrend' => $salesTrend,
            'purchaseOrdersToday' => $purchaseOrdersToday,
            'tenant' => $tenant,
            'subscriptionHealth' => $health->forTenant($tenant),
            'alerts' => $snapshot,
            'stats' => [
                ['label' => __('pharmacy.stats.today_sales'), 'value' => 'AFN '.number_format((float) $snapshot['today_sales'], 2)],
                ['label' => __('pharmacy.stats.low_stock'), 'value' => (string) $snapshot['counts']['low_stock']],
                ['label' => __('pharmacy.stats.expiring'), 'value' => (string) $snapshot['counts']['near_expiry']],
                ['label' => __('pharmacy.stats.alerts'), 'value' => (string) $snapshot['counts']['total']],
                ['label' => __('pharmacy.stats.today_transactions'), 'value' => (string) $todayTransactions],
                ['label' => __('pharmacy.stats.stock_value'), 'value' => 'AFN '.number_format((float) $stockValue, 2)],
                ['label' => __('pharmacy.stats.customers'), 'value' => (string) $activeCustomers],
                ['label' => __('pharmacy.stats.credit_due'), 'value' => 'AFN '.number_format((float) $outstandingCredit, 2)],
            ],
        ]);
    }
}
