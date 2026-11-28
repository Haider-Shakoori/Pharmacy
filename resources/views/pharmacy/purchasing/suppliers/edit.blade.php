@extends('layouts.pharmacy')
@section('title', 'Edit '.$supplier->name.' — '.__('pharmacy.product'))
@section('content')
<a class="inline-block rounded-xl border px-4 py-2 font-semibold" href="{{ route('pharmacy.suppliers.statement', $supplier) }}">{{ __('accounts.statement') }} / {{ __('accounts.print') }}</a>
<div class="mx-auto max-w-4xl"><h1 class="mb-5 text-3xl font-bold">Edit {{ $supplier->name }}</h1><div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">@include('pharmacy.purchasing.suppliers._form')</div></div>
@if(auth()->user()->hasPermission('purchases.pay'))
@include('pharmacy.accounts.bulk', ['party'=>$supplier, 'invoices'=>$outstandingInvoices, 'isCustomer'=>false])
<section class="mx-auto mt-5 max-w-4xl space-y-4 rounded-2xl border bg-white p-5">
<h2 id="account-payment" class="text-xl font-bold">{{ __('accounts.make_payment') }}</h2>
@forelse($outstandingInvoices as $invoice)
<form method="POST" action="{{ route('pharmacy.supplier-payments.store', $invoice) }}" class="space-y-3 rounded-xl border p-4">
@csrf
<p class="font-semibold">{{ $invoice->invoice_number }} — {{ __('accounts.balance') }}: {{ $invoice->balance_due }} {{ $invoice->currency }}</p>
<input type="hidden" name="currency" value="{{ $invoice->currency }}">
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<div class="grid gap-3 sm:grid-cols-2">
<label>{{ __('accounts.amount') }}<input class="block w-full rounded border p-2" type="number" name="amount" min="0.0001" step="0.0001" max="{{ $invoice->balance_due }}" required></label>
<label>{{ __('accounts.method') }}<select class="block w-full rounded border p-2" name="method">@foreach(['cash','bank','hawala','other'] as $method)<option value="{{ $method }}">{{ __('accounts.'.$method) }}</option>@endforeach</select></label>
<label>{{ __('accounts.date') }}<input class="block w-full rounded border p-2" type="date" name="paid_at" value="{{ now()->toDateString() }}" required></label>
<label>{{ __('accounts.location') }} <select name="stock_location_id" required class="rounded border p-2">@foreach(\App\Models\StockLocation::where('is_active',true)->get() as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
<label>{{ __('accounts.reference') }}<input class="block w-full rounded border p-2" name="reference" maxlength="160"></label>
<label>{{ __('accounts.notes') }}<textarea class="block w-full rounded border p-2" name="notes" maxlength="2000"></textarea></label>
</div><button class="rounded-xl bg-blue-700 px-4 py-2 font-bold text-white">{{ __('accounts.make_payment') }}</button> <button name="print" value="1" class="rounded border px-4 py-2">{{ __('accounts.save_print') }}</button>
</form>
@empty<p>{{ __('accounts.no_due') }}</p>@endforelse
</section>
@endif
@include('pharmacy.accounts.history', ['party' => $supplier, 'receiptRoute' => 'pharmacy.suppliers.payments.receipt'])
@endsection
