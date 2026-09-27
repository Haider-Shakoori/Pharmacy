@extends('layouts.pharmacy')

@section('title', __('settings.title').' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <p class="text-sm font-semibold text-teal-700">{{ __('settings.subtitle') }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ __('settings.title') }}</h1>
    </div>

    <form method="POST" action="{{ route('pharmacy.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="text-lg font-bold">{{ __('settings.profile') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold">{{ __('settings.name') }}</span>
                    <input name="name" value="{{ old('name', $profile['name']) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    @error('name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="block">
                    <span class="text-sm font-semibold">{{ __('settings.phone') }}</span>
                    <input name="phone" value="{{ old('phone', $profile['phone']) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                </label>

                <label class="block">
                    <span class="text-sm font-semibold">{{ __('settings.currency') }}</span>
                    <input value="{{ $profile['currency'] }}" disabled class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5">
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold">{{ __('settings.address') }}</span>
                    <textarea name="address" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('address', $profile['address']) }}</textarea>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold">{{ __('settings.timezone') }}</span>
                    <input name="timezone" value="{{ old('timezone', $profile['timezone']) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    @error('timezone')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="block">
                    <span class="text-sm font-semibold">{{ __('settings.language') }}</span>
                    <select name="locale" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        @foreach (['en' => 'English', 'fa' => 'دری', 'ps' => 'پښتو'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('locale', $profile['locale']) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold">{{ __('settings.receipt_footer') }}</span>
                    <textarea name="receipt_footer" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('receipt_footer', $profile['receipt_footer']) }}</textarea>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div>
                <h2 class="text-lg font-bold">{{ __('settings.closing') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('settings.offline_note') }}</p>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-semibold">{{ __('settings.rollover') }}</span>
                    <input type="time" name="business_day_rollover_time" value="{{ old('business_day_rollover_time', $closing['business_day_rollover_time']) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('settings.rollover_help') }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold">{{ __('settings.opening_cash') }}</span>
                    <select name="opening_cash_mode" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        <option value="carry_forward" @selected(old('opening_cash_mode', $closing['opening_cash_mode']) === 'carry_forward')>{{ __('settings.carry_forward') }}</option>
                        <option value="manual" @selected(old('opening_cash_mode', $closing['opening_cash_mode']) === 'manual')>{{ __('settings.manual') }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold">{{ __('settings.variance_threshold') }}</span>
                    <input type="number" min="0" step="0.01" name="variance_note_threshold" value="{{ old('variance_note_threshold', $closing['variance_note_threshold']) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('settings.variance_help') }}</span>
                </label>
            </div>

            @php
                $toggles = [
                    'require_counted_cash' => __('settings.require_counted_cash'),
                    'allow_reopen' => __('settings.allow_reopen'),
                    'require_close_before_next_day' => __('settings.require_close_before_next_day'),
                    'block_online_sales_after_close' => __('settings.block_online_sales_after_close'),
                    'warn_unsynced_devices_before_close' => __('settings.warn_unsynced'),
                ];
            @endphp

            <div class="mt-5 grid gap-2">
                @foreach ($toggles as $key => $label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-medium">
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $closing[$key]))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div>
                <h2 class="text-lg font-bold">Inventory policy</h2>
                <p class="mt-1 text-sm text-slate-500">Shared thresholds for inventory alerts, expiry handling and POS stock allocation.</p>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-semibold">Low-stock threshold</span>
                    <input type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $inventory['low_stock_threshold']) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Near-expiry window (days)</span>
                    <input type="number" min="1" name="near_expiry_days" value="{{ old('near_expiry_days', $inventory['near_expiry_days']) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                </label>
                @foreach ([
                    'block_expired_sales' => 'Block expired batches from sales',
                    'fefo_enabled' => 'Use FEFO by default',
                ] as $key => $label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-medium">
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $inventory[$key]))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <button class="rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white">{{ __('settings.save') }}</button>
    </form>
</div>
@endsection
