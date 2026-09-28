@extends('layouts.pharmacy')

@section('title', __('pharmacy.alerts.title').' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">{{ __('pharmacy.alerts.subtitle') }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ __('pharmacy.alerts.title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('pharmacy.alerts.policy', ['low' => $alerts['policy']['low_stock_threshold'], 'days' => $alerts['policy']['near_expiry_days']]) }}</p>
        </div>
        <a href="{{ route('pharmacy.inventory.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">{{ __('pharmacy.nav.inventory') }}</a>
    </div>

    <section class="grid gap-4 sm:grid-cols-3">
        <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-sm font-semibold text-amber-800">{{ __('pharmacy.alerts.low_stock') }}</p>
            <p class="mt-2 text-3xl font-black text-amber-950">{{ $alerts['counts']['low_stock'] }}</p>
        </article>
        <article class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
            <p class="text-sm font-semibold text-orange-800">{{ __('pharmacy.alerts.near_expiry') }}</p>
            <p class="mt-2 text-3xl font-black text-orange-950">{{ $alerts['counts']['near_expiry'] }}</p>
        </article>
        <article class="rounded-2xl border border-red-200 bg-red-50 p-5">
            <p class="text-sm font-semibold text-red-800">{{ __('pharmacy.alerts.expired') }}</p>
            <p class="mt-2 text-3xl font-black text-red-950">{{ $alerts['counts']['expired'] }}</p>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold">{{ __('pharmacy.alerts.low_stock') }}</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">{{ __('pharmacy.alerts.medicine') }}</th><th class="px-5 py-3">{{ __('pharmacy.alerts.available') }}</th><th class="px-5 py-3">{{ __('pharmacy.alerts.threshold') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($alerts['low_stock'] as $item)
                        <tr><td class="px-5 py-3 font-semibold">{{ $item['name'] }} <span class="font-normal text-slate-400">{{ $item['medicine_code'] }}</span></td><td class="px-5 py-3">{{ $item['available_quantity'] }}</td><td class="px-5 py-3">{{ $item['threshold'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ __('pharmacy.alerts.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach (['near_expiry' => 'near_expiry', 'expired' => 'expired'] as $key => $labelKey)
        <section class="rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold">{{ __('pharmacy.alerts.'.$labelKey) }}</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">{{ __('pharmacy.alerts.medicine') }}</th><th class="px-5 py-3">{{ __('pharmacy.alerts.batch') }}</th><th class="px-5 py-3">{{ __('pharmacy.alerts.location') }}</th><th class="px-5 py-3">{{ __('pharmacy.alerts.available') }}</th><th class="px-5 py-3">{{ __('pharmacy.alerts.expiry') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($alerts[$key] as $item)
                            <tr><td class="px-5 py-3 font-semibold">{{ $item['name'] }}</td><td class="px-5 py-3">{{ $item['batch_number'] }}</td><td class="px-5 py-3">{{ $item['location'] }}</td><td class="px-5 py-3">{{ $item['available_quantity'] }}</td><td class="px-5 py-3">{{ $item['expires_at'] }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">{{ __('pharmacy.alerts.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
@endsection
