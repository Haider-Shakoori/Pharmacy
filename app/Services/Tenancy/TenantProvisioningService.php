<?php

namespace App\Services\Tenancy;

use App\Contracts\Tenancy\TenantDatabaseProvisioner;
use App\Contracts\Tenancy\TenantDomainProvisioner;
use App\Models\Business;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Settings\PharmacySettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        private readonly TenantDatabaseProvisioner $databases,
        private readonly TenantDomainProvisioner $domains,
        private readonly TenantApplicationReadiness $readiness,
        private readonly ProvisioningEventRecorder $events,
        private readonly RbacProvisioner $rbac,
        private readonly PharmacySettings $settings,
        private readonly InventoryProvisioner $inventory,
        private readonly AccountingProvisioner $accounting,
    ) {}

    public function provision(array $data): array
    {
        $tenant = DB::connection('central')->transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'status' => 'active',
                'provisioning_status' => 'provisioning',
            ]);

            $tenant->setAttribute('provisioning_owner_name', $data['owner_name']);
            $tenant->setAttribute('provisioning_owner_email', $data['owner_email']);
            $tenant->setAttribute('provisioning_owner_password', Crypt::encryptString($data['owner_password']));
            $tenant->save();

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

            return $tenant->fresh(['business']);
        });

        $this->events->record($tenant, 'central_record', 'success', 'Central tenant and business records created.');

        return $this->resume($tenant);
    }

    public function resume(Tenant $tenant): array
    {
        try {
            $tenant->forceFill(['provisioning_error' => null])->save();

            if ($tenant->provisioning_status !== 'awaiting_domain_tls') {
                $this->databases->ensureDatabase($tenant);
                $tenant->forceFill(['provisioning_status' => 'database_created'])->save();
                $this->events->record($tenant, 'database', 'success', 'Tenant database is available.');

                MigrateDatabase::dispatchSync($tenant);
                $tenant->forceFill(['provisioning_status' => 'tenant_migrated'])->save();
                $this->events->record($tenant, 'migrations', 'success', 'Tenant migrations completed.');

                $this->settings->record($tenant);
                $this->inventory->ensureDefaults($tenant);
                $this->accounting->ensureDefaults($tenant);
                $this->ensureOwner($tenant);
                $this->events->record($tenant, 'baseline', 'success', 'Settings, inventory/accounting defaults, RBAC, and owner are ready.');
            }

            $domain = $tenant->business->slug.'.'.config('pharmacy.deployment_host');
            $tenant->domains()->firstOrCreate(['domain' => $domain]);
            $this->domains->ensureTlsRequested($domain);
            $this->events->record($tenant, 'domain_tls', 'requested', 'Tenant domain registered and TLS readiness requested.', [
                'domain' => $domain,
                'mode' => config('pharmacy.cpanel.tenant_domain_mode', 'wildcard'),
            ]);

            if (! $this->readiness->isReady($domain)) {
                $tenant->forceFill(['provisioning_status' => 'awaiting_domain_tls'])->save();
                $this->events->record($tenant, 'readiness', 'pending', 'HTTPS tenant login is not reachable yet.', [
                    'domain' => $domain,
                ]);

                return [$tenant->fresh(['business', 'domains']), null];
            }

            $tenant->forceFill([
                'provisioning_status' => 'application_ready',
                'provisioning_error' => null,
            ])->save();
            $this->events->record($tenant, 'readiness', 'success', 'Tenant HTTPS application is reachable; hosted trial is eligible to start from the platform.', [
                'domain' => $domain,
            ]);

            return [$tenant->fresh(['business', 'domains']), null];
        } catch (Throwable $exception) {
            $tenant->forceFill([
                'provisioning_status' => 'failed',
                'provisioning_error' => str($exception->getMessage())->limit(2000),
            ])->save();

            $this->events->record($tenant, 'provisioning', 'failed', str($exception->getMessage())->limit(2000));

            throw $exception;
        }
    }

    private function ensureOwner(Tenant $tenant): void
    {
        $ownerExists = $tenant->run(
            fn (): bool => User::query()->where('email', $tenant->business->owner_email)->exists(),
        );

        if ($ownerExists) {
            $this->clearTemporaryOwnerCredentials($tenant);

            return;
        }

        $name = $tenant->getAttribute('provisioning_owner_name');
        $email = $tenant->getAttribute('provisioning_owner_email');
        $encryptedPassword = $tenant->getAttribute('provisioning_owner_password');

        if (! is_string($name) || ! is_string($email) || ! is_string($encryptedPassword)) {
            throw new RuntimeException('Provisioning retry requires the initial owner credentials, but they are no longer available.');
        }

        $this->rbac->provisionOwner($tenant, $name, $email, Crypt::decryptString($encryptedPassword));
        $this->clearTemporaryOwnerCredentials($tenant);
    }

    private function clearTemporaryOwnerCredentials(Tenant $tenant): void
    {
        $tenant->setAttribute('provisioning_owner_name', null);
        $tenant->setAttribute('provisioning_owner_email', null);
        $tenant->setAttribute('provisioning_owner_password', null);
        $tenant->save();
    }
}
