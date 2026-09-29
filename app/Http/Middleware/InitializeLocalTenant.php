<?php

namespace App\Http\Middleware;

use App\Models\LocalNodeState;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeLocalTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            (bool) config('pharmacy.local_node.enabled'),
            404,
        );

        $state = LocalNodeState::query()->with('tenant')->first();
        $tenant = $state?->tenant;

        abort_unless(
            $tenant instanceof Tenant,
            503,
            'This BusinessOS Pharmacy local node has not been activated yet.',
        );

        tenancy()->initialize($tenant);

        try {
            return $next($request);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}