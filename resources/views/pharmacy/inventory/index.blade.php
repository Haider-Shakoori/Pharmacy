@extends('layouts.pharmacy')

@section('title', 'Inventory — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Batch-aware stock</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Inventory</h1>
            <p class="mt-1 text-sm text-slate-500">FEFO-ready stock by batch, expiry and location.</p>
        </div>
        <a href="{{ route('pharmacy.inventory.locations') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Branches & locations</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">Low-stock policy</p><p class="mt-2 text-xl font-bold">{{ $policy['low_stock_threshold'] }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">Near-expiry window</p><p class="mt-2 text-xl font-bold">{{ $policy['near_expiry_days'] }} days</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">FEFO</p><p class="mt-2 text-xl font-bold">{{ $policy['fefo_enabled'] ? 'Enabled' : 'Disabled' }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">Expired sales</p><p class="mt-2 text-xl font-bold">{{ $policy['block_expired_sales'] ? 'Blocked' : 'Policy review' }}</p></div>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_180px_180px_220px_auto]">
        <input name="search" value="{{ $search }}" placeholder="Medicine, code or batch" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All statuses</option>
            @foreach (['active','depleted','quarantined','recalled','damaged'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ str($option)->headline() }}</option>
            @endforeach
        </select>
        <select name="expiry" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All expiry</option>
            <option value="expired" @selected($expiry === 'expired')>Expired</option>
            <option value="near" @selected($expiry === 'near')>Near expiry</option>
            <option value="no_expiry" @selected($expiry === 'no_expiry')>No expiry</option>
        </select>
        <select name="location" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All locations</option>
            @foreach ($locations as $item)
                <option value="{{ $item->id }}" @selected($location === $item->id)>{{ $item->branch->name }} · {{ $item->name }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-[1050px] w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3 text-start">Medicine</th><th class="px-4 py-3 text-start">Batch</th><th class="px-4 py-3 text-start">Location</th><th class="px-4 py-3 text-start">Expiry</th><th class="px-4 py-3 text-start">Status</th><th class="px-4 py-3 text-end">Available</th><th class="px-4 py-3 text-end">Cost</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($batches as $batch)
                    @php
                        $expired = $batch->expires_at?->isBefore(today()) ?? false;
                        $near = $batch->expires_at && ! $expired && $batch->expires_at->lte(today()->addDays((int) $policy['near_expiry_days']));
                        $low = BrickMathBigDecimal::of($batch->available_quantity)->isLessThanOrEqualTo(BrickMathBigDecimal::of((string) $policy['low_stock_threshold']));
                    @endphp
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('pharmacy.inventory.show', $batch) }}" class="font-semibold text-teal-700">{{ $batch->medicine->brand_name }}</a><p class="text-xs text-slate-500">{{ $batch->medicine->generic_name }} {{ $batch->medicine->strength }}</p></td>
                        <td class="px-4 py-3">{{ $batch->batch_number ?: 'Unbatched' }}</td>
                        <td class="px-4 py-3">{{ $batch->location->branch->name }} · {{ $batch->location->name }}</td>
                        <td class="px-4 py-3"><span class="{{ $expired ? 'font-bold text-red-700' : ($near ? 'font-semibold text-amber-700' : '') }}">{{ $batch->expires_at?->toDateString() ?? '—' }}</span></td>
                        <td class="px-4 py-3">{{ str($batch->status)->headline() }}</td>
                        <td class="px-4 py-3 text-end"><span class="{{ $low ? 'font-bold text-amber-700' : 'font-semibold' }}">{{ $batch->available_quantity }}</span></td>
                        <td class="px-4 py-3 text-end">{{ $batch->purchase_cost }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No inventory batches found. Post a goods receipt to inventory to create stock.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($batches->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $batches->links() }}</div>@endif
    </div>
</div>
@endsection
