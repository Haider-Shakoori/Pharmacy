@extends('layouts.pharmacy')
@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))
@section('content')
@php
$maxRevenue=max(1,...array_column($salesTrend,'revenue'));$netCash=$todayPaid-$todayExpenses;$alertTotal=(int)$alerts['counts']['total'];$avgTicket=$todayTransactions?($todaySales/$todayTransactions):0;
@endphp
<div class="dc mx-auto max-w-[1680px] pb-10">
  <header class="dc-head">
    <div><span class="dc-overline">PHARMACY CONTROL CENTER</span><h1>{{ $tenant->name }}</h1><p>{{ $businessDate }} <b>•</b> {{ $subscriptionHealth->label() }} <b>•</b> {{ auth()->user()->name }}</p></div>
    <div class="dc-head-actions">
      @if(auth()->user()->hasPermission('reports.view'))<a href="{{ route('pharmacy.reports.index') }}" class="dc-btn">Reports</a>@endif
      @if(auth()->user()->hasPermission('pos.sell'))<a href="{{ route('pharmacy.pos.index') }}" class="dc-btn primary">Start a sale <span>→</span></a>@endif
    </div>
  </header>

  <section class="dc-grid">
    <aside class="dc-left">
      <article class="dc-today">
        <div class="dc-today-top"><span>Today's sales</span><span class="dc-live"><i></i> LIVE</span></div>
        <strong>AFN<br>{{ number_format($todaySales,2) }}</strong>
        <div class="dc-today-foot"><span>{{ $todayTransactions }} transactions</span><span>Avg {{ number_format($avgTicket,0) }}</span></div>
      </article>

      <article class="dc-mini">
        <div class="dc-mini-icon green">↓</div><div><span>Collected</span><strong>AFN {{ number_format($todayPaid,0) }}</strong></div><small>{{ $todaySales>0?number_format($todayPaid/$todaySales*100,0):0 }}%</small>
      </article>
      <article class="dc-mini">
        <div class="dc-mini-icon orange">↗</div><div><span>Credit issued</span><strong>AFN {{ number_format($todayDue,0) }}</strong></div><small>Today</small>
      </article>
      <article class="dc-mini">
        <div class="dc-mini-icon violet">◇</div><div><span>Stock value</span><strong>AFN {{ number_format($stockValue,0) }}</strong></div><small>{{ $overviewMedicines }} items</small>
      </article>

      <article class="dc-shortcuts">
        <span class="dc-section-label">QUICK ACCESS</span>
        <div>
          @if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}"><i>+</i><span>Add medicine</span></a>@endif
          @if(auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}"><i>↙</i><span>Purchase</span></a>@endif
          @if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}"><i>○</i><span>Customers</span></a>@endif
          @if(auth()->user()->hasPermission('accounting.manage'))<a href="{{ route('pharmacy.accounting.index') }}"><i>≡</i><span>Expenses</span></a>@endif
        </div>
      </article>
    </aside>

    <main class="dc-center">
      <article class="dc-revenue">
        <div class="dc-panel-head">
          <div><span class="dc-section-label">PERFORMANCE</span><h2>Revenue flow</h2></div>
          <div class="dc-tabs">@foreach(['today'=>'D','week'=>'W','month'=>'M','year'=>'Y'] as $key=>$label)<a href="{{ request()->fullUrlWithQuery(['period'=>$key]) }}" class="{{ $selectedPeriod===$key?'active':'' }}">{{ $label }}</a>@endforeach</div>
        </div>
        <div class="dc-revenue-number"><strong>AFN {{ number_format($periodSales,2) }}</strong><span>{{ $periodInvoiceCount }} invoices</span></div>
        <div class="dc-area-chart">
          <svg viewBox="0 0 700 220" preserveAspectRatio="none" aria-hidden="true">
            <defs><linearGradient id="dcFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3b82f6" stop-opacity=".25"/><stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/></linearGradient></defs>
            <path class="dc-area-fill" d="@php $pts=[];foreach($salesTrend as $i=>$day){$x=$i*(700/6);$y=190-($day['revenue']/$maxRevenue*155);$pts[]=[round($x,1),round($y,1)];} echo 'M '.$pts[0][0].' '.$pts[0][1].' '.implode(' ',array_map(fn($p)=>'L '.$p[0].' '.$p[1],array_slice($pts,1))).' L 700 210 L 0 210 Z'; @endphp"/>
            <polyline class="dc-area-line" points="@foreach($pts as $p){{ $p[0] }},{{ $p[1] }} @endforeach"/>
            @foreach($pts as $p)<circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="4" class="dc-area-dot"/>@endforeach
          </svg>
          <div class="dc-chart-days">@foreach($salesTrend as $day)<span>{{ \Carbon\CarbonImmutable::parse($day['date'])->format('D') }}</span>@endforeach</div>
        </div>
        <div class="dc-split">
          <div><span>Collected</span><strong>{{ number_format($periodPaid,2) }}</strong><i class="green"></i></div>
          <div><span>Credit</span><strong>{{ number_format($periodDue,2) }}</strong><i class="orange"></i></div>
          <div><span>Net cash today</span><strong>{{ number_format($netCash,2) }}</strong><i class="blue"></i></div>
        </div>
      </article>

      @if(auth()->user()->hasPermission('pos.sell'))
      <article class="dc-activity">
        <div class="dc-panel-head"><div><span class="dc-section-label">LATEST ACTIVITY</span><h2>Recent transactions</h2></div><a href="{{ route('pharmacy.pos.invoices') }}">View all →</a></div>
        <div class="dc-activity-list">
          @forelse($recentSales as $sale)
          <div class="dc-transaction"><span class="dc-receipt">⌑</span><div><strong>{{ $sale->sale_number }}</strong><small>{{ $sale->customer?->name??'Walk-in customer' }} · {{ $sale->completed_at?->format('H:i') }}</small></div><div class="dc-amount"><strong>AFN {{ number_format((float)$sale->grand_total,2) }}</strong><span class="{{ (float)$sale->due_total>0?'due':'paid' }}">{{ (float)$sale->due_total>0?'Credit':'Paid' }}</span></div></div>
          @empty<div class="dc-empty">No completed transactions yet.</div>@endforelse
        </div>
      </article>
      @endif
    </main>

    <aside class="dc-right">
      <article class="dc-status">
        <div class="dc-panel-head"><div><span class="dc-section-label">PHARMACY HEALTH</span><h2>Operations</h2></div><span class="dc-health {{ $alertTotal?'warn':'good' }}">{{ $alertTotal?'Review':'Healthy' }}</span></div>
        <div class="dc-ring-wrap">
          @php $healthScore=max(0,100-min(100,$alertTotal*8)); @endphp
          <div class="dc-ring" style="--score:{{ $healthScore }}"><div><strong>{{ $healthScore }}</strong><span>/100</span></div></div>
          <p>Operational health score</p>
        </div>
        <div class="dc-health-rows">
          <a href="{{ route('pharmacy.alerts.index') }}"><span><i class="red"></i>Low stock</span><strong>{{ $alerts['counts']['low_stock'] }}</strong></a>
          <a href="{{ route('pharmacy.alerts.index') }}"><span><i class="amber"></i>Expiring soon</span><strong>{{ $alerts['counts']['near_expiry'] }}</strong></a>
          <span><span><i class="blue"></i>Open purchases</span><strong>{{ $pendingPurchaseOrders }}</strong></span>
          <span><span><i class="green"></i>Active customers</span><strong>{{ $activeCustomers }}</strong></span>
        </div>
      </article>

      <article class="dc-attention">
        <div class="dc-panel-head"><div><span class="dc-section-label">ATTENTION</span><h2>Stock watch</h2></div></div>
        @forelse(array_slice($alerts['low_stock'],0,4) as $item)<div class="dc-attn-row"><span>{{ mb_strtoupper(mb_substr($item['name'],0,1)) }}</span><div><strong>{{ $item['name'] }}</strong><small>{{ $item['available_quantity'] }} available</small></div><b>{{ $item['threshold'] }}</b></div>@empty<div class="dc-empty">Everything looks stocked.</div>@endforelse
      </article>

      <article class="dc-credit">
        <span class="dc-section-label">CUSTOMER CREDIT</span><strong>AFN {{ number_format($outstandingCredit,2) }}</strong><p>Total outstanding balance</p>
        @if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}">Manage accounts →</a>@endif
      </article>
    </aside>
  </section>
</div>
@endsection