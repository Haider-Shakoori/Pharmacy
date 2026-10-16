@extends('layouts.pharmacy')

@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))

@section('content')
<div class="dt-dashboard mx-auto max-w-[1600px] space-y-4 text-slate-800">
    {{-- Desktop-aligned compact title row --}}
    <header class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-blue-600">{{ $tenant->name }}</p>
            <h1 class="text-2xl font-bold tracking-tight">{{ __('pharmacy.dashboard') }}</h1>
            <p class="text-xs text-slate-500">{{ auth()->user()->name }} · {{ now()->format('d M Y') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">{{ $subscriptionHealth->label() }}</span>
            @if (auth()->user()->hasPermission('pos.sell'))
                <a href="{{ route('pharmacy.pos.index') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-xs font-bold text-white hover:bg-blue-800">{{ __('pharmacy.actions.open_pos') }} →</a>
            @endif
        </div>
    </header>

    {{-- Desktop KPI strip: keep server values and mobile stacking --}}
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4" aria-label="Key performance indicators">
        @foreach ($stats as $index => $stat)
            <article class="relative overflow-hidden rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="absolute inset-y-0 start-0 w-1 {{ ['bg-blue-600','bg-amber-500','bg-rose-500','bg-sky-600','bg-emerald-600','bg-indigo-600','bg-cyan-600','bg-violet-600'][$index % 8] }}"></div>
                <p class="text-xs font-semibold text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-2 text-xl font-extrabold tracking-tight tabular-nums sm:text-2xl">{{ $stat['value'] }}</p>
            </article>
        @endforeach
    </section>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-4">
            {{-- Desktop sales overview with real server-side period selection --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Sales overview">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold">Sales Overview</h2>
                        <p class="text-xs text-slate-500">Sales (AFN) · completed invoices</p>
                    </div>
                    <div class="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1" aria-label="Sales period">
                        @foreach (['today' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $periodKey => $periodLabel)
                            <a href="{{ request()->fullUrlWithQuery(['period' => $periodKey]) }}" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $selectedPeriod === $periodKey ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:bg-white/70' }}" @if($selectedPeriod === $periodKey) aria-current="true" @endif>{{ $periodLabel }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-5 border-b border-slate-100 pb-4">
                    <div><p class="text-xs text-slate-500">Total Sales</p><p class="text-xl font-bold tabular-nums">AFN {{ number_format($periodSales, 2) }}</p></div>
                    <div><p class="text-xs text-slate-500">Invoices</p><p class="text-xl font-bold tabular-nums">{{ $periodInvoiceCount }}</p></div>
                </div>
                @php($maxRevenue = max(1, ...array_column($salesTrend, 'revenue')))
                <div class="mt-4 grid h-44 grid-cols-7 items-end gap-2 border-b border-slate-200 pb-2" role="img" aria-label="Sales revenue for the last seven business dates">
                    @foreach ($salesTrend as $day)
                        <div class="flex h-full min-w-0 flex-col items-center justify-end gap-2" title="{{ $day['date'] }} — AFN {{ number_format($day['revenue'], 2) }}">
                            <span class="text-[10px] font-semibold tabular-nums text-slate-600">{{ number_format($day['revenue'], 0) }}</span>
                            <div class="w-full max-w-12 rounded-t-md bg-blue-600" style="height: {{ $day['revenue'] > 0 ? max(4, $day['revenue'] / $maxRevenue * 115) : 2 }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 grid grid-cols-7 gap-1 text-center text-[10px] text-slate-500">
                    @foreach ($salesTrend as $day)<span>{{ substr($day['date'], 5) }}</span>@endforeach
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3"><h2 class="text-sm font-bold">Low Stock Items</h2><a href="{{ route('pharmacy.alerts.index') }}" class="text-xs font-semibold text-blue-700">View all →</a></div>
                    <div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-start">Medicine</th><th class="p-3 text-end">Current</th><th class="p-3 text-end">Minimum</th></tr></thead><tbody>
                    @forelse (array_slice($alerts['low_stock'], 0, 6) as $item)
                        <tr class="border-t"><td class="p-3 font-medium">{{ $item['name'] }}</td><td class="p-3 text-end font-bold text-rose-700">{{ $item['available_quantity'] }}</td><td class="p-3 text-end">{{ $item['threshold'] }}</td></tr>
                    @empty <tr><td colspan="3" class="p-5 text-center text-slate-500">No low-stock medicines.</td></tr> @endforelse
                    </tbody></table></div>
                </section>
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3"><h2 class="text-sm font-bold">Expiring Soon</h2><a href="{{ route('pharmacy.alerts.index') }}" class="text-xs font-semibold text-blue-700">View all →</a></div>
                    <div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-start">Medicine</th><th class="p-3 text-end">Expiry date</th></tr></thead><tbody>
                    @forelse (array_slice($alerts['near_expiry'], 0, 6) as $item)
                        <tr class="border-t"><td class="p-3 font-medium">{{ $item['name'] }}</td><td class="p-3 text-end font-semibold text-amber-700">{{ $item['expires_at'] }}</td></tr>
                    @empty <tr><td colspan="2" class="p-5 text-center text-slate-500">No approaching expiry dates.</td></tr> @endforelse
                    </tbody></table></div>
                </section>
            </div>

            @if (auth()->user()->hasPermission('pos.sell'))
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3"><h2 class="text-sm font-bold">Recent Transactions</h2><a href="{{ route('pharmacy.pos.invoices') }}" class="text-xs font-semibold text-blue-700">View all →</a></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[530px] text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-start">Invoice</th><th class="p-3 text-start">Customer</th><th class="p-3 text-start">Date</th><th class="p-3 text-end">Amount (AFN)</th></tr></thead><tbody>
                    @forelse ($recentSales as $sale)
                        <tr class="border-t"><td class="p-3 font-semibold">{{ $sale->sale_number }}</td><td class="p-3">{{ $sale->customer?->name ?? 'Walk-in customer' }}</td><td class="p-3">{{ $sale->completed_at?->format('Y-m-d H:i') }}</td><td class="p-3 text-end font-semibold tabular-nums">{{ number_format((float) $sale->grand_total, 2) }}</td></tr>
                    @empty <tr><td colspan="4" class="p-5 text-center text-slate-500">No recent transactions.</td></tr> @endforelse
                    </tbody></table></div>
                </section>
            @endif
        </div>

        <aside class="min-w-0 space-y-4">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="border-b pb-3 text-sm font-bold">Quick Actions</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @if(auth()->user()->hasPermission('pos.sell'))<a href="{{ route('pharmacy.pos.index') }}" class="rounded-lg bg-blue-700 px-3 py-3 text-center text-xs font-bold text-white">Open POS</a>@endif
                    @if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}" class="rounded-lg border bg-slate-50 px-3 py-3 text-center text-xs font-semibold">Add Medicine</a>@endif
                    @if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('pharmacy.inventory.index') }}" class="rounded-lg border bg-slate-50 px-3 py-3 text-center text-xs font-semibold">Inventory</a>@endif
                    @if(auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}" class="rounded-lg border bg-slate-50 px-3 py-3 text-center text-xs font-semibold">Purchase</a>@endif
                    @if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}" class="rounded-lg border bg-slate-50 px-3 py-3 text-center text-xs font-semibold">Customers</a>@endif
                    @if(auth()->user()->hasPermission('reports.view'))<a href="{{ route('pharmacy.reports.index') }}" class="rounded-lg border bg-slate-50 px-3 py-3 text-center text-xs font-semibold">Reports</a>@endif
                    @if(auth()->user()->hasPermission('safe.view'))<a href="{{ route('pharmacy.safe.index') }}" class="rounded-lg border bg-amber-50 px-3 py-3 text-center text-xs font-semibold">Cash Safe</a>@endif
                </div>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="border-b pb-3 text-sm font-bold">Pharmacy Overview</h2>
                <dl class="mt-3 space-y-3 text-xs">
                    @foreach ([
                        'Medicines' => $overviewMedicines,
                        'Stock batches' => $overviewBatches,
                        'Suppliers' => $overviewSuppliers,
                        'Customers' => $stats[6]['value'],
                        'Sales this month (AFN)' => number_format($overviewMonthSales, 2),
                        'Purchase orders this month' => $overviewMonthPurchases,
                    ] as $label => $value)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-bold tabular-nums">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <a href="{{ route('pharmacy.alerts.index') }}" class="block rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold text-amber-800">Alerts requiring attention</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-amber-900">{{ $alerts['counts']['total'] }}</p>
                <span class="text-xs font-semibold text-blue-700">View alerts →</span>
            </a>
        </aside>
    </div>
</div>
@endsection
