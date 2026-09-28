<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Accounting\AccountingBackfillService;
use Illuminate\Console\Command;

class BackfillAccounting extends Command
{
    protected $signature = 'pharmacy:accounting:backfill {tenant? : Optional tenant ULID}';

    protected $description = 'Idempotently create missing accounting journals from existing tenant source transactions.';

    public function handle(AccountingBackfillService $backfill): int
    {
        $tenantId = $this->argument('tenant');
        $query = Tenant::query();

        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn('No matching tenants found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $counts = $tenant->run(fn (): array => $backfill->backfill());
            $this->info($tenant->id.' · '.collect($counts)->map(fn ($value, $key) => "{$key}={$value}")->implode(', '));
        }

        return self::SUCCESS;
    }
}
