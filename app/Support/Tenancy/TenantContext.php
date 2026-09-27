<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Closure;
use LogicException;

class TenantContext
{
    public function set(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
    }

    public function clear(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    public function has(): bool
    {
        return tenancy()->initialized && tenancy()->tenant instanceof Tenant;
    }

    public function tenant(): Tenant
    {
        $tenant = tenancy()->tenant;

        if (! $tenant instanceof Tenant) {
            throw new LogicException('No tenant context is active.');
        }

        return $tenant;
    }

    public function id(): string
    {
        return (string) $this->tenant()->getTenantKey();
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        return $tenant->run($callback);
    }
}
