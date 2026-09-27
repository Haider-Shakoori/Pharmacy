<?php

declare(strict_types=1);

use App\Http\Controllers\Pharmacy\Auth\LoginController as PharmacyLoginController;
use App\Http\Controllers\Pharmacy\DashboardController as PharmacyDashboardController;
use App\Http\Controllers\Pharmacy\MedicineController as PharmacyMedicineController;
use App\Http\Controllers\Pharmacy\MedicineReferenceController as PharmacyMedicineReferenceController;
use App\Http\Controllers\Pharmacy\RoleController as PharmacyRoleController;
use App\Http\Controllers\Pharmacy\SettingsController as PharmacySettingsController;
use App\Http\Controllers\Pharmacy\UserController as PharmacyUserController;
use App\Http\Middleware\SetTenantRouteDefaults;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::domain('{pharmacy}.'.config('pharmacy.deployment_host'))->middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    SetTenantRouteDefaults::class,
])->name('pharmacy.')->group(function (): void {
    Route::get('/login', [PharmacyLoginController::class, 'create'])->name('login');
    Route::post('/login', [PharmacyLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('/locale/{locale}', function (string $locale) {
        abort_unless(in_array($locale, config('pharmacy.locales'), true), 404);
        session(['locale' => $locale]);

        return back();
    })->name('locale.switch');

    Route::middleware(['tenant.preferences', 'auth:web'])->group(function (): void {
        Route::post('/logout', [PharmacyLoginController::class, 'destroy'])->name('logout');

        Route::middleware('subscription.operational')->group(function (): void {
            Route::get('/', PharmacyDashboardController::class)
                ->middleware('permission:dashboard.view')
                ->name('dashboard');

            Route::resource('users', PharmacyUserController::class)
                ->except(['show', 'destroy'])
                ->middleware('permission:users.manage');

            Route::resource('roles', PharmacyRoleController::class)
                ->except(['show', 'destroy'])
                ->middleware('permission:roles.manage');

            Route::resource('medicines', PharmacyMedicineController::class)
                ->except(['show', 'destroy'])
                ->middleware('permission:medicines.manage');

            Route::get('/medicine-setup', [PharmacyMedicineReferenceController::class, 'index'])
                ->middleware('permission:medicines.manage')
                ->name('medicine-references.index');
            Route::post('/medicine-setup/{type}', [PharmacyMedicineReferenceController::class, 'store'])
                ->middleware('permission:medicines.manage')
                ->name('medicine-references.store');

            Route::get('/settings', [PharmacySettingsController::class, 'edit'])
                ->middleware('permission:settings.manage')
                ->name('settings.edit');
            Route::put('/settings', [PharmacySettingsController::class, 'update'])
                ->middleware('permission:settings.manage')
                ->name('settings.update');
        });
    });
});
