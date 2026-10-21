@extends('layouts.pharmacy')
@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))
@section('content')
@php
$maxRevenue=max(1,...array_column($salesTrend,'revenue')); $netCash=$todayPaid-$todayExpenses; $alertTotal=(int)$alerts['counts']['total'];
$avgTicket=$todayTransactions>0?$todaySales/$todayTransactions:0;
@endphp
<div class="dt-dashboard mx-auto max-w-[1660px] space-y-6 pb-10">
    <section class="dt-dash-welcome">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2"><span class="dt-dash-eyebrow">Darmaltoon Intelligence</span><span class="dt-dash-date">{{ $businessDate }}</span></div>
            <h1>Good day, {{ explode(' ', trim(auth()->user()->name))[0] }}</h1>
            <p>Here’s what is happening at <strong>{{ $tenant->name }}</strong> today.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}" class="dt-dash-btn">New purchase</a>@endif
            @if(auth()->user()->hasPermission('pos.sell'))<a href="{{ route('pharmacy.pos.index') }}" class="dt-dash-btn dt-dash-btn-primary"><span>＋</span> New sale</a>@endif
        </div>
    </section>

    <section class="dt-metric-grid">
        <article class="dt-metric-card">
            <div class="dt-metric-top"><span class="dt-metric-icon is-blue">↗</span><span class="dt-metric-chip">{{ $todayTransactions }} sales</span></div>
            <p>Today's revenue</p><h2>AFN {{ number_format($todaySales,2) }}</h2><small>Avg. ticket · AFN {{ number_format($avgTicket,0) }}</small>
        </article>
        <article class="dt-metric-card">
            <div class="dt-metric-top"><span class="dt-metric-icon is-green">✓</span><span class="dt-metric-chip is-green">Collected</span></div>
            <p>Cash collected</p><h2>AFN {{ number_format($todayPaid,2) }}</h2><small>Credit today · AFN {{ number_format($todayDue,0) }}</small>
        </article>
        <article class="dt-metric-card">
            <div class="dt-metric-top"><span class="dt-metric-icon is-purple">◇</span><span class="dt-metric-chip">{{ number_format($overviewBatches) }} batches</span></div>
            <p>Inventory value</p><h2>AFN {{ number_format($stockValue,2) }}</h2><small>{{ number_format($overviewMedicines) }} medicines in catalog</small>
        </article>
        <article class="dt-metric-card {{ $alertTotal>0?'has-alert':'' }}">
            <div class="dt-metric-top"><span class="dt-metric-icon is-amber">!</span><a href="{{ route('pharmacy.alerts.index') }}" class="dt-metric-link">Review →</a></div>
            <p>Attention needed</p><h2>{{ number_format($alertTotal) }}</h2><small>{{ $alerts['counts']['low_stock'] }} low stock · {{ $alerts['counts']['near_expiry'] }} expiring</small>
        </article>
    </section>

    <div class="grid gap-6 2xl:grid-cols-[minmax(0,1.75fr)_390px]">
        <div class="space-y-6">
            <section class="dt-dash-panel dt-sales-panel">
                <div class="dt-panel-head">
                    <div><span class="dt-panel-kicker">Revenue analytics</span><h3>Sales performance</h3><p>Revenue and collection performance for your selected period.</p></div>
                    <div class="dt-period-tabs">@foreach(['today'=>'Today','week'=>'Week','month'=>'Month','year'=>'Year'] as $key=>$label)<a href="{{ request()->fullUrlWithQuery(['period'=>$key]) }}" class="{{ $selectedPeriod===$key?'active':'' }}">{{ $label }}</a>@endforeach</div>
                </div>
                <div class="dt-sales-summary">
                    <div><span>Revenue</span><strong>AFN {{ number_format($periodSales,2) }}</strong></div>
                    <div><span>Collected</span><strong>AFN {{ number_format($periodPaid,2) }}</strong></div>
                    <div><span>Credit</span><strong>AFN {{ number_format($periodDue,2) }}</strong></div>
                    <div><span>Invoices</span><strong>{{ number_format($periodInvoiceCount) }}</strong></div>
                </div>
                <div class="dt-chart">
                    <div class="dt-chart-grid"><i></i><i></i><i></i><i></i></div>
                    <div class="dt-chart-bars">
                        @foreach($salesTrend as $day)
                        <div class="dt-chart-col" title="{{ $day['date'] }} · AFN {{ number_format($day['revenue'],2) }}">
                            <span class="dt-chart-value">{{ $day['revenue']>0?number_format($day['revenue']/1000,1).'k':'—' }}</span>
                            <div class="dt-chart-bar"><i style="height:{{ $day['revenue']>0?max(6,$day['revenue']/$maxRevenue*100):2 }}%"></i></div>
                            <span class="dt-chart-label">{{ \Carbon\CarbonImmutable::parse($day['date'])->format('D') }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="dt-dash-panel">
                    <div class="dt-panel-head compact"><div><span class="dt-panel-kicker is-red">Stock watch</span><h3>Low inventory</h3></div><a href="{{ route('pharmacy.alerts.index') }}">See all</a></div>
                    <div class="dt-watch-list">@forelse(array_slice($alerts['low_stock'],0,5) as $item)<div class="dt-watch-row"><span class="dt-watch-avatar is-red">{{ mb_strtoupper(mb_substr($item['name'],0,1)) }}</span><div><strong>{{ $item['name'] }}</strong><small>Reorder threshold {{ $item['threshold'] }}</small></div><span class="dt-stock-pill is-red">{{ $item['available_quantity'] }} left</span></div>@empty<div class="dt-empty">✓ Stock levels are healthy</div>@endforelse</div>
                </article>
                <article class="dt-dash-panel">
                    <div class="dt-panel-head compact"><div><span class="dt-panel-kicker is-amber">Expiry watch</span><h3>Expiring soon</h3></div><a href="{{ route('pharmacy.alerts.index') }}">See all</a></div>
                    <div class="dt-watch-list">@forelse(array_slice($alerts['near_expiry'],0,5) as $item)<div class="dt-watch-row"><span class="dt-watch-avatar is-amber">⌁</span><div><strong>{{ $item['name'] }}</strong><small>Requires expiry review</small></div><span class="dt-stock-pill is-amber">{{ $item['expires_at'] }}</span></div>@empty<div class="dt-empty">✓ No medicines expiring soon</div>@endforelse</div>
                </article>
            </section>

            @if(auth()->user()->hasPermission('pos.sell'))
            <section class="dt-dash-panel overflow-hidden">
                <div class="dt-panel-head compact px-0 pt-0"><div><span class="dt-panel-kicker">Transactions</span><h3>Recent sales</h3></div><a href="{{ route('pharmacy.pos.invoices') }}">View invoices</a></div>
                <div class="overflow-x-auto"><table class="dt-sales-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Time</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($recentSales as $sale)<tr><td><strong>{{ $sale->sale_number }}</strong></td><td>{{ $sale->customer?->name??'Walk-in customer' }}</td><td>{{ $sale->completed_at?->format('H:i') }}</td><td><strong>AFN {{ number_format((float)$sale->grand_total,2) }}</strong></td><td><span class="dt-payment {{ (float)$sale->due_total>0?'due':'paid' }}">{{ (float)$sale->due_total>0?'Due '.number_format((float)$sale->due_total,0):'Paid' }}</span></td></tr>@empty<tr><td colspan="5"><div class="dt-empty">No completed sales yet</div></td></tr>@endforelse</tbody></table></div>
            </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="dt-cash-card">
                <div class="dt-cash-orb"></div><span class="dt-cash-label">Today's cash position</span><h3>AFN {{ number_format($netCash,2) }}</h3><p>Collected minus recorded expenses</p>
                <div class="dt-cash-lines"><div><span>Collected</span><strong>+ {{ number_format($todayPaid,2) }}</strong></div><div><span>Expenses</span><strong class="negative">− {{ number_format($todayExpenses,2) }}</strong></div><div><span>Purchases</span><strong>{{ number_format($todayPurchaseValue,2) }}</strong></div></div>
                @if(auth()->user()->hasPermission('safe.view'))<a href="{{ route('pharmacy.safe.index') }}">Open cash safe <span>→</span></a>@endif
            </section>

            <section class="dt-dash-panel">
                <div class="dt-panel-head compact"><div><span class="dt-panel-kicker">At a glance</span><h3>Pharmacy pulse</h3></div></div>
                <div class="dt-pulse-grid">@foreach([['Medicines',$overviewMedicines],['Customers',$activeCustomers],['Suppliers',$overviewSuppliers],['Open POs',$pendingPurchaseOrders]] as [$label,$value])<div><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></div>@endforeach</div>
                <div class="dt-credit-line"><div><span>Outstanding customer credit</span><strong>AFN {{ number_format($outstandingCredit,2) }}</strong></div><div class="dt-credit-track"><i style="width:{{ $periodSales>0?min(100,$outstandingCredit/$periodSales*100):0 }}%"></i></div></div>
            </section>

            <section class="dt-dash-panel">
                <div class="dt-panel-head compact"><div><span class="dt-panel-kicker">Get things done</span><h3>Quick actions</h3></div></div>
                <div class="dt-quick-grid">
                    @if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}"><span>＋</span><strong>Medicine</strong><small>Add new item</small></a>@endif
                    @if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('pharmacy.inventory.index') }}"><span>◇</span><strong>Inventory</strong><small>Check stock</small></a>@endif
                    @if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}"><span>○</span><strong>Customers</strong><small>Accounts</small></a>@endif
                    @if(auth()->user()->hasPermission('accounting.manage'))<a href="{{ route('pharmacy.accounting.index') }}"><span>≋</span><strong>Expenses</strong><small>Accounting</small></a>@endif
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection