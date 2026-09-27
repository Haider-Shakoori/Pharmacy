@extends('layouts.pharmacy')

@section('title', $batch->medicine->brand_name.' — Inventory')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Inventory batch</p>
            <h1 class="mt-1 text-3xl font-bold">{{ $batch->medicine->brand_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $batch->batch_number ?: 'Unbatched' }} · {{ $batch->location->branch->name }} / {{ $batch->location->name }}</p>
        </div>
        <a href="{{ route('pharmacy.inventory.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Back to inventory</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['Available', $batch->available_quantity],
            ['Received', $batch->received_quantity],
            ['Cost', $batch->purchase_cost],
            ['Sale price', $batch->sale_price ?? '—'],
            ['Expiry', $batch->expires_at?->toDateString() ?? '—'],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-bold">{{ $value }}</p></div>
        @endforeach
    </div>

    @if ($batch->isExpired())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">This batch is expired and is excluded from normal FEFO sales allocation.</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        @if (auth()->user()->hasPermission('inventory.status'))
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Batch status</h2>
            <p class="mt-1 text-sm text-slate-500">Current: {{ str($batch->status)->headline() }}. Every change creates an audit event.</p>
            <form method="POST" action="{{ route('pharmacy.inventory.status', $batch) }}" class="mt-4 grid gap-3 sm:grid-cols-[180px_1fr_auto]">
                @csrf
                <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5">
                    @foreach (['active','quarantined','recalled','damaged'] as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach
                </select>
                <input name="reason" required placeholder="Required reason" class="rounded-xl border border-slate-300 px-3 py-2.5">
                <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Change</button>
            </form>
        </section>
        @endif

        @if (auth()->user()->hasPermission('inventory.adjust'))
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Stock adjustment</h2>
            <p class="mt-1 text-sm text-slate-500">Adjustments create a source document and immutable movement. Negative stock is rejected.</p>
            <form method="POST" action="{{ route('pharmacy.inventory.adjustments.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="product_batch_id" value="{{ $batch->id }}">
                <input type="number" step="0.0001" name="quantity_delta" required placeholder="+/- quantity" class="rounded-xl border border-slate-300 px-3 py-2.5">
                <select name="reason_code" class="rounded-xl border border-slate-300 px-3 py-2.5">
                    <option value="count_correction">Count correction</option><option value="damage">Damage</option><option value="wastage">Wastage</option><option value="found_stock">Found stock</option><option value="other">Other</option>
                </select>
                <input name="reason" required placeholder="Reason" class="rounded-xl border border-slate-300 px-3 py-2.5 sm:col-span-2">
                <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white sm:col-span-2">Post adjustment</button>
            </form>
        </section>
        @endif
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 p-4"><h2 class="font-bold">Stock movement ledger</h2><p class="mt-1 text-xs text-slate-500">Latest 100 movements. Existing entries are never silently rewritten.</p></div>
        <div class="overflow-x-auto"><table class="min-w-[1000px] w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">Time</th><th class="px-4 py-3 text-start">Type</th><th class="px-4 py-3 text-end">Delta</th><th class="px-4 py-3 text-end">Balance</th><th class="px-4 py-3 text-start">Source</th><th class="px-4 py-3 text-start">Reason</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse ($batch->movements as $movement)<tr><td class="px-4 py-3">{{ $movement->occurred_at->format('Y-m-d H:i') }}</td><td class="px-4 py-3">{{ str($movement->movement_type)->headline() }}</td><td class="px-4 py-3 text-end font-semibold">{{ $movement->quantity_delta }}</td><td class="px-4 py-3 text-end">{{ $movement->balance_after }}</td><td class="px-4 py-3 text-xs">{{ class_basename($movement->source_type) }} · {{ $movement->source_id }}</td><td class="px-4 py-3">{{ $movement->reason ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No movements.</td></tr>@endforelse</tbody>
        </table></div>
    </section>

    @if ($batch->statusEvents->isNotEmpty())
    <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Status audit</h2><div class="mt-3 divide-y divide-slate-100">@foreach ($batch->statusEvents as $event)<div class="py-2 text-sm"><span class="font-semibold">{{ str($event->from_status)->headline() }} → {{ str($event->to_status)->headline() }}</span> · {{ $event->reason }} <span class="text-slate-400">({{ $event->changed_at->format('Y-m-d H:i') }})</span></div>@endforeach</div></section>
    @endif
</div>
@endsection
