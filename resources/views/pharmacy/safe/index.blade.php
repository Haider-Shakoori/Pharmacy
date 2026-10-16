@extends('layouts.pharmacy')

@section('title', __('safe.title').' — '.__('pharmacy.product'))

@section('content')
@php($money = fn ($amount) => number_format((float) $amount, 2))
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <p class="text-sm font-semibold text-blue-700">{{ __('safe.subtitle') }}</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">{{ __('safe.title') }}</h1>
    </div>

    @if (session('success'))
        <div role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <form method="GET" action="{{ route('pharmacy.safe.index') }}" class="flex flex-wrap items-end gap-3">
            <label class="flex-1 text-sm font-medium text-slate-700">Safe
                <select name="safe" class="mt-1 w-full rounded-lg border border-slate-300 p-2" onchange="this.form.submit()">
                    @foreach ($safes as $item)
                        <option value="{{ $item->id }}" @selected($safe?->id === $item->id)>{{ $item->name }} ({{ $item->code }})</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">View</button>
        </form>
    </div>

    @if ($safe)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                __('safe.balance') => $balance,
                __('safe.opening_balance') => $snapshot['opening_balance'] ?? '0',
                __('safe.cash_in') => $snapshot['cash_in'] ?? '0',
                __('safe.cash_out') => $snapshot['cash_out'] ?? '0',
            ] as $label => $amount)
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $money($amount) }} AFN</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-lg font-bold">{{ __('safe.movement') }}</h2>
                <form method="POST" action="{{ route('pharmacy.safe.movements.store', $safe) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <label class="block text-sm">Type
                        <select name="movement_type" required class="mt-1 w-full rounded-lg border p-2">
                            @foreach (\App\Services\Safe\SafeService::MANUAL_TYPES as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">Amount (AFN)<input name="amount" type="number" min="0.01" step="0.01" required class="mt-1 w-full rounded-lg border p-2"></label>
                    <label class="block text-sm">Reference<input name="reference" class="mt-1 w-full rounded-lg border p-2"></label>
                    <label class="block text-sm">Reason<textarea name="reason" required class="mt-1 w-full rounded-lg border p-2"></textarea></label>
                    <button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Post movement</button>
                </form>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-lg font-bold">{{ __('safe.pending_pos') }}</h2>
                @forelse ($pendingShifts as $row)
                    <form method="POST" action="{{ route('pharmacy.safe.pos-transfers.store', [$safe, $row['shift']]) }}" class="mb-3 flex flex-wrap items-end gap-3 border-b pb-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <p class="flex-1 text-sm">{{ $row['shift']->user?->name }} — {{ $money($row['remaining']) }} AFN</p>
                        <input type="number" name="amount" required min="0.01" step="0.01" max="{{ $row['remaining'] }}" value="{{ $row['suggested'] }}" class="w-36 rounded-lg border p-2">
                        <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm text-white">Receive</button>
                    </form>
                @empty
                    <p class="text-sm text-slate-500">No pending transfers.</p>
                @endforelse
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 text-lg font-bold">{{ __('safe.closing') }} · {{ $businessDate }}</h2>
            <p class="mb-3 text-sm text-slate-600">{{ __('safe.expected_balance') }}: {{ $money($snapshot['expected_balance'] ?? 0) }} AFN</p>
            @if (!$closing)
                <form method="POST" action="{{ route('pharmacy.safe.closing.finalize', $safe) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="text-sm">Counted balance<input type="number" min="0" step="0.01" name="counted_balance" required class="mt-1 block rounded-lg border p-2"></label>
                    <label class="flex-1 text-sm">Notes<input name="notes" class="mt-1 w-full rounded-lg border p-2"></label>
                    <button class="rounded-lg bg-blue-700 px-4 py-2 text-white">{{ __('safe.finalize') }}</button>
                </form>
            @else
                <p class="text-sm">Status: {{ $closing->status instanceof \BackedEnum ? $closing->status->value : $closing->status }}</p>
            @endif
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-lg font-bold">{{ __('safe.history') }}</h2>
                <table class="w-full text-left text-sm"><thead><tr><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Amount</th></tr></thead><tbody>
                    @forelse ($movements as $movement)
                        <tr class="border-t"><td class="p-2">{{ $movement->occurred_at }}</td><td class="p-2">{{ str_replace('_', ' ', $movement->movement_type) }}</td><td class="p-2">{{ $movement->direction === 'out' ? '-' : '+' }}{{ $money($movement->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="p-2 text-slate-500">No movements yet.</td></tr>
                    @endforelse
                </tbody></table>
            </section>
            <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-lg font-bold">{{ __('safe.closing_history') }}</h2>
                <table class="w-full text-left text-sm"><thead><tr><th class="p-2">Date</th><th class="p-2">Status</th></tr></thead><tbody>
                    @forelse ($recentClosings as $item)
                        <tr class="border-t"><td class="p-2">{{ $item->business_date }}</td><td class="p-2">{{ $item->status instanceof \BackedEnum ? $item->status->value : $item->status }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="p-2 text-slate-500">No closings yet.</td></tr>
                    @endforelse
                </tbody></table>
            </section>
        </div>
    @endif
</div>
@endsection
