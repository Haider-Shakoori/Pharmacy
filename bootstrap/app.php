<?php

use App\Console\Commands\BackfillAccounting;
use App\Console\Commands\CheckProductionReadiness;
use App\Console\Commands\CreateBackup;
use App\Console\Commands\InstallOfflinePharmacy;
use App\Console\Commands\OfflineLicenseActivate;
use App\Console\Commands\OfflineLicenseStatus;
use App\Console\Commands\PruneBackups;
use App\Console\Commands\RestoreBackup;
use App\Console\Commands\RetryTenantProvisioning;
use App\Console\Commands\SyncOfflineNetworkDomains;
use App\Console\Commands\VerifyBackup;
use App\Http\Middleware\ApplyTenantPreferences;
use App\Http\Middleware\EnsureOfflineLicense;
use App\Http\Middleware\EnsureOperationalSubscription;
use App\Http\Middleware\RejectOversizedApiPayload;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        BackfillAccounting::class,
        CheckProductionReadiness::class,
        CreateBackup::class,
        PruneBackups::class,
        OfflineLicenseActivate::class,
        OfflineLicenseStatus::class,
        InstallOfflinePharmacy::class,
        SyncOfflineNetworkDomains::class,
        RestoreBackup::class,
        RetryTenantProvisioning::class,
        VerifyBackup::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(EnsureOfflineLicense::class);

        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'tenant.preferences' => ApplyTenantPreferences::class,
            'subscription.operational' => EnsureOperationalSubscription::class,
            'permission' => RequirePermission::class,
            'api.payload' => RejectOversizedApiPayload::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('platform*')
                ? route('platform.login')
                : '/login',
        );

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('platform*')
                ? route('platform.dashboard')
                : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
