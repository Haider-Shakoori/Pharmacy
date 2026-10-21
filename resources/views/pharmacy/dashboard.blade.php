@extends('layouts.pharmacy')
@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))
@section('content')
@php $maxRevenue=max(1,...array_column($salesTrend,'revenue'));$netCash=$todayPaid-$todayExpenses;$alertTotal=(int)$alerts['counts']['total'];$avgTicket=$todayTransactions?$todaySales/$todayTransactions:0;$collection=$todaySales>0?min(100,$todayPaid/$todaySales*100):0; @endphp
<div class="atelier mx-auto max-w-[1660px]">
<header class="at-head"><div><span>DARMALTOON / DAILY OPERATIONS</span><h1>{{ $tenant->name }}</h1></div><div class="at-date"><small>BUSINESS DATE</small><strong>{{ $businessDate }}</strong></div></header>
<section class="at-command">
  <div class="at-command-copy"><span>GOOD {{ now()->hour<12?'MORNING':(now()->hour<17?'AFTERNOON':'EVENING') }}</span><h2>Your pharmacy,<br><em>clearly understood.</em></h2><p>{{ $alertTotal ? $alertTotal.' operational items need review today.' : 'No urgent operational issues require your attention.' }}</p></div>
  <div class="at-command-actions">@if(auth()->user()->hasPermission('pos.sell'))<a class="sale" href="{{ route('pharmacy.pos.index') }}"><span>01</span><div><small>PRIMARY</small><strong>New sale</strong></div><b>↗</b></a>@endif @if(auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}"><span>02</span><div><small>SUPPLY</small><strong>New purchase</strong></div><b>↗</b></a>@endif</div>
</section>

<section class="at-ticker">
 <div><span>REVENUE TODAY</span><strong>AFN {{ number_format($todaySales,2) }}</strong><small>{{ $todayTransactions }} transactions</small></div>
 <div><span>COLLECTED</span><strong>AFN {{ number_format($todayPaid,2) }}</strong><small>{{ number_format($collection,0) }}% collection rate</small></div>
 <div><span>INVENTORY</span><strong>AFN {{ number_format($stockValue,2) }}</strong><small>{{ $overviewMedicines }} medicines</small></div>
 <div class="{{ $alertTotal?'attention':'' }}"><span>ATTENTION</span><strong>{{ $alertTotal }}</strong><small>{{ $alerts['counts']['low_stock'] }} stock · {{ $alerts['counts']['near_expiry'] }} expiry</small></div>
</section>

<section class="at-workspace">
 <main>
  <article class="at-performance">
   <header><div><span>01 / PERFORMANCE</span><h3>Sales movement</h3></div><nav>@foreach(['today'=>'D','week'=>'W','month'=>'M','year'=>'Y'] as $key=>$label)<a href="{{ request()->fullUrlWithQuery(['period'=>$key]) }}" class="{{ $selectedPeriod===$key?'active':'' }}">{{ $label }}</a>@endforeach</nav></header>
   <div class="at-performance-total"><strong>AFN {{ number_format($periodSales,2) }}</strong><span>{{ $periodInvoiceCount }} invoices in selected period</span></div>
   <div class="at-columns">@foreach($salesTrend as $day)<div><span>{{ $day['revenue']?number_format($day['revenue']/1000,1).'k':'' }}</span><section><i style="height:{{ $day['revenue']?max(3,$day['revenue']/$maxRevenue*100):1 }}%"></i></section><b>{{ \Carbon\CarbonImmutable::parse($day['date'])->format('D') }}</b></div>@endforeach</div>
   <footer><div><i class="green"></i><span>Collected</span><strong>{{ number_format($periodPaid,2) }}</strong></div><div><i class="orange"></i><span>Credit</span><strong>{{ number_format($periodDue,2) }}</strong></div><div><i></i><span>Avg. ticket today</span><strong>{{ number_format($avgTicket,2) }}</strong></div></footer>
  </article>
  @if(auth()->user()->hasPermission('pos.sell'))<article class="at-ledger"><header><div><span>02 / ACTIVITY</span><h3>Transaction ledger</h3></div><a href="{{ route('pharmacy.pos.invoices') }}">OPEN ALL ↗</a></header><div>@forelse(array_slice($recentSales->all(),0,5) as $sale)<section><span class="at-index">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div><strong>{{ $sale->sale_number }}</strong><small>{{ $sale->customer?->name??'Walk-in customer' }}</small></div><time>{{ $sale->completed_at?->format('H:i') }}</time><b>AFN {{ number_format((float)$sale->grand_total,2) }}</b><em class="{{ (float)$sale->due_total>0?'due':'paid' }}">{{ (float)$sale->due_total>0?'CREDIT':'PAID' }}</em></section>@empty<p>No completed sales yet.</p>@endforelse</div></article>@endif
 </main>
 <aside>
  <article class="at-cash"><span>03 / CASH POSITION</span><h3>Today</h3><strong>AFN {{ number_format($netCash,2) }}</strong><small>NET CASH AFTER EXPENSES</small><div><p><span>Collections</span><b>+ {{ number_format($todayPaid,0) }}</b></p><p><span>Expenses</span><b class="negative">− {{ number_format($todayExpenses,0) }}</b></p><p><span>Purchases</span><b>{{ number_format($todayPurchaseValue,0) }}</b></p></div>@if(auth()->user()->hasPermission('safe.view'))<a href="{{ route('pharmacy.safe.index') }}">CASH SAFE ↗</a>@endif</article>
  <article class="at-watch"><header><span>04 / STOCK WATCH</span><a href="{{ route('pharmacy.alerts.index') }}">ALL</a></header><h3>Running low</h3><div>@forelse(array_slice($alerts['low_stock'],0,4) as $item)<section><span>{{ mb_strtoupper(mb_substr($item['name'],0,2)) }}</span><div><strong>{{ $item['name'] }}</strong><small>Reorder at {{ $item['threshold'] }}</small></div><b>{{ $item['available_quantity'] }}</b></section>@empty<p>Stock levels are healthy.</p>@endforelse</div></article>
  <article class="at-credit"><span>05 / RECEIVABLES</span><strong>AFN {{ number_format($outstandingCredit,2) }}</strong><p>Customer credit outstanding</p>@if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}">CUSTOMER ACCOUNTS ↗</a>@endif</article>
 </aside>
</section>

<section class="at-footer-grid">
 <article><span>PHARMACY SCALE</span><div><p><strong>{{ $overviewMedicines }}</strong><small>Medicines</small></p><p><strong>{{ $overviewBatches }}</strong><small>Batches</small></p><p><strong>{{ $activeCustomers }}</strong><small>Customers</small></p><p><strong>{{ $overviewSuppliers }}</strong><small>Suppliers</small></p></div></article>
 <article class="at-shortcuts"><span>FAST ACCESS</span><div>@if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}">Add medicine <b>↗</b></a>@endif @if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('pharmacy.inventory.index') }}">Inventory <b>↗</b></a>@endif @if(auth()->user()->hasPermission('accounting.manage'))<a href="{{ route('pharmacy.accounting.index') }}">Accounting <b>↗</b></a>@endif @if(auth()->user()->hasPermission('reports.view'))<a href="{{ route('pharmacy.reports.index') }}">Reports <b>↗</b></a>@endif</div></article>
</section>
</div>
@endsection