<?php

namespace App\Console\Commands;

use App\Contracts\Tenancy\TenantDatabaseProvisioner;
use App\Models\Business;
use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Offline\OfflineNetworkIdentity;
use App\Services\Settings\PharmacySettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class InstallOfflinePharmacy extends Command
{
    protected $signature = 'pharmacy:offline:install
        {--name=BusinessOS Offline Pharmacy : Pharmacy name}
        {--owner-name=Administrator : Initial owner name}
        {--owner-email=admin@businessos.local : Initial owner email}
        {--owner-password= : Initial owner password}
        {--locale=en : Default locale}
        {--domain=* : Additional local domain or LAN IP}';

    protected $description = 'Create or repair the single-tenant local Pharmacy installation.';

    public function handle(
        TenantDatabaseProvisioner $databases,
        PharmacySettings $settings,
        InventoryProvisioner $inventory,
        AccountingProvisioner $accounting,
        RbacProvisioner $rbac,
        OfflineNetworkIdentity $network,
    ): int {
        if (! config('offline.enabled')) {
            $this->error('APP_EDITION must be set to offline.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('owner-password') ?: $this->secret('Initial owner password'));

        if (strlen($password) < 8) {
            $this->error('The owner password must contain at least 8 characters.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->with('business')->first();

        if ($tenant === null) {
            $tenant = DB::connection('central')->transaction(function (): Tenant {
                return Tenant::query()->create([
                    'status' => 'active',
                    'provisioning_status' => 'provisioning',
                ]);
            });
        }

        $business = $tenant->business;

        if ($business === null) {
            $business = Business::query()->create([
                'tenant_id' => $tenant->id,
                'pharmacy_name' => (string) $this->option('name'),
                'slug' => 'offline-'.strtolower(substr((string) $tenant->id, -8)),
                'contact_person' => (string) $this->option('owner-name'),
                'owner_email' => Str::lower((string) $this->option('owner-email')),
                'billing_currency' => 'AFN',
                'default_timezone' => 'Asia/Kabul',
                'default_locale' => (string) $this->option('locale'),
            ]);
            $tenant->setRelation('business', $business);
        }

        $databases->ensureDatabase($tenant);
        MigrateDatabase::dispatchSync($tenant);

        $settings->record($tenant);
        $inventory->ensureDefaults($tenant);
        $accounting->ensureDefaults($tenant);
        $rbac->provisionOwner(
            $tenant,
            (string) $this->option('owner-name'),
            Str::lower((string) $this->option('owner-email')),
            $password,
        );

        $domains = array_values(array_unique([
            ...$network->domains(),
            ...array_filter(array_map('trim', (array) $this->option('domain'))),
        ]));

        foreach ($domains as $domain) {
            $tenant->domains()->firstOrCreate(['domain' => $domain]);
        }

        $tenant->forceFill([
            'status' => 'active',
            'provisioning_status' => 'application_ready',
            'provisioning_error' => null,
        ])->save();

        $this->info('BusinessOS Pharmacy Offline is ready.');
        $this->table(['Item', 'Value'], [
            ['Tenant', $tenant->id],
            ['Pharmacy', $business->pharmacy_name],
            ['Owner', $business->owner_email],
            ['Domains', implode(', ', $domains) ?: 'none detected'],
            ['URLs', implode(', ', $network->urls()) ?: 'Open the server by its LAN IP'],
        ]);

        return self::SUCCESS;
    }
}
