<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LocalNodeLeaseGuard;
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
        private readonly LocalNodeLeaseGuard $localLease,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ((bool) config('pharmacy.local_node.enabled')) {
            $this->localLease->assertOperational($tenant);

            return $next($request);
        }

        $state = $this->health->forTenant($tenant);

        abort_unless(
            $state->isOperational(),
            402,
            'Pharmacy access is blocked because subscription health is '.$state->value.'.',
        );

        return $next($request);
    }
}