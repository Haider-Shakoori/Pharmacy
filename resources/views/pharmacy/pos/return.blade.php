@extends('layouts.pharmacy')

@section('title', 'Return '.$sale->sale_number.' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div><p class="text-sm font-semibold text-teal-700">Sale return</p><h1 class="mt-1 text-3xl font-bold">Return {{ $sale->sale_number }}</h1><p class="mt-1 text-sm text-slate-500">Returns are tied to the original sold batches. Expired, quarantined or recalled stock is not restored to sellable inventory.</p></div>

    <form method="POST" action="{{ route('pharmacy.returns.store', $sale) }}" class="space-y-5">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-start">Item</th><th class="px-4 py-3 text-end">Sold</th><th class="px-4 py-3 text-end">Already returned</th><th class="px-4 py-3 text-end">Return qty</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($sale->lines as $index => $line)
                        <tr>
                            <td class="px-4 py-3"><strong>{{ $line->description }}</strong><div class="text-xs text-slate-500">{{ number_format((float) $line->line_total, 2) }} AFN net line value</div></td>
                            <td class="px-4 py-3 text-end">{{ rtrim(rtrim($line->quantity, '0'), '.') }}</td>
                            <td class="px-4 py-3 text-end">{{ rtrim(rtrim((string) ($line->returned_quantity ?? 0), '0'), '.') }}</td>
                            <td class="px-4 py-3 text-end">
                                <input type="hidden" name="lines[{{ $index }}][sale_line_id]" value="{{ $line->id }}">
                                <input type="number" min="0" max="{{ max(0, (float)$line->quantity-(float)($line->returned_quantity ?? 0)) }}" step="0.0001" name="lines[{{ $index }}][quantity]" value="0" class="w-28 rounded-lg border border-slate-300 px-2 py-2 text-end">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <label class="block"><span class="text-sm font-semibold">Return reason</span><textarea name="reason" required rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></textarea></label>
            <div class="mt-4 grid gap-3 sm:grid-cols-[140px_1fr]">
                <select name="refunds[0][method]" class="rounded-xl border border-slate-300 px-3 py-2.5"><option value="cash">Cash refund</option><option value="bank">Bank refund</option><option value="mobile">Mobile refund</option><option value="credit">Reduce credit</option></select>
                <input type="number" step="0.0001" min="0.0001" name="refunds[0][amount]" required placeholder="Exact calculated refund amount" class="rounded-xl border border-slate-300 px-3 py-2.5">
            </div>
            <p class="mt-2 text-xs text-slate-500">The server validates the refund amount against the selected quantities and original net sale-line values.</p>
        </section>

        <div class="flex gap-3"><button class="rounded-xl bg-teal-700 px-5 py-3 font-bold text-white">Complete return</button><a href="{{ route('pharmacy.pos.receipt', $sale) }}" class="px-4 py-3 text-sm font-semibold">Cancel</a></div>
    </form>
</div>
@endsection
