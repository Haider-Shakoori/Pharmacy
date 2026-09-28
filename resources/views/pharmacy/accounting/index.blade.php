@extends('layouts.pharmacy')

@section('title', 'Accounting — '.__('pharmacy.product'))

@section('content')
@php($fmt = fn ($value) => number_format((float) $value, 2))
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Finance control</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Accounting</h1>
            <p class="mt-1 text-sm text-slate-500">Double-entry tenant ledger with source-document links, reversals and fixed-precision AFN values.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3">
            <label class="text-xs font-semibold text-slate-600">From<input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded-lg border border-slate-300 px-2.5 py-2 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">To<input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded-lg border border-slate-300 px-2.5 py-2 text-sm"></label>
            <label class="min-w-48 text-xs font-semibold text-slate-600">Account
                <select name="account_id" class="mt-1 block w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                    <option value="">All accounts</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected($accountId === $account->id)>{{ $account->code }} · {{ $account->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Apply</button>
        </form>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Cash', $keyBalances['cash_on_hand']],
            ['Bank', $keyBalances['bank']],
            ['Receivables', $keyBalances['accounts_receivable']],
            ['Payables', $keyBalances['accounts_payable']],
            ['Inventory ledger', $keyBalances['inventory']],
            ['Net revenue', $profitAndLoss['net_revenue']],
            ['Expenses', $profitAndLoss['expenses']],
            ['Net income', $profitAndLoss['net_income']],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black tracking-tight">{{ $fmt($value) }} <span class="text-xs font-semibold text-slate-400">AFN</span></p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-semibold text-teal-700">Inventory reconciliation</p>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Ledger inventory</dt><dd class="font-bold">{{ $fmt($inventoryReconciliation['ledger_inventory']) }} AFN</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Batch stock value</dt><dd class="font-bold">{{ $fmt($inventoryReconciliation['batch_stock_value']) }} AFN</dd></div>
                <div class="flex justify-between gap-4 border-t border-slate-100 pt-3"><dt class="font-semibold">Difference</dt><dd class="font-black">{{ $fmt($inventoryReconciliation['difference']) }} AFN</dd></div>
            </dl>
            <p class="mt-3 text-xs leading-5 text-slate-500">Timing differences can exist when supplier invoices and physical receipts occur on different dates. Batch stock is valued using the recorded weighted acquisition cost.</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-semibold text-teal-700">Receivable aging</p>
            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                @foreach (['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => '90+ days'] as $key => $label)
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 font-bold">{{ $fmt($receivableAging[$key]) }} AFN</p></div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-semibold text-teal-700">Payable aging</p>
            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                @foreach (['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => '90+ days'] as $key => $label)
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 font-bold">{{ $fmt($payableAging[$key]) }} AFN</p></div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold">Post expense</h2>
            <form method="POST" action="{{ route('pharmacy.accounting.expenses.store') }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="currency" value="AFN">
                <input type="hidden" name="idempotency_key" value="EXP-{{ \Illuminate\Support\Str::ulid() }}">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <label class="text-xs font-semibold">Business date<input type="date" name="business_date" value="{{ old('business_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <label class="text-xs font-semibold">Amount<input type="number" step="0.0001" min="0.0001" name="amount" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <label class="text-xs font-semibold">Expense account<select name="expense_account_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach($expenseAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                    <label class="text-xs font-semibold">Paid from<select name="payment_account_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach($paymentAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                    <label class="text-xs font-semibold">Location<select name="stock_location_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">General</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <label class="text-xs font-semibold">Payee<input name="payee" value="{{ old('payee') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <label class="text-xs font-semibold">Reference<input name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <label class="text-xs font-semibold">Notes<textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></label>
                </div>
                <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white">Post expense</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold">Audited adjustment</h2>
            <p class="mt-1 text-xs text-slate-500">Use for authorized corrections and owner drawings/equity movements. Original journals are never edited.</p>
            <form method="POST" action="{{ route('pharmacy.accounting.adjustments.store') }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="currency" value="AFN">
                <input type="hidden" name="idempotency_key" value="AJE-{{ \Illuminate\Support\Str::ulid() }}">
                <label class="block text-xs font-semibold">Business date<input type="date" name="business_date" value="{{ old('business_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-xs font-semibold">Amount<input type="number" step="0.0001" min="0.0001" name="amount" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-xs font-semibold">Debit account<select name="debit_account_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                <label class="block text-xs font-semibold">Credit account<select name="credit_account_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                <label class="block text-xs font-semibold">Location<select name="stock_location_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">General</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                <label class="block text-xs font-semibold">Reference<input name="reference" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-xs font-semibold">Reason<textarea name="reason" rows="2" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea></label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Post adjustment</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold">Add ledger account</h2>
            <form method="POST" action="{{ route('pharmacy.accounting.accounts.store') }}" class="mt-4 space-y-3">
                @csrf
                <label class="block text-xs font-semibold">Code<input name="code" required maxlength="32" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-xs font-semibold">Name<input name="name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-xs font-semibold">Type<select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach(['asset','liability','equity','revenue','expense'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></label>
                    <label class="block text-xs font-semibold">Normal balance<select name="normal_balance" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="debit">Debit</option><option value="credit">Credit</option></select></label>
                </div>
                <input type="hidden" name="currency" value="AFN">
                <label class="block text-xs font-semibold">Parent<select name="parent_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">No parent</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                <label class="block text-xs font-semibold">Description<textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea></label>
                <button class="rounded-lg border border-teal-700 px-4 py-2 text-sm font-bold text-teal-700">Create account</button>
            </form>
        </section>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-lg font-bold">Trial balance</h2><p class="text-xs text-slate-500">{{ $from }} through {{ $to }}</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-start">Account</th><th class="px-4 py-3 text-end">Debit</th><th class="px-4 py-3 text-end">Credit</th><th class="px-4 py-3 text-end">Balance</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($trialBalance as $row)
                        <tr><td class="px-4 py-3"><span class="font-mono text-xs text-slate-500">{{ $row['account']->code }}</span> <span class="font-semibold">{{ $row['account']->name }}</span></td><td class="px-4 py-3 text-end tabular-nums">{{ $fmt($row['debit']) }}</td><td class="px-4 py-3 text-end tabular-nums">{{ $fmt($row['credit']) }}</td><td class="px-4 py-3 text-end font-semibold tabular-nums">{{ $fmt($row['balance']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold">Recent expenses</h2>
            <div class="mt-3 divide-y divide-slate-100">
                @forelse($recentExpenses as $expense)
                    <div class="py-3">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $expense->expense_number }} · {{ $expense->expenseAccount?->name }}</p><p class="text-xs text-slate-500">{{ $expense->business_date->toDateString() }} · {{ $expense->payee ?: 'No payee' }} · {{ $expense->status }}</p></div><p class="font-black">{{ $fmt($expense->amount) }} AFN</p></div>
                        @if($expense->status === 'posted')<form method="POST" action="{{ route('pharmacy.accounting.expenses.reverse', $expense) }}" class="mt-2 flex gap-2">@csrf<input name="reason" required placeholder="Reversal reason" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs"><button class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-bold text-red-700">Reverse</button></form>@endif
                    </div>
                @empty<p class="py-4 text-sm text-slate-500">No expenses posted yet.</p>@endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold">Recent adjustments</h2>
            <div class="mt-3 divide-y divide-slate-100">
                @forelse($recentAdjustments as $adjustment)
                    <div class="py-3">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $adjustment->adjustment_number }}</p><p class="text-xs text-slate-500">Dr {{ $adjustment->debitAccount?->code }} / Cr {{ $adjustment->creditAccount?->code }} · {{ $adjustment->status }}</p></div><p class="font-black">{{ $fmt($adjustment->amount) }} AFN</p></div>
                        <p class="mt-1 text-xs text-slate-500">{{ $adjustment->reason }}</p>
                        @if($adjustment->status === 'posted')<form method="POST" action="{{ route('pharmacy.accounting.adjustments.reverse', $adjustment) }}" class="mt-2 flex gap-2">@csrf<input name="reason" required placeholder="Reversal reason" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs"><button class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-bold text-red-700">Reverse</button></form>@endif
                    </div>
                @empty<p class="py-4 text-sm text-slate-500">No manual adjustments posted yet.</p>@endforelse
            </div>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold">Journal history</h2>
        <p class="mt-1 text-xs text-slate-500">Source-linked entries are immutable. A reversal appears as a new journal.</p>
        <div class="mt-4 space-y-2">
            @forelse($journals as $entry)
                <details class="rounded-xl border border-slate-200 p-3">
                    <summary class="cursor-pointer list-none">
                        <div class="grid gap-2 text-sm sm:grid-cols-[150px_110px_1fr_120px_120px] sm:items-center">
                            <span class="font-mono text-xs font-semibold">{{ $entry->journal_number }}</span>
                            <span>{{ $entry->business_date->toDateString() }}</span>
                            <span><strong>{{ class_basename($entry->source_type) }}</strong> · {{ $entry->source_number ?: $entry->source_id }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-center text-xs font-semibold">{{ $entry->status }}</span>
                            <span class="text-end font-bold">{{ $fmt($entry->total_debit) }} AFN</span>
                        </div>
                    </summary>
                    <div class="mt-3 overflow-x-auto border-t border-slate-100 pt-3">
                        <table class="min-w-full text-xs"><thead><tr class="text-slate-500"><th class="py-2 text-start">Account</th><th class="py-2 text-start">Memo</th><th class="py-2 text-end">Debit</th><th class="py-2 text-end">Credit</th></tr></thead><tbody>@foreach($entry->lines as $line)<tr class="border-t border-slate-50"><td class="py-2 pe-3">{{ $line->account?->code }} · {{ $line->account?->name }}</td><td class="py-2 pe-3 text-slate-500">{{ $line->memo }}</td><td class="py-2 text-end">{{ $fmt($line->debit) }}</td><td class="py-2 text-end">{{ $fmt($line->credit) }}</td></tr>@endforeach</tbody></table>
                    </div>
                </details>
            @empty
                <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No journals in this period.</p>
            @endforelse
        </div>
        @if($journals->hasPages())<div class="mt-4">{{ $journals->links() }}</div>@endif
    </section>
</div>
@endsection
