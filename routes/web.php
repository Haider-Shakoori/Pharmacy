<?php

use App\Http\Controllers\Health\ReadinessController;
use App\Http\Controllers\Platform\Account\PasswordController as PlatformPasswordController;
use App\Http\Controllers\Platform\Auth\LoginController as PlatformLoginController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\LicenseController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\SubscriptionMonitoringController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\Platform\TenantProvisioningRetryController;
use App\Http\Controllers\Platform\TenantStatusController;
use App\Http\Controllers\Platform\TenantTrialController;
use App\Http\Controllers\Platform\TrialRequestController;
use App\Http\Controllers\PublicTrialRequestController;
use Illuminate\Support\Facades\Route;

Route::domain(config('pharmacy.deployment_host'))->group(function (): void {
    Route::get('/ready', ReadinessController::class)->name('health.ready');
    Route::get('/', [PublicTrialRequestController::class, 'create'])->name('trial.request');
    Route::post('/trial-request', [PublicTrialRequestController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('trial.request.store');

    Route::prefix('platform')->name('platform.')->group(function (): void {
        Route::middleware('guest:platform')->group(function (): void {
            Route::get('/login', [PlatformLoginController::class, 'create'])->name('login');
            Route::post('/login', [PlatformLoginController::class, 'store'])
                ->middleware('throttle:platform-login')
                ->name('login.store');
        });

        Route::middleware('auth:platform')->group(function (): void {
            Route::get('/', PlatformDashboardController::class)->name('dashboard');
            Route::post('/logout', [PlatformLoginController::class, 'destroy'])->name('logout');

            Route::get('/account/password', [PlatformPasswordController::class, 'edit'])
                ->name('account.password.edit');
            Route::put('/account/password', [PlatformPasswordController::class, 'update'])
                ->name('account.password.update');

            Route::resource('tenants', TenantController::class)->except(['show', 'destroy']);
            Route::put('/tenants/{tenant}/status/{status}', TenantStatusController::class)
                ->whereIn('status', ['active', 'suspended', 'archived'])
                ->name('tenants.status');

            Route::post('/tenants/{tenant}/retry-provisioning', TenantProvisioningRetryController::class)
                ->name('tenants.retry-provisioning');

            Route::post('/tenants/{tenant}/trial/start', TenantTrialController::class)
                ->name('tenants.trial.start');

            Route::get('/trial-requests', [TrialRequestController::class, 'index'])
                ->name('trial-requests.index');
            Route::post('/trial-requests/{trialRequest}/approve', [TrialRequestController::class, 'approve'])
                ->name('trial-requests.approve');
            Route::post('/trial-requests/{trialRequest}/reject', [TrialRequestController::class, 'reject'])
                ->name('trial-requests.reject');

            Route::resource('plans', PlanController::class)->except(['show', 'destroy']);

            Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('/monitoring', SubscriptionMonitoringController::class)->name('monitoring.index');
            Route::get('/subscriptions/{tenant}/edit', [SubscriptionController::class, 'edit'])->name('subscriptions.edit');
            Route::put('/subscriptions/{tenant}', [SubscriptionController::class, 'update'])->name('subscriptions.update');

            Route::get('/licenses', [LicenseController::class, 'index'])->name('licenses.index');
            Route::post('/licenses/{subscription}/regenerate', [LicenseController::class, 'regenerate'])->name('licenses.regenerate');
            Route::post('/licenses/{subscription}/revoke', [LicenseController::class, 'revoke'])->name('licenses.revoke');
        });
    });
});
