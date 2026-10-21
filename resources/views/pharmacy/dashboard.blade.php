@extends('layouts.pharmacy')

@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))

@section('content')
@php
    $maxRevenue = max(1, ...array_column($salesTrend, 'revenue'));
    $alertTotal = (int) $alerts['counts']['total'];
    $netCash = $todayPaid - $todayExpenses;
@endphp

<div class="mx-auto max-w-[1680px] space-y-5 pb-8 text-slate-900">
    <section class="relative overflow-hidden rounded-[28px] border border-slate-200 bg-slate-950 px-5 py-6 text-white shadow-sm sm:px-7">
        <div class="pointer-events-none absolute -end-20 -top-24 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-28 start-1/3 h-64 w-64 rounded-full bg-emerald-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[.16em] text-blue-100">{{ $subscriptionHealth->label() }}</span>
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs text-slate-300">{{ $businessDate }}</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">{{ $tenant->name }}</h1>
                <p class="mt-1 text-sm text-slate-300">{{ __('pharmacy.dashboard') }} · {{ auth()->user()->name }}</p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex">
                @if(auth()->user()->hasPermission('pos.sell'))
                    <a href="{{ route('pharmacy.pos.index') }}" class="rounded-xl bg-emerald-500 px-5 py-3 text-center text-sm font-black text-white shadow-lg shadow-emerald-950/20 transition hover:bg-emerald-400">{{ __('pharmacy.actions.open_pos') }}</a>
                @endif
                @if(auth()->user()->hasPermission('purchases.manage'))
                    <a href="{{ route('pharmacy.purchase-orders.create') }}" class="rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-center text-sm font-bold text-white hover:bg-white/15">+ Purchase</a>
                @endif
                @if(auth()->user()->hasPermission('reports.view'))
                    <a href="{{ route('pharmacy.reports.index') }}" class="rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-center text-sm font-bold text-white hover:bg-white/15">{{ __('pharmacy.nav.reports') }}</a>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Sales today', 'AFN '.number_format($todaySales, 2), $todayTransactions.' invoices', 'emerald'],
            ['Collected today', 'AFN '.number_format($todayPaid, 2), 'Due: AFN '.number_format($todayDue, 2), 'blue'],
            ['Stock value', 'AFN '.number_format($stockValue, 2), number_format($overviewBatches).' batches', 'violet'],
            ['Needs attention', number_format($alertTotal), $alerts['counts']['low_stock'].' low · '.$alerts['counts']['near_expiry'].' expiring', 'amber'],
        ] as [$label,$value,$note,$tone])
            <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-y-0 start-0 w-1 {{ $tone === 'emerald' ? 'bg-emerald-500' : ($tone === 'blue' ? 'bg-blue-500' : ($tone === 'violet' ? 'bg-violet-500' : 'bg-amber-500')) }}"></span>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black tracking-tight tabular-nums text-slate-950">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $note }}</p>
            </article>
        @endforeach
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,.65fr)]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600">Performance</p>
                        <h2 class="mt-1 text-lg font-black">Sales overview</h2>
                    </div>
                    <div class="flex rounded-xl bg-slate-100 p-1">
                        @foreach(['today'=>'Today','week'=>'Week','month'=>'Month','year'=>'Year'] as $key=>$label)
                            <a href="{{ request()->fullUrlWithQuery(['period'=>$key]) }}" class="rounded-lg px-3 py-2 text-xs font-bold {{ $selectedPeriod === $key ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Revenue</p><p class="mt-1 text-xl font-black tabular-nums">AFN {{ number_format($periodSales,2) }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Collected</p><p class="mt-1 text-xl font-black tabular-nums">AFN {{ number_format($periodPaid,2) }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Credit</p><p class="mt-1 text-xl font-black tabular-nums">AFN {{ number_format($periodDue,2) }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Invoices</p><p class="mt-1 text-xl font-black tabular-nums">{{ number_format($periodInvoiceCount) }}</p></div>
                </div>
                <div class="mt-6 grid h-52 grid-cols-7 items-end gap-2 border-b border-slate-200 pb-2">
                    @foreach($salesTrend as $day)
                        <div class="group flex h-full min-w-0 flex-col items-center justify-end gap-2" title="{{ $day['date'] }} · AFN {{ number_format($day['revenue'],2) }} · {{ $day['invoices'] }} invoices">
                            <span class="hidden text-[10px] font-bold text-slate-500 md:block">{{ number_format($day['revenue'],0) }}</span>
                            <div class="w-full max-w-14 rounded-t-lg bg-gradient-to-t from-blue-700 to-blue-400 transition group-hover:from-blue-600" style="height:{{ $day['revenue'] > 0 ? max(5, $day['revenue'] / $maxRevenue * 135) : 3 }}px"></div>
                            <span class="text-[10px] text-slate-500">{{ substr($day['date'],5) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-5 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-amber-600">Inventory risk</p><h2 class="mt-1 font-black">Low stock</h2></div>
                        <a href="{{ route('pharmacy.alerts.index') }}" class="text-xs font-bold text-blue-700">View all →</a>
                    </div>
                    <div class="divide-y divide-slate-100 px-5">
                        @forelse(array_slice($alerts['low_stock'],0,6) as $item)
                            <div class="flex items-center justify-between gap-3 py-3 text-sm"><span class="min-w-0 truncate font-semibold">{{ $item['name'] }}</span><span class="shrink-0 rounded-lg bg-red-50 px-2 py-1 text-xs font-bold text-red-700">{{ $item['available_quantity'] }} / {{ $item['threshold'] }}</span></div>
                        @empty <p class="py-8 text-center text-sm text-slate-500">Stock levels look healthy.</p> @endforelse
                    </div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-rose-600">Expiry control</p><h2 class="mt-1 font-black">Expiring soon</h2></div>
                        <a href="{{ route('pharmacy.alerts.index') }}" class="text-xs font-bold text-blue-700">View all →</a>
                    </div>
                    <div class="divide-y divide-slate-100 px-5">
                        @forelse(array_slice($alerts['near_expiry'],0,6) as $item)
                            <div class="flex items-center justify-between gap-3 py-3 text-sm"><span class="min-w-0 truncate font-semibold">{{ $item['name'] }}</span><span class="shrink-0 rounded-lg bg-amber-50 px-2 py-1 text-xs font-bold text-amber-700">{{ $item['expires_at'] }}</span></div>
                        @empty <p class="py-8 text-center text-sm text-slate-500">No near-expiry medicines.</p> @endforelse
                    </div>
                </article>
            </section>

            @if(auth()->user()->hasPermission('pos.sell'))
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Live activity</p><h2 class="mt-1 font-black">Recent sales</h2></div><a href="{{ route('pharmacy.pos.invoices') }}" class="text-xs font-bold text-blue-700">All invoices →</a></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[650px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3 text-start">Invoice</th><th class="px-5 py-3 text-start">Customer</th><th class="px-5 py-3 text-start">Time</th><th class="px-5 py-3 text-end">Total</th><th class="px-5 py-3 text-end">Due</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">@forelse($recentSales as $sale)<tr class="hover:bg-slate-50/70"><td class="px-5 py-3 font-bold">{{ $sale->sale_number }}</td><td class="px-5 py-3">{{ $sale->customer?->name ?? 'Walk-in customer' }}</td><td class="px-5 py-3 text-slate-500">{{ $sale->completed_at?->format('H:i') }}</td><td class="px-5 py-3 text-end font-bold tabular-nums">{{ number_format((float)$sale->grand_total,2) }}</td><td class="px-5 py-3 text-end tabular-nums {{ (float)$sale->due_total > 0 ? 'font-bold text-amber-700' : 'text-slate-400' }}">{{ number_format((float)$sale->due_total,2) }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No completed sales yet.</td></tr>@endforelse</tbody>
                    </table>
                </div>
            </section>
            @endif
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Today</p><h2 class="mt-1 font-black">Cash & operations</h2></div><span class="rounded-full {{ $netCash >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }} px-3 py-1 text-xs font-bold">Net AFN {{ number_format($netCash,0) }}</span></div>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Sales collected</span><strong>AFN {{ number_format($todayPaid,2) }}</strong></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Expenses</span><strong>AFN {{ number_format($todayExpenses,2) }}</strong></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Purchases</span><strong>AFN {{ number_format($todayPurchaseValue,2) }}</strong></div>
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-3"><span class="text-slate-500">Total credit due</span><strong class="text-amber-700">AFN {{ number_format($outstandingCredit,2) }}</strong></div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-violet-600">Pharmacy snapshot</p>
                <h2 class="mt-1 font-black">Operations</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @foreach([
                        ['Medicines',$overviewMedicines],
                        ['Batches',$overviewBatches],
                        ['Customers',$activeCustomers],
                        ['Suppliers',$overviewSuppliers],
                        ['POs today',$purchaseOrdersToday],
                        ['Open POs',$pendingPurchaseOrders],
                    ] as [$label,$value])
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-xl font-black tabular-nums">{{ number_format($value) }}</p></div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-blue-600">Shortcuts</p><h2 class="mt-1 font-black">Quick actions</h2>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                    @if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold hover:border-blue-300 hover:bg-blue-50">+ Add medicine</a>@endif
                    @if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('pharmacy.inventory.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold hover:border-blue-300 hover:bg-blue-50">Inventory</a>@endif
                    @if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold hover:border-blue-300 hover:bg-blue-50">Customers</a>@endif
                    @if(auth()->user()->hasPermission('safe.view'))<a href="{{ route('pharmacy.safe.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold hover:border-blue-300 hover:bg-blue-50">{{ __('safe.nav') }}</a>@endif
                    @if(auth()->user()->hasPermission('accounting.manage'))<a href="{{ route('pharmacy.accounting.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold hover:border-blue-300 hover:bg-blue-50">Accounting & expenses</a>@endif
                    @if(auth()->user()->hasPermission('daily_closing.perform'))<a href="{{ route('pharmacy.daily-closing.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold hover:border-blue-300 hover:bg-blue-50">Daily closing</a>@endif
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
