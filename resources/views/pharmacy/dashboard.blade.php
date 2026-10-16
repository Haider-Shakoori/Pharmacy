@extends('layouts.pharmacy')

@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))

@section('content')
<style>
.dt-reference-dashboard {font-family:'Segoe UI',Inter,ui-sans-serif,system-ui,sans-serif}
.dt-reference-dashboard .dt-glass-kpi {isolation:isolate; box-shadow:0 5px 14px #7892b524,inset 0 1px 0 #fff}
.dt-reference-dashboard .dt-kpi-wave {opacity:.58;background:radial-gradient(ellipse at 88% 100%,var(--wave) 0,transparent 56%),linear-gradient(160deg,transparent 42%,var(--wash) 100%)}
.dt-reference-dashboard .dt-kpi-green{--wave:#27cb7b99;--wash:#cff9e5}.dt-reference-dashboard .dt-kpi-blue{--wave:#21aeff99;--wash:#cfefff}.dt-reference-dashboard .dt-kpi-amber{--wave:#ffb625a8;--wash:#fff1cb}.dt-reference-dashboard .dt-kpi-pink{--wave:#ff458699;--wash:#ffe0eb}.dt-reference-dashboard .dt-kpi-purple{--wave:#8b65ff99;--wash:#e8e0ff}
.dt-reference-dashboard .dt-kpi-green .dt-kpi-symbol{background:#2cc36e;box-shadow:0 0 0 9px #2cc36e30}
.dt-reference-dashboard .dt-kpi-blue .dt-kpi-symbol{background:#129cef;box-shadow:0 0 0 9px #129cef30}
.dt-reference-dashboard .dt-kpi-amber .dt-kpi-symbol{background:#ffae08;box-shadow:0 0 0 9px #ffae0830}
.dt-reference-dashboard .dt-kpi-pink .dt-kpi-symbol{background:#fc376b;box-shadow:0 0 0 9px #fc376b30}
.dt-reference-dashboard .dt-kpi-purple .dt-kpi-symbol{background:#6031ed;box-shadow:0 0 0 9px #6031ed30}
.dt-reference-dashboard section {scroll-margin-top:5rem}
@media(min-width:1536px){.dt-reference-dashboard .dt-glass-kpi{min-width:0}}
</style>

<div class="dt-dashboard mx-auto max-w-[1800px] space-y-3 text-slate-800 dt-reference-dashboard">
    {{-- Desktop-aligned compact title row --}}
    <header class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/80 bg-white/85 px-5 py-4">
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

    {{-- Five illustrated desktop KPI tiles, matching the user's reference layout. --}}
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5" aria-label="Desktop overview metrics">
        @foreach ([
            ['label' => 'Total Sales Today', 'value' => $stats[0]['value'], 'note' => $stats[4]['value'].' invoices', 'tone' => 'green', 'icon' => '▥'],
            ['label' => 'Purchases Today', 'value' => 'AFN '.number_format($todayPurchaseValue, 2), 'note' => $purchaseOrdersToday.' purchase orders', 'tone' => 'blue', 'icon' => '🛒'],
            ['label' => 'Low Stock Items', 'value' => $stats[1]['value'], 'note' => 'Items below threshold', 'tone' => 'amber', 'icon' => '⬡'],
            ['label' => 'Expiring Soon', 'value' => $stats[2]['value'], 'note' => 'Within configured expiry window', 'tone' => 'pink', 'icon' => '▣'],
            ['label' => 'Cash in Drawer', 'value' => '—', 'note' => 'Drawer balance not available in web', 'tone' => 'purple', 'icon' => '▤'],
        ] as $metric)
            <article class="dt-glass-kpi dt-kpi-{{ $metric['tone'] }} relative flex min-h-[114px] items-center gap-3 overflow-hidden rounded-2xl border border-white/80 bg-white/90 px-4 py-3 shadow-sm">
                <div class="dt-kpi-symbol relative z-10 grid h-16 w-16 shrink-0 place-items-center rounded-2xl text-3xl font-bold text-white" aria-hidden="true">{{ $metric['icon'] }}</div>
                <div class="relative z-10 min-w-0">
                    <p class="text-xs font-semibold text-slate-700">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-[clamp(1.15rem,1.55vw,1.7rem)] font-extrabold tracking-tight text-slate-950 tabular-nums">{{ $metric['value'] }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">{{ $metric['note'] }}</p>
                </div>
                <span class="dt-kpi-wave pointer-events-none absolute inset-x-0 bottom-0 h-[48%]" aria-hidden="true"></span>
            </article>
        @endforeach
    </section>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_270px]">
        <div class="min-w-0 space-y-4">
            {{-- Desktop sales overview with real server-side period selection --}}
            <section class="rounded-2xl border border-white/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm" aria-label="Sales overview">
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
                <section class="overflow-hidden rounded-2xl border border-white/80 bg-white/80 shadow-sm backdrop-blur-sm">
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
