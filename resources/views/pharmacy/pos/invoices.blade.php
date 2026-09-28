@extends('layouts.pharmacy')

@section('title', 'POS Invoices — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Point of Sale</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">POS Invoices</h1>
            <p class="mt-1 text-sm text-slate-500">Every completed POS sale is stored here. Open any invoice to print, return items, or inspect its batch allocations.</p>
        </div>
        <a href="{{ route('pharmacy.pos.index') }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Open POS</a>
    </div>

    <section class="grid gap-3 sm:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Invoices</p><p class="mt-2 text-2xl font-black">{{ $summary['count'] }}</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sales total</p><p class="mt-2 text-2xl font-black">{{ number_format((float) $summary['total'], 2) }} AFN</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Credit due</p><p class="mt-2 text-2xl font-black text-amber-700">{{ number_format((float) $summary['due'], 2) }} AFN</p></article>
    </section>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_160px_160px_170px_auto]">
        <input name="search" value="{{ $search }}" placeholder="Invoice number, customer or phone" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <input type="date" name="from" value="{{ $from }}" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <input type="date" name="to" value="{{ $to }}" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <select name="payment_status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All payment statuses</option>
            @foreach (['paid','partial','credit','unpaid'] as $status)
                <option value="{{ $status }}" @selected($paymentStatus === $status)>{{ str($status)->headline() }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-[1000px] w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3 text-start">Invoice</th><th class="px-4 py-3 text-start">Customer</th><th class="px-4 py-3 text-start">Cashier</th><th class="px-4 py-3 text-start">Location</th><th class="px-4 py-3 text-start">Status</th><th class="px-4 py-3 text-end">Total</th><th class="px-4 py-3 text-end">Due</th><th class="px-4 py-3 text-end">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="px-4 py-3"><p class="font-mono font-semibold text-slate-900">{{ $sale->sale_number }}</p><p class="text-xs text-slate-500">{{ $sale->completed_at?->format('Y-m-d H:i') }}</p></td>
                            <td class="px-4 py-3">{{ $sale->customer?->name ?? 'Walk-in' }}@if($sale->customer?->phone)<p class="text-xs text-slate-500">{{ $sale->customer->phone }}</p>@endif</td>
                            <td class="px-4 py-3">{{ $sale->cashier?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $sale->location?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ str($sale->payment_status)->headline() }}</span></td>
                            <td class="px-4 py-3 text-end font-semibold">{{ number_format((float) $sale->grand_total, 2) }} AFN</td>
                            <td class="px-4 py-3 text-end {{ (float) $sale->due_total > 0 ? 'font-bold text-amber-700' : '' }}">{{ number_format((float) $sale->due_total, 2) }} AFN</td>
                            <td class="px-4 py-3 text-end"><a href="{{ route('pharmacy.pos.receipt', $sale) }}" class="font-bold text-teal-700">Open invoice</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">No POS invoices match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sales->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $sales->links() }}</div>@endif
    </section>
</div>
@endsection
