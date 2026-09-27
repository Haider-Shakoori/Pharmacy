<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $context = app(TenantContext::class);

            if (! $context->has()) {
                throw new LogicException('Cannot create tenant-owned records without an active tenant context.');
            }

            if ($model->tenant_id !== null && (string) $model->tenant_id !== $context->id()) {
                throw new LogicException('A tenant-owned record cannot be assigned to another tenant.');
            }

            $model->tenant_id = $context->id();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
