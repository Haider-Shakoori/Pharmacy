@extends('layouts.pharmacy')
@section('title', 'Purchase Orders — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-blue-700">Purchasing</p><h1 class="text-3xl font-bold">Purchase orders</h1></div>
        @if (auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}" class="rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white">New purchase order</a>@endif
    </div>
    <form method="GET" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_180px_auto]">
        <input name="search" value="{{ $search }}" aria-label="Search purchase orders" placeholder="PO number or supplier" class="rounded-xl border border-slate-300 px-3 py-2.5">
        <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5">
            <option value="">All statuses</option>
            @foreach (['draft','submitted','approved','partially_received','received','closed','cancelled'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ str($option)->headline() }}</option>@endforeach
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button>
        <a href="{{ route('pharmacy.purchase-orders.index') }}" class="self-center text-center text-sm font-semibold text-blue-700 sm:col-span-full sm:justify-self-end">Clear filters</a>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">PO</th><th class="px-4 py-3 text-start">Supplier</th><th class="px-4 py-3 text-start">Date</th><th class="px-4 py-3 text-start">Status</th><th class="px-4 py-3 text-end">Total</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3"><a class="font-semibold text-blue-700" href="{{ route('pharmacy.purchase-orders.show', $order) }}">{{ $order->number }}</a></td>
                        <td class="px-4 py-3">{{ $order->supplier->name }}</td>
                        <td class="px-4 py-3">{{ $order->order_date->toDateString() }}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ in_array($order->status, ['approved', 'received', 'closed'], true) ? 'bg-emerald-50 text-emerald-800' : (in_array($order->status, ['cancelled'], true) ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-900') }}">{{ str($order->status)->headline() }}</span></td>
                        <td class="px-4 py-3 text-end font-semibold">{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No purchase orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $orders->links() }}</div>@endif
    </div>
</div>
@endsection
