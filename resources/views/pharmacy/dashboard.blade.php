@extends('layouts.pharmacy')
@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))
@section('content')
@php $maxRevenue=max(1,...array_column($salesTrend,'revenue'));$netCash=$todayPaid-$todayExpenses;$alertTotal=(int)$alerts['counts']['total'];$avgTicket=$todayTransactions?$todaySales/$todayTransactions:0;$collection=$todaySales>0?min(100,$todayPaid/$todaySales*100):0; @endphp
<div class="prism mx-auto max-w-[1660px]">
  <header class="prism-intro"><div><span>YOUR PHARMACY · {{ strtoupper($businessDate) }}</span><h1>Everything important,<br><em>in one glance.</em></h1><p>{{ $tenant->name }} is {{ $alertTotal?'asking for your attention in '.$alertTotal.' areas.':'running smoothly today.' }}</p></div><div class="prism-intro-actions">@if(auth()->user()->hasPermission('pos.sell'))<a href="{{ route('pharmacy.pos.index') }}"><i>＋</i><span><small>QUICK ACTION</small><strong>Start new sale</strong></span><b>→</b></a>@endif</div></header>

  <section class="prism-bento">
    <article class="prism-hero">
      <div class="prism-hero-head"><span>Today’s revenue</span><b><i></i> LIVE</b></div><strong>AFN {{ number_format($todaySales,2) }}</strong><p>{{ $todayTransactions }} completed sales · average ticket AFN {{ number_format($avgTicket,0) }}</p>
      <div class="prism-wave"><svg viewBox="0 0 600 100" preserveAspectRatio="none"><defs><linearGradient id="pwave" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#e6b6ff"/><stop offset=".55" stop-color="#9e8cff"/><stop offset="1" stop-color="#78d6ff"/></linearGradient></defs><path d="M0 74 C65 80,90 20,155 42 S250 91,305 49 S405 8,460 43 S535 80,600 25" fill="none" stroke="url(#pwave)" stroke-width="4" stroke-linecap="round"/></svg></div>
      <div class="prism-hero-foot"><span><small>Collected</small><b>AFN {{ number_format($todayPaid,0) }}</b></span><span><small>Credit</small><b>AFN {{ number_format($todayDue,0) }}</b></span><span><small>Net cash</small><b>AFN {{ number_format($netCash,0) }}</b></span></div>
    </article>

    <article class="prism-orbit"><div class="prism-orbit-title"><span>Collection rate</span><b>{{ number_format($collection,0) }}%</b></div><div class="prism-donut" style="--value:{{ $collection }}"><div><strong>{{ number_format($collection,0) }}</strong><small>%</small></div></div><p>AFN {{ number_format($todayPaid,0) }} of {{ number_format($todaySales,0) }}</p></article>

    <article class="prism-alert {{ $alertTotal?'hot':'' }}"><div><span>Pharmacy health</span><b>{{ $alertTotal?'ACTION':'GOOD' }}</b></div><strong>{{ $alertTotal }}</strong><p>items need attention</p><footer><a href="{{ route('pharmacy.alerts.index') }}">{{ $alerts['counts']['low_stock'] }} low stock</a><a href="{{ route('pharmacy.alerts.index') }}">{{ $alerts['counts']['near_expiry'] }} expiry</a></footer></article>

    <article class="prism-inventory"><span>Inventory universe</span><strong>AFN {{ number_format($stockValue,0) }}</strong><p>Total stock value</p><div><span><b>{{ $overviewMedicines }}</b><small>Medicines</small></span><span><b>{{ $overviewBatches }}</b><small>Batches</small></span><span><b>{{ $overviewSuppliers }}</b><small>Suppliers</small></span></div></article>

    <article class="prism-trend">
      <header><div><span>SALES RHYTHM</span><h2>Last 7 days</h2></div><nav>@foreach(['today'=>'D','week'=>'W','month'=>'M','year'=>'Y'] as $key=>$label)<a href="{{ request()->fullUrlWithQuery(['period'=>$key]) }}" class="{{ $selectedPeriod===$key?'active':'' }}">{{ $label }}</a>@endforeach</nav></header>
      <div class="prism-trend-total"><strong>AFN {{ number_format($periodSales,2) }}</strong><span>{{ $periodInvoiceCount }} invoices</span></div>
      <div class="prism-linechart"><svg viewBox="0 0 700 180" preserveAspectRatio="none">@php $pts=[];foreach($salesTrend as $i=>$day){$x=$i*(700/6);$y=155-($day['revenue']/$maxRevenue*125);$pts[]=[round($x,1),round($y,1)];} @endphp<defs><linearGradient id="pline" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#a45ff5"/><stop offset="1" stop-color="#4ca9ff"/></linearGradient><linearGradient id="pfill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9b72f6" stop-opacity=".2"/><stop offset="1" stop-color="#9b72f6" stop-opacity="0"/></linearGradient></defs><path d="M {{ $pts[0][0] }} {{ $pts[0][1] }} @foreach(array_slice($pts,1) as $p)L {{ $p[0] }} {{ $p[1] }} @endforeach L700 175 L0 175 Z" fill="url(#pfill)"/><polyline points="@foreach($pts as $p){{ $p[0] }},{{ $p[1] }} @endforeach" fill="none" stroke="url(#pline)" stroke-width="3" vector-effect="non-scaling-stroke"/>@foreach($pts as $p)<circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="4" fill="#fff" stroke="#8c68ed" stroke-width="2" vector-effect="non-scaling-stroke"/>@endforeach</svg><div>@foreach($salesTrend as $day)<span>{{ \Carbon\CarbonImmutable::parse($day['date'])->format('D') }}</span>@endforeach</div></div>
    </article>

    <article class="prism-credit"><span>Customer credit</span><strong>AFN {{ number_format($outstandingCredit,2) }}</strong><p>Outstanding across active accounts</p>@if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}">Open accounts <b>↗</b></a>@endif</article>

    <article class="prism-actions"><span>DO SOMETHING</span><div>@if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}"><i>＋</i><b>Add medicine</b></a>@endif @if(auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}"><i>↙</i><b>Purchase</b></a>@endif @if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('pharmacy.inventory.index') }}"><i>◇</i><b>Inventory</b></a>@endif @if(auth()->user()->hasPermission('accounting.manage'))<a href="{{ route('pharmacy.accounting.index') }}"><i>≋</i><b>Accounting</b></a>@endif</div></article>
  </section>

  <section class="prism-bottom">
    <article><header><div><span>ATTENTION QUEUE</span><h2>What needs you next</h2></div><a href="{{ route('pharmacy.alerts.index') }}">See all →</a></header><div class="prism-queue">@forelse(array_slice($alerts['low_stock'],0,4) as $item)<div><span class="danger">{{ mb_strtoupper(mb_substr($item['name'],0,1)) }}</span><section><strong>{{ $item['name'] }}</strong><small>Only {{ $item['available_quantity'] }} left · reorder at {{ $item['threshold'] }}</small></section><b>LOW STOCK</b></div>@empty<p>Nothing urgent in stock right now ✓</p>@endforelse</div></article>
    @if(auth()->user()->hasPermission('pos.sell'))<article><header><div><span>RECENT SALES</span><h2>Latest transactions</h2></div><a href="{{ route('pharmacy.pos.invoices') }}">All invoices →</a></header><div class="prism-sales">@forelse(array_slice($recentSales->all(),0,4) as $sale)<div><span>⌑</span><section><strong>{{ $sale->sale_number }}</strong><small>{{ $sale->customer?->name??'Walk-in' }} · {{ $sale->completed_at?->format('H:i') }}</small></section><b>AFN {{ number_format((float)$sale->grand_total,0) }}</b></div>@empty<p>No completed sales yet.</p>@endforelse</div></article>@endif
  </section>
</div>
@endsection