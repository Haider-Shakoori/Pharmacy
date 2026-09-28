<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreLedgerAccountRequest;
use App\Models\AccountingAdjustment;
use App\Models\Expense;
use App\Models\LedgerAccount;
use App\Models\StockLocation;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function index(
        Request $request,
        AccountingProvisioner $provisioner,
        AccountingReportService $reports,
    ): View {
        $provisioner->ensureDefaults();
        [$from, $to] = $reports->range($request->query('from'), $request->query('to'));
        $accountId = $request->query('account_id');

        $accounts = LedgerAccount::query()->where('is_active', true)->orderBy('code')->get();
        $expenseAccounts = $accounts->where('type', 'expense')->values();
        $paymentAccounts = $accounts
            ->where('type', 'asset')
            ->reject(fn (LedgerAccount $account) => in_array($account->system_key, ['accounts_receivable', 'inventory'], true))
            ->values();

        return view('pharmacy.accounting.index', [
            'from' => $from,
            'to' => $to,
            'accountId' => $accountId,
            'accounts' => $accounts,
            'expenseAccounts' => $expenseAccounts,
            'paymentAccounts' => $paymentAccounts,
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'trialBalance' => $reports->trialBalance($from, $to),
            'profitAndLoss' => $reports->profitAndLoss($from, $to),
            'keyBalances' => $reports->keyBalances(),
            'inventoryReconciliation' => $reports->inventoryReconciliation(),
            'receivableAging' => $reports->receivableAging(),
            'payableAging' => $reports->payableAging(),
            'journals' => $reports->journals($from, $to, is_string($accountId) && $accountId !== '' ? $accountId : null),
            'recentExpenses' => Expense::query()->with(['expenseAccount:id,code,name', 'paymentAccount:id,code,name'])->latest('posted_at')->limit(10)->get(),
            'recentAdjustments' => AccountingAdjustment::query()->with(['debitAccount:id,code,name', 'creditAccount:id,code,name'])->latest('posted_at')->limit(10)->get(),
        ]);
    }

    public function storeAccount(StoreLedgerAccountRequest $request): RedirectResponse
    {
        LedgerAccount::query()->create([
            ...$request->validated(),
            'currency' => strtoupper($request->validated('currency')),
            'is_system' => false,
            'is_active' => true,
        ]);

        return back()->with('success', 'Ledger account created.');
    }
}
