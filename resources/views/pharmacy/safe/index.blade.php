@extends('layouts.pharmacy')

@section('title', __('safe.title').' — '.__('pharmacy.product'))
@section('fullscreen', '1')
@section('body_class', 'bg-slate-100 text-slate-900 antialiased')

@section('content')
<div class="flex min-h-screen flex-col bg-slate-100">
    <header class="sticky top-0 z-30 flex min-h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 py-2 shadow-sm sm:px-5">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('pharmacy.dashboard') }}" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-900 font-black text-white">₳</a>
            <div class="min-w-0">
                <p class="truncate text-sm font-black">{{ __('safe.title') }} · {{ app(\App\Support\Tenancy\TenantContext::class)->tenant()->name }}</p>
                <p class="truncate text-xs text-slate-500">{{ __('safe.subtitle') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()" class="hidden rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold hover:bg-slate-50 sm:block">Full screen</button>
            <a href="{{ route('pharmacy.daily-closing.index') }}" class="hidden rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold hover:bg-slate-50 md:block">POS Closing</a>
            <a href="{{ route('pharmacy.dashboard') }}" class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-bold text-white">Exit Safe</a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[1600px] flex-1 space-y-5 p-3 sm:p-5">
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                {{ $errors->first() }}
            </div>
        @endif
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('safe.business_date') }}</p>
                <p class="mt-1 text-xl font-black">{{ $businessDate }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($safes as $item)
                    <a href="{{ route('pharmacy.safe.index', ['safe' => $item->id]) }}" class="rounded-xl px-4 py-2.5 text-sm font-bold {{ $safe?->id === $item->id ? 'bg-teal-700 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                        {{ $item->name }}
                    </a>
                @endforeach
            </div>
        </section>

        @if($safe)
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl bg-slate-900 p-5 text-white shadow-sm sm:col-span-2 xl:col-span-1">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('safe.balance') }}</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format((float)$balance, 2) }} <span class="text-base font-bold text-slate-400">AFN</span></p>
                    <p class="mt-2 text-xs text-slate-400">{{ $safe->code }} · {{ $safe->location?->branch?->name }} / {{ $safe->location?->name }}</p>
                </article>
                @foreach([
                    [__('safe.opening_balance'), $snapshot['opening_balance']],
                    [__('safe.cash_in'), $snapshot['cash_in']],
                    [__('safe.cash_out'), $snapshot['cash_out']],
                    [__('safe.expected_balance'), $snapshot['expected_balance']],
                ] as [$label,$value])
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-black">{{ number_format((float)$value, 2) }} <span class="text-sm text-slate-400">AFN</span></p>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,.65fr)]">
                <div class="space-y-5">
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div><p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ __('safe.pos_transfers') }}</p><h2 class="mt-1 text-lg font-black">{{ __('safe.pending_pos') }}</h2></div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ $pendingShifts->count() }}</span>
                        </div>
                        @forelse($pendingShifts as $row)
                            <div class="grid gap-3 border-b border-slate-100 p-4 last:border-0 md:grid-cols-[1fr_auto] md:items-center">
                                <div>
                                    <p class="font-bold">{{ $row['shift']->user?->name ?? 'Cashier' }} · {{ $row['shift']->business_date->toDateString() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Counted {{ number_format((float)$row['shift']->counted_cash,2) }} AFN · Opening float {{ number_format((float)$row['shift']->opening_cash,2) }} AFN · Already transferred {{ number_format((float)$row['transferred'],2) }} AFN</p>
                                    <p class="mt-1 text-sm font-bold text-amber-700">Transferable POS cash: {{ number_format((float)$row['remaining'],2) }} AFN</p>
                                </div>
                                @if(auth()->user()->hasPermission('safe.transfer_pos'))
                                    <form method="POST" action="{{ route('pharmacy.safe.pos-transfers.store', [$safe, $row['shift']]) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="safe:pos:{{ $row['shift']->id }}:{{ \Illuminate\Support\Str::uuid() }}">
                                        <input type="number" name="amount" min="0.01" step="0.01" value="{{ number_format((float)$row['suggested'],2,'.','') }}" class="w-32 rounded-xl border border-slate-300 px-3 py-2.5 text-end text-sm font-bold">
                                        <button class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-black text-white">Transfer</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-slate-500">No closed POS shift is waiting for a cash transfer. Close the cashier shift first, then its cash appears here.</div>
                        @endforelse
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Audit trail</p><h2 class="mt-1 text-lg font-black">{{ __('safe.history') }}</h2></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-[900px] w-full text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">Time</th><th class="px-4 py-3 text-start">Type</th><th class="px-4 py-3 text-start">Reference</th><th class="px-4 py-3 text-start">User</th><th class="px-4 py-3 text-end">In</th><th class="px-4 py-3 text-end">Out</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($movements as $movement)
                                        <tr>
                                            <td class="px-4 py-3">{{ $movement->occurred_at?->format('Y-m-d H:i') }}</td>
                                            <td class="px-4 py-3"><p class="font-semibold">{{ str($movement->movement_type)->headline() }}</p><p class="text-xs text-slate-500">{{ $movement->reason }}</p></td>
                                            <td class="px-4 py-3 font-mono text-xs">{{ $movement->reference ?: '—' }}</td>
                                            <td class="px-4 py-3">{{ $movement->creator?->name ?? 'System' }}</td>
                                            <td class="px-4 py-3 text-end font-bold text-emerald-700">{{ $movement->direction === 'in' ? number_format((float)$movement->amount,2).' AFN' : '—' }}</td>
                                            <td class="px-4 py-3 text-end font-bold text-red-700">{{ $movement->direction === 'out' ? number_format((float)$movement->amount,2).' AFN' : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No safe movements yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5">
                    @if(auth()->user()->hasPermission('safe.manage'))
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ __('safe.movement') }}</p>
                            <h2 class="mt-1 text-lg font-black">Cash in / cash out</h2>
                            <form method="POST" action="{{ route('pharmacy.safe.movements.store', $safe) }}" class="mt-4 space-y-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="safe:manual:{{ \Illuminate\Support\Str::uuid() }}">
                                <select name="movement_type" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                    <option value="owner_deposit">Owner deposit → Safe</option>
                                    <option value="owner_withdrawal">Safe → Owner withdrawal</option>
                                    <option value="bank_deposit">Safe → Bank deposit</option>
                                    <option value="bank_withdrawal">Bank → Safe withdrawal</option>
                                </select>
                                <input type="number" name="amount" min="0.01" step="0.01" required placeholder="Amount AFN" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-end font-bold">
                                <input name="reference" placeholder="Reference (optional)" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                <textarea name="reason" required rows="2" placeholder="Reason" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                                <button class="w-full rounded-xl bg-slate-900 px-4 py-3 font-black text-white">Post safe movement</button>
                            </form>
                        </section>
                    @endif

                    <section class="rounded-2xl border {{ $closing && in_array($closing->status,['finalized','approved']) ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ __('safe.closing') }}</p><h2 class="mt-1 text-lg font-black">{{ $businessDate }}</h2></div>
                            @if($closing)<span class="rounded-full bg-white px-3 py-1 text-xs font-black shadow-sm">{{ str($closing->status)->headline() }}</span>@endif
                        </div>

                        @if($closing && in_array($closing->status,['finalized','approved']))
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl bg-white p-3"><p class="text-xs text-slate-500">Expected</p><p class="mt-1 font-black">{{ number_format((float)$closing->expected_balance,2) }} AFN</p></div>
                                <div class="rounded-xl bg-white p-3"><p class="text-xs text-slate-500">Counted</p><p class="mt-1 font-black">{{ number_format((float)$closing->counted_balance,2) }} AFN</p></div>
                                <div class="col-span-2 rounded-xl bg-white p-3"><p class="text-xs text-slate-500">{{ __('safe.variance') }}</p><p class="mt-1 text-xl font-black {{ (float)$closing->variance === 0.0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ number_format((float)$closing->variance,2) }} AFN</p></div>
                            </div>
                            @if(auth()->user()->hasPermission('safe.approve') && $closing->status === 'finalized')
                                <form method="POST" action="{{ route('pharmacy.safe.closing.approve', $closing) }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-emerald-700 px-4 py-3 font-black text-white">{{ __('safe.approve') }}</button></form>
                            @endif
                            @if(auth()->user()->hasPermission('safe.reopen'))
                                <form method="POST" action="{{ route('pharmacy.safe.closing.reopen', $closing) }}" class="mt-3 space-y-2">@csrf<textarea name="reason" required rows="2" placeholder="Required reason for reopening" class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5 text-sm"></textarea><button class="w-full rounded-xl border border-amber-300 bg-amber-100 px-4 py-3 font-black text-amber-900">{{ __('safe.reopen') }}</button></form>
                            @endif
                        @elseif(auth()->user()->hasPermission('safe.close'))
                            <div class="mt-4 rounded-xl bg-sky-50 p-3 text-xs leading-5 text-sky-900">Close all POS cashier shifts, transfer their cash here, and finalize POS Daily Closing first. Then count the physical money in this safe.</div>
                            <form method="POST" action="{{ route('pharmacy.safe.closing.finalize', $safe) }}" class="mt-4 space-y-3">
                                @csrf
                                <label class="block text-sm font-bold">{{ __('safe.counted_balance') }}
                                    <input type="number" name="counted_balance" min="0" step="0.01" required value="{{ number_format((float)$snapshot['expected_balance'],2,'.','') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-3 text-end text-lg font-black">
                                </label>
                                <textarea name="notes" rows="2" placeholder="Closing note; required if counted amount differs" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                                <button class="w-full rounded-xl bg-teal-700 px-4 py-3 font-black text-white">{{ __('safe.finalize') }}</button>
                            </form>
                        @else
                            <p class="mt-4 text-sm text-slate-600">You can view this safe but do not have permission to perform Safe Closing.</p>
                        @endif
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-black">{{ __('safe.closing_history') }}</h2></div>
                        <div class="divide-y divide-slate-100">
                            @forelse($recentClosings as $item)
                                <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm"><div><p class="font-bold">{{ $item->business_date->toDateString() }}</p><p class="text-xs text-slate-500">{{ str($item->status)->headline() }}</p></div><div class="text-end"><p class="font-black">{{ number_format((float)$item->counted_balance,2) }} AFN</p><p class="text-xs {{ (float)$item->variance === 0.0 ? 'text-emerald-700' : 'text-amber-700' }}">Variance {{ number_format((float)$item->variance,2) }}</p></div></div>
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-slate-500">No Safe Closing history yet.</div>
                            @endforelse
                        </div>
                    </section>

                    @if(auth()->user()->hasPermission('safe.manage'))
                        <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <summary class="cursor-pointer font-black">{{ __('safe.create_safe') }}</summary>
                            <form method="POST" action="{{ route('pharmacy.safe.safes.store') }}" class="mt-4 space-y-3">
                                @csrf
                                <select name="stock_location_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->branch?->name }} · {{ $location->name }}</option>@endforeach</select>
                                <input name="code" required placeholder="SAFE-02" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase">
                                <input name="name" required placeholder="Back Office Safe" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                <textarea name="notes" rows="2" placeholder="Notes" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                                <button class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 font-black">Create safe</button>
                            </form>
                        </details>
                    @endif
                </aside>
            </section>
        @endif
    </main>
</div>
@endsection
