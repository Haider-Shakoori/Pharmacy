<?php

namespace App\Http\Middleware;

use App\Services\Offline\OfflineLicenseManager;
use App\Services\Subscriptions\SubscriptionHealthService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalSubscription
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionHealthService $health,
        private readonly OfflineLicenseManager $offlineLicenses,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('offline.enabled')) {
            $this->offlineLicenses->assertValid();

            return $next($request);
        }

        $state = $this->health->forTenant($this->tenantContext->tenant());

        abort_unless(
            $state->isOperational(),
            402,
            'Pharmacy access is blocked because subscription health is '.$state->value.'.',
        );

        return $next($request);
    }
}
