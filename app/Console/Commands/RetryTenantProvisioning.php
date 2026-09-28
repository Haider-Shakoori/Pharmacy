<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;

class RetryTenantProvisioning extends Command
{
    protected $signature = 'pharmacy:provisioning:retry
        {tenant? : Optional tenant ULID}
        {--pending-only : Retry only tenants waiting for domain/TLS readiness}';

    protected $description = 'Safely retry incomplete pharmacy tenant provisioning.';

    public function handle(TenantProvisioningService $provisioner): int
    {
        $tenantId = $this->argument('tenant');

        $query = Tenant::query()->with(['business', 'domains']);

        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        } elseif ($this->option('pending-only')) {
            $query->where('provisioning_status', 'awaiting_domain_tls');
        } else {
            $query->whereIn('provisioning_status', ['failed', 'awaiting_domain_tls', 'provisioning']);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->info('No matching tenants require provisioning retry.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                [$updated] = $provisioner->resume($tenant);
                $this->info("{$updated->id}: {$updated->provisioning_status}");
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("{$tenant->id}: {$exception->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
