<?php

declare(strict_types=1);

use App\Http\Controllers\Pharmacy\AccountingAdjustmentController;
use App\Http\Controllers\Pharmacy\AccountingController;
use App\Http\Controllers\Pharmacy\AlertController;
use App\Http\Controllers\Pharmacy\Auth\LoginController as PharmacyLoginController;
use App\Http\Controllers\Pharmacy\BatchStatusController;
use App\Http\Controllers\Pharmacy\CustomerController;
use App\Http\Controllers\Pharmacy\DailyClosingController;
use App\Http\Controllers\Pharmacy\DashboardController as PharmacyDashboardController;
use App\Http\Controllers\Pharmacy\ExpenseController;
use App\Http\Controllers\Pharmacy\GoodsReceiptController;
use App\Http\Controllers\Pharmacy\GoodsReceiptInventoryController;
use App\Http\Controllers\Pharmacy\InventoryAdjustmentController;
use App\Http\Controllers\Pharmacy\InventoryController;
use App\Http\Controllers\Pharmacy\InventoryLocationController;
use App\Http\Controllers\Pharmacy\MedicineController as PharmacyMedicineController;
use App\Http\Controllers\Pharmacy\MedicineReferenceController as PharmacyMedicineReferenceController;
use App\Http\Controllers\Pharmacy\PosController;
use App\Http\Controllers\Pharmacy\PurchaseInvoiceController;
use App\Http\Controllers\Pharmacy\PurchaseOrderController;
use App\Http\Controllers\Pharmacy\ReportController;
use App\Http\Controllers\Pharmacy\RoleController as PharmacyRoleController;
use App\Http\Controllers\Pharmacy\SaleReturnController;
use App\Http\Controllers\Pharmacy\SettingsController as PharmacySettingsController;
use App\Http\Controllers\Pharmacy\SupplierController;
use App\Http\Controllers\Pharmacy\SupplierPaymentController;
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
        ->middleware('throttle:pharmacy-login')
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
            Route::get('/alerts', AlertController::class)
                ->middleware('permission:dashboard.view')
                ->name('alerts.index');

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

            Route::resource('suppliers', SupplierController::class)
                ->except(['show', 'destroy'])
                ->middleware('permission:purchases.manage');

            Route::resource('customers', CustomerController::class)
                ->except(['destroy'])
                ->middleware('permission:customers.manage');

            Route::middleware('permission:purchases.manage')->group(function (): void {
                Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
                Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
                Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
                Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
                Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
                Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

                Route::get('/purchase-orders/{purchaseOrder}/receipts/create', [GoodsReceiptController::class, 'create'])->name('purchase-receipts.create');
                Route::post('/purchase-orders/{purchaseOrder}/receipts', [GoodsReceiptController::class, 'store'])->name('purchase-receipts.store');
                Route::post('/purchase-orders/{purchaseOrder}/invoice', [PurchaseInvoiceController::class, 'store'])->name('purchase-invoices.store');
            });

            Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
                ->middleware('permission:purchases.approve')
                ->name('purchase-orders.approve');

            Route::post('/purchase-invoices/{purchaseInvoice}/payments', [SupplierPaymentController::class, 'store'])
                ->middleware('permission:purchases.pay')
                ->name('supplier-payments.store');

            Route::middleware('permission:inventory.manage')->group(function (): void {
                Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
                Route::get('/inventory/locations', [InventoryLocationController::class, 'index'])->name('inventory.locations');
                Route::post('/inventory/branches', [InventoryLocationController::class, 'storeBranch'])->name('inventory.branches.store');
                Route::post('/inventory/locations', [InventoryLocationController::class, 'storeLocation'])->name('inventory.locations.store');
                Route::get('/inventory/batches/{productBatch}', [InventoryController::class, 'show'])->name('inventory.show');
                Route::post('/goods-receipts/{goodsReceipt}/post-inventory', GoodsReceiptInventoryController::class)->name('inventory.receipts.post');
            });

            Route::post('/inventory/adjustments', [InventoryAdjustmentController::class, 'store'])
                ->middleware('permission:inventory.adjust')
                ->name('inventory.adjustments.store');

            Route::post('/inventory/batches/{productBatch}/status', BatchStatusController::class)
                ->middleware('permission:inventory.status')
                ->name('inventory.status');

            Route::middleware('permission:pos.sell')->group(function (): void {
                Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
                Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
                Route::get('/pos/invoices', [PosController::class, 'invoices'])->name('pos.invoices');
                Route::post('/pos/sales', [PosController::class, 'store'])->name('pos.store');
                Route::get('/pos/sales/{sale}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
            });

            Route::middleware('permission:returns.manage')->group(function (): void {
                Route::get('/pos/sales/{sale}/returns/create', [SaleReturnController::class, 'create'])->name('returns.create');
                Route::post('/pos/sales/{sale}/returns', [SaleReturnController::class, 'store'])->name('returns.store');
            });

            Route::middleware('permission:daily_closing.perform')->group(function (): void {
                Route::get('/daily-closing', [DailyClosingController::class, 'index'])->name('daily-closing.index');
                Route::post('/daily-closing/shifts/open', [DailyClosingController::class, 'openShift'])->name('daily-closing.shifts.open');
                Route::post('/daily-closing/shifts/{cashierShift}/close', [DailyClosingController::class, 'closeShift'])->name('daily-closing.shifts.close');
                Route::post('/daily-closing/finalize', [DailyClosingController::class, 'finalize'])->name('daily-closing.finalize');
            });

            Route::post('/daily-closing/{dailyClosing}/approve', [DailyClosingController::class, 'approve'])
                ->middleware('permission:daily_closing.approve')
                ->name('daily-closing.approve');
            Route::post('/daily-closing/{dailyClosing}/reopen', [DailyClosingController::class, 'reopen'])
                ->middleware('permission:daily_closing.reopen')
                ->name('daily-closing.reopen');

            Route::middleware('permission:reports.view')->group(function (): void {
                Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
            });

            Route::middleware('permission:accounting.manage')->group(function (): void {
                Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
                Route::post('/accounting/accounts', [AccountingController::class, 'storeAccount'])->name('accounting.accounts.store');
                Route::post('/accounting/expenses', [ExpenseController::class, 'store'])->name('accounting.expenses.store');
                Route::post('/accounting/expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('accounting.expenses.reverse');
                Route::post('/accounting/adjustments', [AccountingAdjustmentController::class, 'store'])->name('accounting.adjustments.store');
                Route::post('/accounting/adjustments/{accountingAdjustment}/reverse', [AccountingAdjustmentController::class, 'reverse'])->name('accounting.adjustments.reverse');
            });

            Route::get('/settings', [PharmacySettingsController::class, 'edit'])
                ->middleware('permission:settings.manage')
                ->name('settings.edit');
            Route::put('/settings', [PharmacySettingsController::class, 'update'])
                ->middleware('permission:settings.manage')
                ->name('settings.update');
        });
    });
});
