@extends('layouts.pharmacy')

@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div class="mb-2 inline-flex rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-800">{{ $subscriptionHealth->label() }}</div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $tenant->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ __('pharmacy.dashboard') }} · {{ auth()->user()->name }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if (auth()->user()->hasPermission('pos.sell'))
                    <a href="{{ route('pharmacy.pos.index') }}" class="rounded-xl bg-emerald-600 px-5 py-3 text-center text-sm font-black text-white shadow-sm hover:bg-emerald-700">
                        {{ __('pharmacy.actions.open_pos') }}
                    </a>
                @endif
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pharmacy.labels.pharmacy_code') }}</p>
                    <p class="mt-1 font-mono font-semibold text-slate-800">{{ $tenant->slug }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-2xl font-bold tracking-tight">{{ $stat['value'] }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ __('pharmacy.alerts.live_note') }}</p>
            </article>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"><div><p class="text-sm font-semibold text-teal-700">Quick actions</p><h2 class="text-lg font-bold">Run the pharmacy</h2></div></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @if(auth()->user()->hasPermission('pos.sell'))<a href="{{ route('pharmacy.pos.index') }}" class="rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white">{{ __('pharmacy.actions.open_pos') }}</a>@endif
            @if(auth()->user()->hasPermission('medicines.manage'))<a href="{{ route('pharmacy.medicines.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">+ {{ __('pharmacy.nav.medicines') }}</a>@endif
            @if(auth()->user()->hasPermission('customers.manage'))<a href="{{ route('pharmacy.customers.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">{{ __('pharmacy.nav.customers') }}</a>@endif
            @if(auth()->user()->hasPermission('purchases.manage'))<a href="{{ route('pharmacy.purchase-orders.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">+ Purchase</a>@endif
            @if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('pharmacy.inventory.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">{{ __('pharmacy.nav.inventory') }}</a>@endif
            @if(auth()->user()->hasPermission('safe.view'))<a href="{{ route('pharmacy.safe.index') }}" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm font-bold text-amber-900 hover:bg-amber-100">{{ __('safe.nav') }}</a>@endif
            @if(auth()->user()->hasPermission('reports.view'))<a href="{{ route('pharmacy.reports.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold hover:bg-slate-50">{{ __('pharmacy.nav.reports') }}</a>@endif
        </div>
    </section>

    @if ($alerts['counts']['total'] > 0)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-sm font-bold text-amber-900">{{ __('pharmacy.alerts.attention') }}</p>
                    <p class="mt-1 text-sm text-amber-800">{{ __('pharmacy.alerts.summary', ['low' => $alerts['counts']['low_stock'], 'near' => $alerts['counts']['near_expiry'], 'expired' => $alerts['counts']['expired']]) }}</p>
                </div>
                <a href="{{ route('pharmacy.alerts.index') }}" class="rounded-xl bg-amber-900 px-4 py-2.5 text-center text-sm font-bold text-white">{{ __('pharmacy.alerts.view_all') }}</a>
            </div>
        </section>
    @endif
</div>
@endsection