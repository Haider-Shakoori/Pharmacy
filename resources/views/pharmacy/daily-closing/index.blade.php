@extends('layouts.pharmacy')

@section('title', 'Daily Closing — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
        <div><p class="text-sm font-semibold text-teal-700">Cash control</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Daily Closing</h1><p class="mt-1 text-sm text-slate-500">Business date {{ $businessDate }} · shifts, collections and reconciliation.</p></div>
        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasPermission('safe.view'))<a href="{{ route('pharmacy.safe.index') }}" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-900">{{ __('safe.nav') }}</a>@endif
            <form method="GET"><select name="location" onchange="this.form.submit()" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5">@foreach ($locations as $item)<option value="{{ $item->id }}" @selected($location?->id === $item->id)>{{ $item->branch?->name }} · {{ $item->name }}</option>@endforeach</select></form>
        </div>
    </div>

    @if (! $location)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">Create an inventory location before using Daily Closing.</div>
    @else
        @if ($closing)
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide">Closing status</p><p class="mt-1 text-lg font-black">{{ strtoupper($closing->status) }}</p></div><div class="flex gap-2">
                    @if ($closing->status === 'finalized' && auth()->user()->hasPermission('daily_closing.approve'))<form method="POST" action="{{ route('pharmacy.daily-closing.approve', $closing) }}">@csrf<button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Approve</button></form>@endif
                    @if (in_array($closing->status, ['finalized','approved']) && auth()->user()->hasPermission('daily_closing.reopen'))<form method="POST" action="{{ route('pharmacy.daily-closing.reopen', $closing) }}" class="flex gap-2">@csrf<input name="reason" required placeholder="Reopen reason" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Reopen</button></form>@endif
                </div></div>
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['Sales',$snapshot['gross_sales']],['Cash collected',$snapshot['cash_collected']],['Bank + mobile',(float)$snapshot['bank_collected']+(float)$snapshot['mobile_collected']],['Credit sales',$snapshot['credit_sales']]] as [$label,$value])
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-black">{{ number_format((float) $value, 2) }} AFN</p></div>
            @endforeach
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="font-bold">My cashier shift</h2>
                @if ($openShift)
                    <div class="mt-3 rounded-xl bg-teal-50 p-3 text-sm">Opened {{ $openShift->opened_at->format('H:i') }} · Opening cash {{ number_format((float) $openShift->opening_cash, 2) }} AFN</div>
                    <form method="POST" action="{{ route('pharmacy.daily-closing.shifts.close', $openShift) }}" class="mt-4 space-y-3">@csrf<input type="number" step="0.01" min="0" name="counted_cash" required placeholder="Counted cash" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><textarea name="notes" rows="2" placeholder="Closing notes" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"></textarea><button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Close my shift</button></form>
                @else
                    <form method="POST" action="{{ route('pharmacy.daily-closing.shifts.open') }}" class="mt-4 space-y-3">@csrf<input type="hidden" name="stock_location_id" value="{{ $location->id }}"><input type="number" step="0.01" min="0" name="opening_cash" required value="0" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Open cashier shift</button></form>
                @endif
                <div class="mt-5 divide-y divide-slate-100">@foreach ($shifts as $shift)<div class="flex justify-between gap-3 py-2 text-sm"><span>{{ $shift->user->name }} · {{ strtoupper($shift->status) }}</span><span>{{ $shift->variance !== null ? number_format((float) $shift->variance, 2).' AFN variance' : 'Open' }}</span></div>@endforeach</div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="font-bold">Finalize business day</h2>
                <div class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><span>Opening cash</span><strong>{{ number_format((float) $snapshot['opening_cash'], 2) }} AFN</strong></div><div class="flex justify-between"><span>Cash collected</span><strong>{{ number_format((float) $snapshot['cash_collected'], 2) }} AFN</strong></div><div class="flex justify-between border-t pt-2"><span>Expected cash</span><strong>{{ number_format((float) $snapshot['expected_cash'], 2) }} AFN</strong></div>@if ($closing?->counted_cash !== null)<div class="flex justify-between"><span>Counted</span><strong>{{ number_format((float) $closing->counted_cash, 2) }} AFN</strong></div><div class="flex justify-between"><span>Variance</span><strong>{{ number_format((float) $closing->variance, 2) }} AFN</strong></div>@endif</div>
                @if (! in_array($closing?->status, ['finalized','approved'], true))
                    <form method="POST" action="{{ route('pharmacy.daily-closing.finalize') }}" class="mt-5 space-y-3">@csrf<input type="hidden" name="stock_location_id" value="{{ $location->id }}"><input type="number" step="0.01" min="0" name="counted_cash" placeholder="Counted cash" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><textarea name="notes" rows="3" placeholder="Variance / closing notes" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"></textarea><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Finalize Daily Closing</button></form>
                @endif
            </section>
        </div>

        @if ($closing?->events?->isNotEmpty())<section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Audit history</h2><div class="mt-3 divide-y divide-slate-100">@foreach ($closing->events as $event)<div class="py-2 text-sm"><strong>{{ strtoupper($event->event_type) }}</strong> · {{ $event->occurred_at->format('Y-m-d H:i') }} @if($event->reason) — {{ $event->reason }} @endif</div>@endforeach</div></section>@endif
    @endif
</div>
@endsection
