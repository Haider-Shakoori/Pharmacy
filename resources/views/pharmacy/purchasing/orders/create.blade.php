@extends('layouts.pharmacy')
@section('title', 'New Purchase Order — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-7xl space-y-5" x-data="{ lines: [{ medicine_id: '', ordered_quantity: 1, unit_cost: 0, discount_amount: 0, landed_cost_allocated: 0 }] }">
    <div><p class="text-sm font-semibold text-teal-700">Purchasing</p><h1 class="text-3xl font-bold">New purchase order</h1></div>
    <form method="POST" action="{{ route('pharmacy.purchase-orders.store') }}" class="space-y-5">
        @csrf
        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
            <label class="lg:col-span-2"><span class="text-sm font-semibold">Supplier</span><select name="supplier_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"><option value="">Choose supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }} ({{ $supplier->code }})</option>@endforeach</select></label>
            <label><span class="text-sm font-semibold">Order date</span><input type="date" name="order_date" value="{{ old('order_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
            <label><span class="text-sm font-semibold">Expected date</span><input type="date" name="expected_date" value="{{ old('expected_date') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
            <label><span class="text-sm font-semibold">Currency</span><input name="currency" value="{{ old('currency', 'AFN') }}" maxlength="3" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 uppercase"></label>
            <label class="sm:col-span-2 lg:col-span-3"><span class="text-sm font-semibold">Notes</span><input name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-100 p-4"><h2 class="font-bold">Order lines</h2><button type="button" @click="lines.push({ medicine_id: '', ordered_quantity: 1, unit_cost: 0, discount_amount: 0, landed_cost_allocated: 0 })" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Add line</button></div>
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-start">Medicine</th><th class="p-3 text-start">Qty</th><th class="p-3 text-start">Unit cost</th><th class="p-3 text-start">Discount</th><th class="p-3 text-start">Landed cost</th><th class="p-3"></th></tr></thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="index">
                            <tr class="border-t border-slate-100">
                                <td class="p-3"><select x-bind:name="'lines['+index+'][medicine_id]'" x-model="line.medicine_id" required class="w-full rounded-lg border border-slate-300 px-2 py-2"><option value="">Select medicine</option>@foreach ($medicines as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->brand_name }} {{ $medicine->strength ? '· '.$medicine->strength : '' }} ({{ $medicine->purchase_unit }})</option>@endforeach</select></td>
                                <td class="p-3"><input type="number" step="0.0001" min="0.0001" x-bind:name="'lines['+index+'][ordered_quantity]'" x-model="line.ordered_quantity" required class="w-28 rounded-lg border border-slate-300 px-2 py-2"></td>
                                <td class="p-3"><input type="number" step="0.0001" min="0" x-bind:name="'lines['+index+'][unit_cost]'" x-model="line.unit_cost" required class="w-32 rounded-lg border border-slate-300 px-2 py-2"></td>
                                <td class="p-3"><input type="number" step="0.0001" min="0" x-bind:name="'lines['+index+'][discount_amount]'" x-model="line.discount_amount" class="w-28 rounded-lg border border-slate-300 px-2 py-2"></td>
                                <td class="p-3"><input type="number" step="0.0001" min="0" x-bind:name="'lines['+index+'][landed_cost_allocated]'" x-model="line.landed_cost_allocated" class="w-28 rounded-lg border border-slate-300 px-2 py-2"></td>
                                <td class="p-3 text-end"><button type="button" @click="if(lines.length > 1) lines.splice(index,1)" class="text-xs font-bold text-red-600">Remove</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
        @error('lines')<div class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>@enderror
        <button class="rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white">Create purchase order</button>
    </form>
</div>
@endsection
