<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('tenant_id');

        abort_if(blank($tenantId), 403, 'A pharmacy tenant must be selected.');

        $tenant = Tenant::query()->find($tenantId);

        abort_if($tenant === null, 403, 'The selected pharmacy tenant is unavailable.');
        abort_unless($tenant->status === TenantStatus::Active, 403, 'The selected pharmacy tenant is not active.');

        try {
            $this->context->set($tenant);

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
