@extends('layouts.pharmacy')
@section('title', 'Suppliers — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-teal-700">Purchasing</p><h1 class="text-3xl font-bold">Suppliers</h1></div>
        <a href="{{ route('pharmacy.suppliers.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add supplier</a>
    </div>
    <form method="GET" class="flex gap-2 rounded-2xl border border-slate-200 bg-white p-4">
        <input name="search" value="{{ $search }}" placeholder="Search supplier, code, phone..." class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5">
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Search</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">Supplier</th><th class="px-4 py-3 text-start">Contact</th><th class="px-4 py-3 text-start">Orders</th><th class="px-4 py-3 text-start">Invoices</th><th class="px-4 py-3 text-end">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td class="px-4 py-3"><p class="font-semibold">{{ $supplier->name }}</p><p class="text-xs text-slate-500">{{ $supplier->code }} · {{ $supplier->is_active ? 'Active' : 'Inactive' }}</p></td>
                        <td class="px-4 py-3"><p>{{ $supplier->contact_person ?: '—' }}</p><p class="text-xs text-slate-500">{{ $supplier->phone ?: $supplier->whatsapp ?: '—' }}</p></td>
                        <td class="px-4 py-3">{{ $supplier->purchase_orders_count }}</td>
                        <td class="px-4 py-3">{{ $supplier->invoices_count }}</td>
                        <td class="px-4 py-3 text-end"><a class="font-semibold text-teal-700" href="{{ route('pharmacy.suppliers.edit', $supplier) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No suppliers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($suppliers->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $suppliers->links() }}</div>@endif
    </div>
</div>
@endsection
