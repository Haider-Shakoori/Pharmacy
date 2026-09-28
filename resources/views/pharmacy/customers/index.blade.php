@extends('layouts.pharmacy')
@section('title', 'Customers — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-teal-700">Customer accounts</p><h1 class="text-3xl font-bold">Customers</h1><p class="mt-1 text-sm text-slate-500">Credit limits, outstanding balances and sales history.</p></div>
        <a href="{{ route('pharmacy.customers.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add customer</a>
    </div>
    <form method="GET" class="flex gap-2 rounded-2xl border border-slate-200 bg-white p-4">
        <input name="search" value="{{ $search }}" placeholder="Search name, phone or email..." class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5">
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Search</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">Customer</th><th class="px-4 py-3 text-start">Sales</th><th class="px-4 py-3 text-end">Credit limit</th><th class="px-4 py-3 text-end">Outstanding</th><th class="px-4 py-3 text-end">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-4 py-3"><a class="font-semibold text-slate-900 hover:text-teal-700" href="{{ route('pharmacy.customers.show', $customer) }}">{{ $customer->name }}</a><p class="text-xs text-slate-500">{{ $customer->phone ?: 'No phone' }} · {{ $customer->is_active ? 'Active' : 'Inactive' }}</p></td>
                        <td class="px-4 py-3">{{ $customer->sales_count }}</td>
                        <td class="px-4 py-3 text-end">{{ number_format((float) $customer->credit_limit, 2) }} AFN</td>
                        <td class="px-4 py-3 text-end font-semibold {{ (float) ($customer->outstanding_credit ?? 0) > 0 ? 'text-amber-700' : 'text-slate-700' }}">{{ number_format((float) ($customer->outstanding_credit ?? 0), 2) }} AFN</td>
                        <td class="px-4 py-3 text-end"><a class="font-semibold text-teal-700" href="{{ route('pharmacy.customers.edit', $customer) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No customers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $customers->links() }}</div>@endif
    </div>
</div>
@endsection
