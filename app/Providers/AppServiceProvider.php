<?php

namespace App\Providers;

use App\Contracts\Tenancy\TenantDatabaseProvisioner;
use App\Contracts\Tenancy\TenantDomainProvisioner;
use App\Services\Tenancy\CpanelTenantDatabaseProvisioner;
use App\Services\Tenancy\CpanelWildcardDomainProvisioner;
use App\Services\Tenancy\LocalTenantDatabaseProvisioner;
use App\Services\Tenancy\LocalTenantDomainProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $driver = (string) config('pharmacy.provisioning.driver', 'local');

        $this->app->bind(
            TenantDatabaseProvisioner::class,
            $driver === 'cpanel' ? CpanelTenantDatabaseProvisioner::class : LocalTenantDatabaseProvisioner::class,
        );

        $this->app->bind(
            TenantDomainProvisioner::class,
            $driver === 'cpanel' ? CpanelWildcardDomainProvisioner::class : LocalTenantDomainProvisioner::class,
        );
    }

    public function boot(): void
    {
        //
    }
}
