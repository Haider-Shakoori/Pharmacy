<?php

namespace App\Http\Middleware;

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
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->health->forTenant($this->tenantContext->tenant());

        abort_unless(
            $state->isOperational(),
            402,
            'Pharmacy access is blocked because subscription health is '.$state->value.'.',
        );

        return $next($request);
    }
}
