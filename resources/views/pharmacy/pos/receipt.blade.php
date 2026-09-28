<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), config('pharmacy.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $sale->sale_number }}</title>@vite(['resources/css/app.css'])<style>@media print{.no-print{display:none!important}body{background:#fff!important}}@page{margin:8mm}</style></head>
<body class="bg-slate-100 p-4 text-slate-900">
<main class="mx-auto max-w-2xl bg-white p-6 shadow-sm">
    <div class="no-print mb-4 flex flex-wrap justify-end gap-2"><a href="{{ route('pharmacy.pos.invoices') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold">All invoices</a>@if(auth()->user()?->hasPermission('returns.manage'))<a href="{{ route('pharmacy.returns.create', $sale) }}" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-900">Return items</a>@endif<button onclick="window.print()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Print</button><a href="{{ route('pharmacy.pos.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold">New sale</a></div>
    <div class="text-center"><h1 class="text-xl font-black">{{ __('pharmacy.product') }}</h1><p class="mt-1 text-sm text-slate-500">{{ $sale->location->branch?->name }} · {{ $sale->location->name }}</p><p class="mt-2 font-mono text-sm">{{ $sale->sale_number }}</p></div>
    <div class="mt-5 grid grid-cols-2 gap-3 border-y border-dashed border-slate-300 py-3 text-sm"><div>Business date: {{ $sale->business_date->toDateString() }}</div><div class="text-end">Cashier: {{ $sale->cashier->name }}</div><div>Customer: {{ $sale->customer?->name ?? 'Walk-in' }}</div><div class="text-end">{{ $sale->completed_at?->format('Y-m-d H:i') }}</div></div>
    @if ($sale->prescription_reference)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"><strong>Prescription:</strong> {{ $sale->prescription_reference }} @if($sale->prescriber_name) · {{ $sale->prescriber_name }} @endif @if($sale->prescription_date) · {{ $sale->prescription_date->toDateString() }} @endif</div>
    @endif
    <table class="mt-4 w-full text-sm"><thead><tr class="border-b"><th class="py-2 text-start">Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead><tbody>
    @foreach ($sale->lines as $line)
        <tr class="border-b border-slate-100 align-top">
            <td class="py-2">
                {{ $line->description }}
                <div class="text-xs text-slate-400">{{ $line->sale_unit }}</div>
                @if ($line->allocations->isNotEmpty())
                    <div class="mt-1 space-y-0.5 text-[11px] text-slate-500">
                        @foreach ($line->allocations as $allocation)
                            <div>Batch {{ $allocation->batch?->batch_number ?: 'Unbatched' }} · {{ rtrim(rtrim($allocation->quantity, '0'), '.') }} × {{ number_format((float) ($allocation->unit_price ?? $line->unit_price), 2) }} AFN @if($allocation->batch?->expires_at) · exp {{ $allocation->batch->expires_at->toDateString() }} @endif</div>
                        @endforeach
                    </div>
                @endif
            </td>
            <td class="text-end">{{ rtrim(rtrim($line->quantity, '0'), '.') }}</td>
            <td class="text-end">{{ number_format((float) $line->unit_price, 2) }}<div class="text-[10px] text-slate-400">avg</div></td>
            <td class="text-end font-semibold">{{ number_format((float) $line->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody></table>
    <div class="ms-auto mt-4 max-w-xs space-y-1 text-sm"><div class="flex justify-between"><span>Subtotal</span><span>{{ number_format((float) $sale->subtotal, 2) }} AFN</span></div><div class="flex justify-between"><span>Discount</span><span>{{ number_format((float) $sale->discount_total, 2) }} AFN</span></div><div class="flex justify-between border-t pt-2 text-lg font-black"><span>Total</span><span>{{ number_format((float) $sale->grand_total, 2) }} AFN</span></div><div class="flex justify-between"><span>Paid</span><span>{{ number_format((float) $sale->paid_total, 2) }} AFN</span></div>@if ((float) $sale->due_total > 0)<div class="flex justify-between font-semibold"><span>Credit due</span><span>{{ number_format((float) $sale->due_total, 2) }} AFN</span></div>@endif @if ((float) $sale->change_total > 0)<div class="flex justify-between"><span>Change</span><span>{{ number_format((float) $sale->change_total, 2) }} AFN</span></div>@endif</div>
</main>
</body></html>
