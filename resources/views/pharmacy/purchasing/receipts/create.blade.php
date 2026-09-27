@extends('layouts.pharmacy')
@section('title', 'Goods Receipt — '.$order->number)
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div><p class="text-sm font-semibold text-teal-700">Goods receipt</p><h1 class="text-3xl font-bold">{{ $order->number }}</h1><p class="mt-1 text-sm text-slate-500">{{ $order->supplier->name }}</p></div>
    <form method="POST" action="{{ route('pharmacy.purchase-receipts.store', $order) }}" class="space-y-5">
        @csrf
        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-3">
            <label><span class="text-sm font-semibold">Received at</span><input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
            <label class="md:col-span-2"><span class="text-sm font-semibold">Notes</span><input name="notes" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
            <input type="hidden" name="idempotency_key" value="{{ (string) str()->ulid() }}">
        </section>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="overflow-x-auto"><table class="min-w-[1250px] w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-start">Medicine</th><th class="p-3">Receive</th><th class="p-3">Bonus/free</th><th class="p-3">Batch</th><th class="p-3">Mfg</th><th class="p-3">Expiry</th><th class="p-3">Unit cost</th><th class="p-3">Sale price</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($order->lines as $index => $line)
                    <tr>
                        <td class="p-3"><p class="font-semibold">{{ $line->description }}</p><p class="text-xs text-slate-500">Ordered {{ $line->ordered_quantity }}</p><input type="hidden" name="lines[{{ $index }}][purchase_order_line_id]" value="{{ $line->id }}"></td>
                        <td class="p-3"><input type="number" step="0.0001" min="0" name="lines[{{ $index }}][received_quantity]" value="0" class="w-24 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="p-3"><input type="number" step="0.0001" min="0" name="lines[{{ $index }}][bonus_quantity]" value="0" class="w-24 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="p-3"><input name="lines[{{ $index }}][batch_number]" class="w-36 rounded-lg border border-slate-300 px-2 py-2" placeholder="{{ $line->medicine->batch_tracking_required ? 'Required' : 'Optional' }}"></td>
                        <td class="p-3"><input type="date" name="lines[{{ $index }}][manufactured_at]" class="w-36 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="p-3"><input type="date" name="lines[{{ $index }}][expires_at]" class="w-36 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="p-3"><input type="number" step="0.0001" min="0" name="lines[{{ $index }}][unit_cost]" value="{{ $line->unit_cost }}" required class="w-28 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="p-3"><input type="number" step="0.0001" min="0" name="lines[{{ $index }}][sale_price]" class="w-28 rounded-lg border border-slate-300 px-2 py-2"></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        @error('lines')<div class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>@enderror
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Saving this receipt records the physical receipt and lot data only. Inventory quantity changes occur in Batch 11 through an auditable stock movement.</div>
        <button class="rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white">Capture goods receipt</button>
    </form>
</div>
@endsection
