<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Offline\OfflineLicenseManager;
use App\Services\Subscriptions\SubscriptionHealthService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        TenantContext $tenantContext,
        SubscriptionHealthService $health,
        OfflineLicenseManager $offlineLicenses,
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

        return view('pharmacy.dashboard', [
            'tenant' => $tenant,
            'subscriptionHealth' => config('offline.enabled')
                ? $offlineLicenses->subscriptionHealth()
                : $health->forTenant($tenant),
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
