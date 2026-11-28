@extends('layouts.pharmacy')
@section('title', $customer->name.' — '.__('pharmacy.product'))
@section('content')
<a class="inline-block rounded-xl border px-4 py-2 font-semibold" href="{{ route('pharmacy.customers.statement', $customer) }}">{{ __('accounts.statement') }} / {{ __('accounts.print') }}</a>
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><p class="text-sm font-semibold text-teal-700">Customer account</p><h1 class="text-3xl font-bold">{{ $customer->name }}</h1><p class="mt-1 text-sm text-slate-500">{{ $customer->phone ?: 'No phone' }}{{ $customer->email ? ' · '.$customer->email : '' }}</p></div><a href="{{ route('pharmacy.customers.edit', $customer) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Edit customer</a></div>
    <section class="grid gap-4 sm:grid-cols-3"><article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Lifetime sales</p><p class="mt-2 text-2xl font-bold">{{ number_format((float) $salesTotal, 2) }} AFN</p></article><article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Outstanding credit</p><p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((float) $outstandingCredit, 2) }} AFN</p></article><article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Credit limit</p><p class="mt-2 text-2xl font-bold">{{ number_format((float) $customer->credit_limit, 2) }} AFN</p></article></section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold">Sales history</h2></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">Sale</th><th class="px-4 py-3 text-start">Date</th><th class="px-4 py-3 text-end">Total</th><th class="px-4 py-3 text-end">Due</th><th class="px-4 py-3 text-end">Receipt</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($sales as $sale)<tr><td class="px-4 py-3 font-semibold">{{ $sale->sale_number }}</td><td class="px-4 py-3">{{ $sale->business_date?->toDateString() }}</td><td class="px-4 py-3 text-end">{{ number_format((float)$sale->grand_total,2) }} AFN</td><td class="px-4 py-3 text-end">{{ number_format((float)$sale->due_total,2) }} AFN</td><td class="px-4 py-3 text-end"><a class="font-semibold text-teal-700" href="{{ route('pharmacy.pos.receipt',$sale) }}">Open</a></td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No completed sales yet.</td></tr>@endforelse</tbody></table></div>@if($sales->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $sales->links() }}</div>@endif</section>
</div>
@if(auth()->user()->hasPermission('pos.sell'))
@include('pharmacy.accounts.bulk', ['party'=>$customer, 'invoices'=>$outstandingSales, 'isCustomer'=>true])
<section class="mx-auto mt-5 max-w-7xl space-y-4 rounded-2xl border bg-white p-5">
<h2 id="account-payment" class="text-xl font-bold">{{ __('accounts.receive_payment') }}</h2>
@forelse($outstandingSales as $invoice)
<form method="POST" action="{{ route('pharmacy.customers.payments.store', $customer) }}" class="space-y-3 rounded-xl border p-4">
@csrf
<p class="font-semibold">{{ $invoice->sale_number }} — {{ __('accounts.balance') }}: {{ $invoice->due_total }} {{ $invoice->currency }}</p>
<input type="hidden" name="sale_id" value="{{ $invoice->id }}">
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<div class="grid gap-3 sm:grid-cols-3">
<label>{{ __('accounts.amount') }}<input class="block w-full rounded border p-2" type="number" name="amount" min="0.0001" step="0.0001" max="{{ $invoice->due_total }}" required></label>
<label>{{ __('accounts.method') }}<select class="block w-full rounded border p-2" name="method">@foreach(['cash','bank','mobile'] as $method)<option value="{{ $method }}">{{ __('accounts.'.$method) }}</option>@endforeach</select></label>
<label>{{ __('accounts.reference') }}<input class="block w-full rounded border p-2" name="reference" maxlength="160"></label>
</div><button class="rounded-xl bg-teal-700 px-4 py-2 font-bold text-white">{{ __('accounts.receive_payment') }}</button> <button name="print" value="1" class="rounded border px-4 py-2">{{ __('accounts.save_print') }}</button>
</form>
@empty<p>{{ __('accounts.no_due') }}</p>@endforelse
</section>
@endif
@include('pharmacy.accounts.history', ['party' => $customer, 'receiptRoute' => 'pharmacy.customers.payments.receipt'])
@endsection
