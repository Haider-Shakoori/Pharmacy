<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Closure;
use LogicException;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): Tenant
    {
        return $this->tenant
            ?? throw new LogicException('No tenant context is active.');
    }

    public function id(): string
    {
        return (string) $this->tenant()->getKey();
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;

        try {
            $this->set($tenant);

            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
