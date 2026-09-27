<?php

namespace App\Services\Tenancy;

use App\Models\Business;
use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Settings\PharmacySettings;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        private readonly RbacProvisioner $rbac,
        private readonly PharmacySettings $settings,
        private readonly TrialProvisioner $trials,
        private readonly InventoryProvisioner $inventory,
    ) {}

    public function provision(array $data): array
    {
        [$tenant] = DB::connection('central')->transaction(function () use ($data): array {
            $tenant = Tenant::query()->create([
                'status' => 'active',
                'provisioning_status' => 'provisioning',
            ]);

            Business::query()->create([
                'tenant_id' => $tenant->id,
                'pharmacy_name' => $data['name'],
                'slug' => $data['slug'],
                'contact_person' => $data['contact_person'],
                'phone_whatsapp' => $data['phone_whatsapp'],
                'location' => $data['location'],
                'owner_email' => $data['owner_email'],
                'billing_currency' => strtoupper($data['currency']),
                'default_timezone' => $data['timezone'],
                'default_locale' => $data['locale'],
            ]);

            return [$tenant];
        });

        try {
            CreateDatabase::dispatchSync($tenant);
            $tenant->forceFill(['provisioning_status' => 'database_created'])->save();

            MigrateDatabase::dispatchSync($tenant);
            $tenant->forceFill(['provisioning_status' => 'tenant_migrated'])->save();

            $this->settings->record($tenant);
            $this->inventory->ensureDefaults($tenant);
            $this->rbac->provisionOwner(
                $tenant,
                $data['owner_name'],
                $data['owner_email'],
                $data['owner_password'],
            );

            $tenant->domains()->create([
                'domain' => $data['slug'].'.'.config('pharmacy.deployment_host'),
            ]);

            $licenseKey = $this->trials->provision($tenant);

            $tenant->forceFill([
                'provisioning_status' => 'application_ready',
                'provisioning_error' => null,
            ])->save();

            return [$tenant->fresh(['business', 'domains']), $licenseKey];
        } catch (Throwable $exception) {
            $tenant->forceFill([
                'provisioning_status' => 'failed',
                'provisioning_error' => str($exception->getMessage())->limit(2000),
            ])->save();

            throw $exception;
        }
    }
}
