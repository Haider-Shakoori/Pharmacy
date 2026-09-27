@extends('layouts.pharmacy')

@section('title', 'Reports — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
        <div><p class="text-sm font-semibold text-teal-700">Operational reporting</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Reports</h1><p class="mt-1 text-sm text-slate-500">Traceable totals from tenant sales, returns, purchases and stock movements.</p></div>
        <form method="GET" class="flex flex-wrap gap-2"><input type="date" name="from" value="{{ $from }}" class="rounded-xl border border-slate-300 px-3 py-2"><input type="date" name="to" value="{{ $to }}" class="rounded-xl border border-slate-300 px-3 py-2"><button class="rounded-xl bg-slate-900 px-4 py-2 font-bold text-white">Apply</button></form>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Net sales',$summary['net_sales']],['Collections',$summary['collections']],['Gross profit',$summary['gross_profit']],['Purchases',$summary['purchases']],['Returns',$summary['returns']],['Stock value',$summary['stock_value']],['Receivables',$summary['receivables']],['Payables',$summary['payables']]] as [$label,$value])
            <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-black">{{ number_format((float)$value,2) }} AFN</p></div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach (['sales'=>'Sales CSV','returns'=>'Returns CSV','purchases'=>'Purchases CSV','movements'=>'Stock Movements CSV'] as $type=>$label)
            <a href="{{ route('pharmacy.reports.export', ['type'=>$type,'from'=>$from,'to'=>$to]) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">{{ $label }}</a>
        @endforeach
        <button onclick="window.print()" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Print / Save PDF</button>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-bold">Sales</h2>
        <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">Sale</th><th class="text-start">Date</th><th class="text-start">Cashier</th><th class="text-end">Total</th><th class="text-end">Due</th></tr></thead><tbody>@foreach($sales as $sale)<tr class="border-b border-slate-100"><td class="py-2">{{ $sale->sale_number }}</td><td>{{ $sale->business_date->toDateString() }}</td><td>{{ $sale->cashier?->name }}</td><td class="text-end">{{ number_format((float)$sale->grand_total,2) }}</td><td class="text-end">{{ number_format((float)$sale->due_total,2) }}</td></tr>@endforeach</tbody></table></div>
        @if($sales->hasPages())<div class="mt-3">{{ $sales->links() }}</div>@endif
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Low stock</h2><div class="mt-3 divide-y divide-slate-100">@forelse($lowStock as $medicine)<div class="flex justify-between py-2 text-sm"><span>{{ $medicine->brand_name }} {{ $medicine->strength }}</span><span>{{ rtrim(rtrim((string)($medicine->available_stock ?? 0),'0'),'.') }} / reorder {{ rtrim(rtrim($medicine->reorder_level,'0'),'.') }}</span></div>@empty<p class="text-sm text-slate-500">No low-stock items.</p>@endforelse</div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Near expiry</h2><div class="mt-3 divide-y divide-slate-100">@forelse($nearExpiry as $batch)<div class="flex justify-between py-2 text-sm"><span>{{ $batch->medicine?->brand_name }} · {{ $batch->batch_number }}</span><span>{{ $batch->expires_at?->toDateString() }} · {{ rtrim(rtrim($batch->available_quantity,'0'),'.') }}</span></div>@empty<p class="text-sm text-slate-500">No near-expiry batches.</p>@endforelse</div></section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Purchase invoices</h2><div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">Invoice</th><th class="text-start">Supplier</th><th class="text-start">Date</th><th class="text-end">Total</th><th class="text-end">Due</th></tr></thead><tbody>@foreach($purchases as $invoice)<tr class="border-b border-slate-100"><td class="py-2">{{ $invoice->invoice_number }}</td><td>{{ $invoice->supplier?->name }}</td><td>{{ $invoice->invoice_date->toDateString() }}</td><td class="text-end">{{ number_format((float)$invoice->grand_total,2) }}</td><td class="text-end">{{ number_format((float)$invoice->balance_due,2) }}</td></tr>@endforeach</tbody></table></div></section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Returns</h2><div class="mt-3 divide-y divide-slate-100">@forelse($returns as $return)<div class="flex justify-between gap-3 py-2 text-sm"><span>{{ $return->return_number }} · {{ $return->sale?->sale_number }} · {{ $return->reason }}</span><strong>{{ number_format((float)$return->refund_total,2) }} AFN</strong></div>@empty<p class="text-sm text-slate-500">No returns in this range.</p>@endforelse</div></section>
</div>
@endsection
