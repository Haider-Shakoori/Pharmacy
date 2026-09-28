<?php

use App\Http\Controllers\Platform\Auth\LoginController as PlatformLoginController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\LicenseController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\Platform\TenantProvisioningRetryController;
use App\Http\Controllers\Platform\TenantStatusController;
use Illuminate\Support\Facades\Route;

Route::domain(config('pharmacy.deployment_host'))->group(function (): void {
    Route::redirect('/', '/platform');

    Route::prefix('platform')->name('platform.')->group(function (): void {
        Route::middleware('guest:platform')->group(function (): void {
            Route::get('/login', [PlatformLoginController::class, 'create'])->name('login');
            Route::post('/login', [PlatformLoginController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('login.store');
        });

        Route::middleware('auth:platform')->group(function (): void {
            Route::get('/', PlatformDashboardController::class)->name('dashboard');
            Route::post('/logout', [PlatformLoginController::class, 'destroy'])->name('logout');

            Route::resource('tenants', TenantController::class)->except(['show', 'destroy']);
            Route::put('/tenants/{tenant}/status/{status}', TenantStatusController::class)
                ->whereIn('status', ['active', 'suspended', 'archived'])
                ->name('tenants.status');

            Route::post('/tenants/{tenant}/retry-provisioning', TenantProvisioningRetryController::class)
                ->name('tenants.retry-provisioning');

            Route::resource('plans', PlanController::class)->except(['show', 'destroy']);

            Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('/subscriptions/{tenant}/edit', [SubscriptionController::class, 'edit'])->name('subscriptions.edit');
            Route::put('/subscriptions/{tenant}', [SubscriptionController::class, 'update'])->name('subscriptions.update');

            Route::get('/licenses', [LicenseController::class, 'index'])->name('licenses.index');
            Route::post('/licenses/{subscription}/regenerate', [LicenseController::class, 'regenerate'])->name('licenses.regenerate');
            Route::post('/licenses/{subscription}/revoke', [LicenseController::class, 'revoke'])->name('licenses.revoke');
        });
    });
});