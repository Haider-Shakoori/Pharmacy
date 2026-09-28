<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Subscriptions\SubscriptionHealthService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        TenantContext $tenantContext,
        SubscriptionHealthService $health,
        OperationalAlertService $alerts,
    ): View {
        $tenant = $tenantContext->tenant();
        $snapshot = $alerts->snapshot($tenant);

        return view('pharmacy.dashboard', [
            'tenant' => $tenant,
            'subscriptionHealth' => $health->forTenant($tenant),
            'alerts' => $snapshot,
            'stats' => [
                ['label' => __('pharmacy.stats.today_sales'), 'value' => 'AFN '.number_format((float) $snapshot['today_sales'], 2)],
                ['label' => __('pharmacy.stats.low_stock'), 'value' => (string) $snapshot['counts']['low_stock']],
                ['label' => __('pharmacy.stats.expiring'), 'value' => (string) $snapshot['counts']['near_expiry']],
                ['label' => __('pharmacy.stats.alerts'), 'value' => (string) $snapshot['counts']['total']],
            ],
        ]);
    }
}
