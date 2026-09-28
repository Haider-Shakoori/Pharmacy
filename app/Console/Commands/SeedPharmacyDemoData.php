<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Demo\PharmacyDemoDataService;
use Illuminate\Console\Command;

class SeedPharmacyDemoData extends Command
{
    protected $signature = 'pharmacy:demo:seed {tenant : Tenant ULID or pharmacy slug}';

    protected $description = 'Idempotently seed realistic, date-aware demo data into one pharmacy tenant.';

    public function handle(PharmacyDemoDataService $demo): int
    {
        $identifier = (string) $this->argument('tenant');
        $tenant = Tenant::query()->with('business')
            ->whereKey($identifier)
            ->orWhereHas('business', fn ($query) => $query->where('slug', $identifier))
            ->first();

        if (! $tenant) {
            $this->error('Tenant not found: '.$identifier);

            return self::FAILURE;
        }

        $counts = $demo->seed($tenant);
        $this->info('Demo dataset ready for '.$tenant->name.' ('.$tenant->slug.').');
        $this->table(['Dataset', 'Count'], collect($counts)->map(fn ($count, $name) => [$name, $count])->values()->all());

        return self::SUCCESS;
    }
}
