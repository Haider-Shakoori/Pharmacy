@extends('layouts.pharmacy')

@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-6 dt-dashboard">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div class="mb-2 inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{{ $subscriptionHealth->label() }}</div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $tenant->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ __('pharmacy.dashboard') }} · {{ auth()->user()->name }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if (auth()->user()->hasPermission('pos.sell'))
                    <a href="{{ route('pharmacy.pos.index') }}" class="rounded-xl bg-emerald-600 px-5 py-3 text-center text-sm font-black text-white shadow-sm hover:bg-emerald-700">
                        {{ __('pharmacy.actions.open_pos') }}
                    </a>
                @endif
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pharmacy.labels.pharmacy_code') }}</p>
                    <p class="mt-1 font-mono font-semibold text-slate-800">{{ $tenant->slug }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('pharmacy.dashboard') }}">
        @foreach ($stats as $stat)
            <article class="dt-kpi-card rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-2xl font-bold tracking-tight">{{ $stat['value'] }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ __('pharmacy.alerts.live_note') }}</p>
            </article>
        @endforeach
    </section>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="space-y-4 min-w-0">
            <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-label="Sales overview">
                <div class="flex flex-wrap justify-between gap-3"><h2 class="text-lg font-bold">Sales Overview</h2><span class="text-xs font-semibold text-slate-500">Last 7 business dates · AFN</span></div>
                @php $maxDailySales = max(1, ...array_column($salesTrend, 'revenue')); @endphp
                <div class="mt-5 grid h-48 grid-cols-7 items-end gap-2 border-b border-slate-200 pb-2" role="img" aria-label="Sales revenue for the last seven business dates">
                    @foreach ($salesTrend as $day)
                        <div class="flex h-full min-w-0 flex-col items-center justify-end gap-2" title="{{ $day['date'] }}: AFN {{ number_format($day['revenue'], 2) }}, {{ $day['invoices'] }} invoices">
                            <span class="text-[10px] font-semibold tabular-nums text-slate-600">{{ $day['revenue'] > 0 ? number_format($day['revenue'], 0) : '0' }}</span>
                            <div class="w-full max-w-12 rounded-t-md bg-blue-500" style="height: {{ $day['revenue'] > 0 ? max(4, $day['revenue'] / $maxDailySales * 135) : 2 }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 grid grid-cols-7 gap-2 text-center text-[10px] text-slate-500">@foreach ($salesTrend as $day)<span>{{ substr($day['date'], 5) }}</span>@endforeach</div>
            </section>
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Low Stock Items</h2><a class="text-sm font-semibold text-blue-700" href="{{ route('pharmacy.alerts.index') }}">View All</a></div>
                    @forelse (array_slice($alerts['low_stock'], 0, 5) as $item)
                        <div class="flex justify-between gap-3 border-t border-slate-100 py-2 text-sm"><span class="min-w-0 truncate">{{ $item['name'] }}</span><span class="shrink-0 font-semibold text-red-700">{{ $item['available_quantity'] }} / {{ $item['threshold'] }}</span></div>
                    @empty <p class="text-sm text-slate-500">No low stock items.</p> @endforelse
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Expiring Soon</h2><a class="text-sm font-semibold text-blue-700" href="{{ route('pharmacy.alerts.index') }}">View All</a></div>
                    @forelse (array_slice($alerts['near_expiry'], 0, 5) as $item)
                        <div class="flex justify-between gap-3 border-t border-slate-100 py-2 text-sm"><span class="min-w-0 truncate">{{ $item['name'] }}</span><span class="shrink-0 text-amber-700">{{ $item['expires_at'] }}</span></div>
                    @empty <p class="text-sm text-slate-500">No near-expiry items.</p> @endforelse
                </section>
            </div>
            @if(auth()->user()->hasPermission('pos.sell'))
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Recent Transactions</h2><a href="{{ route('pharmacy.pos.invoices') }}" class="text-sm font-semibold text-blue-700">View All</a></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[530px] text-start text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-2 text-start">Invoice</th><th class="p-2 text-start">Customer</th><th class="p-2 text-start">Date</th><th class="p-2 text-end">Total (AFN)</th></tr></thead>
                <tbody>@forelse ($recentSales as $sale)<tr class="border-t border-slate-100"><td class="p-2 font-medium">{{ $sale->sale_number }}</td><td class="p-2">{{ $sale->customer?->name ?? 'Walk-in customer' }}</td><td class="p-2">{{ $sale->completed_at?->format('Y-m-d H:i') }}</td><td class="p-2 text-end tabular-nums">{{ number_format((float) $sale->grand_total, 2) }}</td></tr>@empty<tr><td class="p-4 text-center text-slate-500" colspan="4">No recent transactions.</td></tr>@endforelse</tbody></table></div>
            </section>
            @endif
        </div>
        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">License & Subscription</h2><p class="mt-2 text-sm text-slate-600">{{ $subscriptionHealth->label() }}</p><p class="mt-2 text-xs text-slate-500">Subscription status from your tenant account.</p></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Pharmacy Overview</h2><div class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><span class="text-slate-600">Today's invoices</span><strong>{{ $stats[4]['value'] }}</strong></div><div class="flex justify-between"><span class="text-slate-600">Purchase orders today</span><strong>{{ $purchaseOrdersToday }}</strong></div><div class="flex justify-between"><span class="text-slate-600">Active customers</span><strong>{{ $stats[6]['value'] }}</strong></div><div class="flex justify-between"><span class="text-slate-600">Stock value</span><strong>{{ $stats[5]['value'] }}</strong></div></div></section>
        </aside>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"><div><p class="text-sm font-semibold text-blue-700">Quick actions</p><h2 class="text-lg font-bold">Run the pharmacy</h2></div></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @if(auth()->user()->hasPermission('pos.sell'))<a href="{{ route('pharmacy.pos.index') }}" class="rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white">{{ __('pharmacy.actions.open_pos') }}</a>@endif
            @if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">+ {{ __('pharmacy.nav.medicines') }}</a>@endif
            @if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">{{ __('pharmacy.nav.customers') }}</a>@endif
            @if(auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">+ Purchase</a>@endif
            @if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('pharmacy.inventory.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">{{ __('pharmacy.nav.inventory') }}</a>@endif
            @if(auth()->user()->hasPermission('safe.view'))<a href="{{ route('pharmacy.safe.index') }}" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm font-bold text-amber-900 hover:bg-amber-100">{{ __('safe.nav') }}</a>@endif
            @if(auth()->user()->hasPermission('reports.view'))<a href="{{ route('pharmacy.reports.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">{{ __('pharmacy.nav.reports') }}</a>@endif
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-3" aria-label="{{ __('pharmacy.alerts.title') }}">
        @foreach (['low_stock' => ['label' => __('pharmacy.alerts.low_stock'), 'tone' => 'amber'], 'near_expiry' => ['label' => __('pharmacy.alerts.near_expiry'), 'tone' => 'orange'], 'expired' => ['label' => __('pharmacy.alerts.expired'), 'tone' => 'red']] as $alertKey => $alertMeta)
            <a href="{{ route('pharmacy.alerts.index') }}" class="dt-alert-summary group rounded-2xl border border-slate-200 bg-white p-5 hover:border-blue-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $alertMeta['label'] }}</p>
                <div class="mt-3 flex items-end justify-between">
                    <span class="text-3xl font-black tabular-nums text-slate-900">{{ $alerts['counts'][$alertKey] }}</span>
                    <span class="text-sm font-semibold text-blue-700 group-hover:underline">{{ __('pharmacy.alerts.view_all') }} →</span>
                </div>
            </a>
        @endforeach
    </section>

    @if ($alerts['counts']['total'] > 0)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-sm font-bold text-amber-900">{{ __('pharmacy.alerts.attention') }}</p>
                    <p class="mt-1 text-sm text-amber-800">{{ __('pharmacy.alerts.summary', ['low' => $alerts['counts']['low_stock'], 'near' => $alerts['counts']['near_expiry'], 'expired' => $alerts['counts']['expired']]) }}</p>
                </div>
                <a href="{{ route('pharmacy.alerts.index') }}" class="rounded-xl bg-amber-900 px-4 py-2.5 text-center text-sm font-bold text-white">{{ __('pharmacy.alerts.view_all') }}</a>
            </div>
        </section>
    @endif
</div>
@endsection