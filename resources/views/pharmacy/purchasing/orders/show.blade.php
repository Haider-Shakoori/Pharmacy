@extends('layouts.pharmacy')
@section('title', $order->number.' — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
        <div><p class="text-sm font-semibold text-teal-700">Purchase order</p><h1 class="text-3xl font-bold">{{ $order->number }}</h1><p class="mt-1 text-sm text-slate-500">{{ $order->supplier->name }} · {{ str($order->status)->headline() }}</p></div>
        <div class="flex flex-wrap gap-2">
            @if ($order->status === 'draft')<form method="POST" action="{{ route('pharmacy.purchase-orders.submit', $order) }}">@csrf<button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Submit</button></form>@endif
            @if ($order->status === 'submitted' && auth()->user()->hasPermission('purchases.approve'))<form method="POST" action="{{ route('pharmacy.purchase-orders.approve', $order) }}">@csrf<button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Approve</button></form>@endif
            @if ($order->status === 'approved' && auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-receipts.create', $order) }}" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Capture goods receipt</a>@endif
            @if (in_array($order->status, ['draft','submitted','approved'], true) && $order->receipts->isEmpty())<form method="POST" action="{{ route('pharmacy.purchase-orders.cancel', $order) }}">@csrf<button class="rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-700">Cancel</button></form>@endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Subtotal',$order->subtotal],['Discount',$order->discount_total],['Landed cost',$order->landed_cost_total],['Grand total',$order->grand_total]] as [$label,$value])
            <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-bold">{{ number_format((float) $value, 2) }} {{ $order->currency }}</p></div>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 p-4"><h2 class="font-bold">Order lines</h2></div>
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">Medicine</th><th class="px-4 py-3 text-end">Qty</th><th class="px-4 py-3 text-end">Unit cost</th><th class="px-4 py-3 text-end">Discount</th><th class="px-4 py-3 text-end">Landed</th><th class="px-4 py-3 text-end">Total</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@foreach ($order->lines as $line)<tr><td class="px-4 py-3"><p class="font-semibold">{{ $line->description }}</p><p class="text-xs text-slate-500">{{ $line->medicine->generic_name }}</p></td><td class="px-4 py-3 text-end">{{ $line->ordered_quantity }}</td><td class="px-4 py-3 text-end">{{ $line->unit_cost }}</td><td class="px-4 py-3 text-end">{{ $line->discount_amount }}</td><td class="px-4 py-3 text-end">{{ $line->landed_cost_allocated }}</td><td class="px-4 py-3 text-end font-semibold">{{ $line->line_total }}</td></tr>@endforeach</tbody>
        </table></div>
    </section>

    @if ($order->status === 'approved' && $order->invoices->isEmpty() && auth()->user()->hasPermission('purchases.manage'))
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-bold">Record supplier invoice</h2>
        <form method="POST" action="{{ route('pharmacy.purchase-invoices.store', $order) }}" class="mt-4 grid gap-3 md:grid-cols-4">
            @csrf
            <input name="supplier_invoice_number" placeholder="Supplier invoice #" class="rounded-xl border border-slate-300 px-3 py-2.5">
            <input type="date" name="invoice_date" value="{{ now()->toDateString() }}" required class="rounded-xl border border-slate-300 px-3 py-2.5">
            <input type="date" name="due_date" class="rounded-xl border border-slate-300 px-3 py-2.5">
            <select name="goods_receipt_id" class="rounded-xl border border-slate-300 px-3 py-2.5"><option value="">No linked GRN</option>@foreach ($order->receipts as $receipt)<option value="{{ $receipt->id }}">{{ $receipt->receipt_number }}</option>@endforeach</select>
            <input name="notes" placeholder="Notes" class="rounded-xl border border-slate-300 px-3 py-2.5 md:col-span-3">
            <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Record invoice</button>
        </form>
    </section>
    @endif

    @foreach ($order->invoices as $invoice)
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-col justify-between gap-2 sm:flex-row"><div><h2 class="font-bold">{{ $invoice->invoice_number }}</h2><p class="text-sm text-slate-500">Supplier ref: {{ $invoice->supplier_invoice_number ?: '—' }} · {{ str($invoice->status)->headline() }}</p></div><div class="text-end"><p class="text-sm text-slate-500">Balance</p><p class="text-xl font-bold">{{ $invoice->balance_due }} {{ $invoice->currency }}</p></div></div>
        @if (auth()->user()->hasPermission('purchases.pay') && $invoice->status !== 'paid')
        <form method="POST" action="{{ route('pharmacy.supplier-payments.store', $invoice) }}" class="mt-4 grid gap-3 md:grid-cols-6">
            @csrf
            <input type="number" step="0.0001" min="0.0001" max="{{ $invoice->balance_due }}" name="amount" placeholder="Amount" required class="rounded-xl border border-slate-300 px-3 py-2.5">
            <input name="currency" value="{{ $invoice->currency }}" readonly class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5">
            <select name="method" required class="rounded-xl border border-slate-300 px-3 py-2.5"><option value="cash">Cash</option><option value="bank">Bank</option><option value="hawala">Hawala</option><option value="other">Other</option></select>
            <input name="reference" placeholder="Reference" class="rounded-xl border border-slate-300 px-3 py-2.5">
            <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded-xl border border-slate-300 px-3 py-2.5">
            <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Record payment</button>
            <input type="hidden" name="idempotency_key" value="{{ (string) str()->ulid() }}">
        </form>
        @endif
        @if ($invoice->payments->isNotEmpty())<div class="mt-4 border-t border-slate-100 pt-3 text-sm">@foreach ($invoice->payments as $payment)<div class="flex justify-between py-1"><span>{{ $payment->payment_number }} · {{ ucfirst($payment->method) }}</span><span>{{ $payment->amount }} {{ $payment->currency }}</span></div>@endforeach</div>@endif
    </section>
    @endforeach

    @if ($order->receipts->isNotEmpty())
    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <h2 class="font-bold text-amber-950">Goods receipts awaiting inventory posting</h2>
        <p class="mt-1 text-sm text-amber-800">Batch 10 captures physical receipt facts. Batch 11 will create stock batches/movements transactionally and stamp <code>inventory_posted_at</code>.</p>
        <div class="mt-3 space-y-2">@foreach ($order->receipts as $receipt)<div class="rounded-xl bg-white p-3 text-sm"><div class="flex justify-between"><span class="font-semibold">{{ $receipt->receipt_number }}</span><span>{{ str($receipt->status)->headline() }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $receipt->lines->count() }} lines · {{ $receipt->received_at->format('Y-m-d H:i') }}</p></div>@endforeach</div>
    </section>
    @endif
</div>
@endsection
