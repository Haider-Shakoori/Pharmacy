<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Services\Subscriptions\SubscriptionHealthService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        TenantContext $tenantContext,
        SubscriptionHealthService $health,
    ): View {
        $tenant = $tenantContext->tenant();

        return view('pharmacy.dashboard', [
            'tenant' => $tenant,
            'subscriptionHealth' => $health->forTenant($tenant),
            'stats' => [
                ['label' => __('pharmacy.stats.today_sales'), 'value' => 'AFN 0'],
                ['label' => __('pharmacy.stats.low_stock'), 'value' => '0'],
                ['label' => __('pharmacy.stats.expiring'), 'value' => '0'],
                ['label' => __('pharmacy.stats.pending_sync'), 'value' => '0'],
            ],
        ]);
    }
}
