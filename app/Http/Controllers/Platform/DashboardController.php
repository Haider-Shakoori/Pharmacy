<?php

namespace App\Http\Controllers\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Subscriptions\PlatformSubscriptionMonitoringService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(PlatformSubscriptionMonitoringService $monitoring): View
    {
        $statusCounts = Tenant::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $subscriptionSnapshot = $monitoring->snapshot();

        return view('platform.dashboard', [
            'metrics' => [
                'total_pharmacies' => (int) $statusCounts->sum(),
                'active_pharmacies' => (int) $statusCounts->get(TenantStatus::Active->value, 0),
                'suspended_pharmacies' => (int) $statusCounts->get(TenantStatus::Suspended->value, 0),
                'archived_pharmacies' => (int) $statusCounts->get(TenantStatus::Archived->value, 0),
                'provisioning_failures' => Tenant::query()->where('provisioning_status', 'failed')->count(),
                'subscription_attention' => $subscriptionSnapshot['metrics']['attention'],
                'stale_devices' => $subscriptionSnapshot['metrics']['stale_devices'],
            ],
            'subscriptionAttention' => $subscriptionSnapshot['rows']
                ->where('needs_attention', true)
                ->take(5)
                ->values(),
            'recentTenants' => Tenant::query()
                ->with(['business', 'domains'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
