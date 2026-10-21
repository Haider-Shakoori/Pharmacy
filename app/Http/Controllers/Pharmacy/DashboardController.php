<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
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
        $date = CarbonImmutable::parse($businessDate);

        $todayTransactions = Sale::query()->where('status', 'completed')->whereDate('business_date', $businessDate)->count();
        $todaySales = (float) $snapshot['today_sales'];
        $todayPaid = (float) (Sale::query()->where('status', 'completed')->whereDate('business_date', $businessDate)->sum('paid_total') ?? 0);
        $todayDue = (float) (Sale::query()->where('status', 'completed')->whereDate('business_date', $businessDate)->sum('due_total') ?? 0);
        $todayExpenses = (float) (Expense::query()->where('status', 'posted')->whereDate('business_date', $businessDate)->sum('amount') ?? 0);
        $outstandingCredit = (float) (Sale::query()->where('status', 'completed')->sum('due_total') ?? 0);
        $activeCustomers = Customer::query()->where('is_active', true)->count();
        $stockValue = (float) (ProductBatch::query()->where('status', 'active')->where('available_quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(available_quantity * purchase_cost), 0) as value')->value('value') ?? 0);

        $recentSales = Sale::query()
            ->where('status', 'completed')
            ->with('customer:id,name')
            ->orderByDesc('completed_at')
            ->limit(7)
            ->get(['id', 'sale_number', 'customer_id', 'grand_total', 'paid_total', 'due_total', 'payment_status', 'completed_at']);

        $salesByDay = Sale::query()
            ->where('status', 'completed')
            ->whereDate('business_date', '>=', $date->subDays(6)->toDateString())
            ->whereDate('business_date', '<=', $businessDate)
            ->selectRaw('business_date, COUNT(*) as invoices, COALESCE(SUM(grand_total), 0) as revenue')
            ->groupBy('business_date')
            ->get()->keyBy(fn ($row) => $row->business_date->toDateString());
        $salesTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($date, $salesByDay): array {
            $day = $date->subDays($daysAgo)->toDateString();
            $row = $salesByDay->get($day);

            return ['date' => $day, 'revenue' => (float) ($row?->revenue ?? 0), 'invoices' => (int) ($row?->invoices ?? 0)];
        })->all();

        $purchaseOrdersToday = PurchaseOrder::query()->whereDate('order_date', $businessDate)->count();
        $todayPurchaseValue = (float) PurchaseOrder::query()->whereDate('order_date', $businessDate)->sum('grand_total');
        $pendingPurchaseOrders = PurchaseOrder::query()->whereIn('status', ['draft', 'submitted', 'approved'])->count();

        $selectedPeriod = (string) $request->query('period', 'week');
        if (! in_array($selectedPeriod, ['today', 'week', 'month', 'year'], true)) {
            $selectedPeriod = 'week';
        }
        $periodStart = match ($selectedPeriod) {
            'today' => $date,
            'week' => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            'year' => $date->startOfYear(),
        };
        $periodTotals = Sale::query()->where('status', 'completed')
            ->whereDate('business_date', '>=', $periodStart->toDateString())
            ->whereDate('business_date', '<=', $businessDate)
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(grand_total), 0) as revenue, COALESCE(SUM(paid_total), 0) as paid, COALESCE(SUM(due_total), 0) as due')
            ->first();

        return view('pharmacy.dashboard', [
            'tenant' => $tenant,
            'subscriptionHealth' => $health->forTenant($tenant),
            'alerts' => $snapshot,
            'businessDate' => $businessDate,
            'selectedPeriod' => $selectedPeriod,
            'periodSales' => (float) ($periodTotals?->revenue ?? 0),
            'periodPaid' => (float) ($periodTotals?->paid ?? 0),
            'periodDue' => (float) ($periodTotals?->due ?? 0),
            'periodInvoiceCount' => (int) ($periodTotals?->invoices ?? 0),
            'todaySales' => $todaySales,
            'todayPaid' => $todayPaid,
            'todayDue' => $todayDue,
            'todayExpenses' => $todayExpenses,
            'todayTransactions' => $todayTransactions,
            'stockValue' => $stockValue,
            'outstandingCredit' => $outstandingCredit,
            'activeCustomers' => $activeCustomers,
            'overviewMedicines' => Medicine::query()->count(),
            'overviewBatches' => ProductBatch::query()->count(),
            'overviewSuppliers' => Supplier::query()->count(),
            'purchaseOrdersToday' => $purchaseOrdersToday,
            'todayPurchaseValue' => $todayPurchaseValue,
            'pendingPurchaseOrders' => $pendingPurchaseOrders,
            'recentSales' => $recentSales,
            'salesTrend' => $salesTrend,
        ]);
    }
}
