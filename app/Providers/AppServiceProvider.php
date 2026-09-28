<?php

namespace App\Providers;

use App\Contracts\Tenancy\TenantDatabaseProvisioner;
use App\Contracts\Tenancy\TenantDomainProvisioner;
use App\Services\Tenancy\CpanelTenantDatabaseProvisioner;
use App\Services\Tenancy\CpanelWildcardDomainProvisioner;
use App\Services\Tenancy\LocalTenantDatabaseProvisioner;
use App\Services\Tenancy\LocalTenantDomainProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('platform-login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by(
                'platform-login|'.$request->ip().'|'.$email,
            );
        });

        RateLimiter::for('pharmacy-login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(8)->by(
                'pharmacy-login|'.$request->getHost().'|'.$request->ip().'|'.$email,
            );
        });

        RateLimiter::for('license-activation', function (Request $request): Limit {
            return Limit::perMinute(30)->by(
                'license-activation|'.$request->ip().'|'.(string) $request->input('device_id'),
            );
        });

        RateLimiter::for('mobile-register', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(10)->by(
                'mobile-register|'.$request->ip().'|'.(string) $request->input('device_id').'|'.$email,
            );
        });

        RateLimiter::for('mobile-api', function (Request $request): Limit {
            $identity = $request->bearerToken();

            if (! is_string($identity) || $identity === '') {
                $identity = $request->ip();
            }

            return Limit::perMinute(120)->by(
                'mobile-api|'.hash('sha256', $identity),
            );
        });
    }
}
