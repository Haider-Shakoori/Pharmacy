@extends('layouts.platform')

@section('title', 'Platform Dashboard — BusinessOS Pharmacy')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">SaaS owner dashboard</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Platform overview</h2>
        </div>
        <a href="{{ route('platform.tenants.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
            Add pharmacy
        </a>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
        @foreach ([
            ['Total pharmacies', $metrics['total_pharmacies']],
            ['Active', $metrics['active_pharmacies']],
            ['Suspended', $metrics['suspended_pharmacies']],
            ['Archived', $metrics['archived_pharmacies']],
            ['Provisioning failures', $metrics['provisioning_failures']],
            ['Subscription attention', $metrics['subscription_attention']],
            ['Stale devices', $metrics['stale_devices']],
        ] as [$label, $value])
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold">{{ $value }}</p>
            </article>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="font-bold">Subscription attention</h3>
                <p class="mt-1 text-xs text-slate-500">Highest-priority subscription, license and device issues.</p>
            </div>
            <a href="{{ route('platform.monitoring.index') }}" class="text-sm font-semibold text-teal-700">Open monitoring</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($subscriptionAttention as $row)
                <div class="flex flex-col justify-between gap-3 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="font-semibold">{{ $row['pharmacy_name'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ implode(' · ', $row['reasons']) }}</p>
                    </div>
                    <span class="self-start rounded-full px-2.5 py-1 text-xs font-bold {{ $row['severity'] === 'critical' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' }}">{{ $row['health_label'] }}</span>
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-emerald-700">No subscription or device issues require attention.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h3 class="font-bold">Recently created pharmacies</h3>
            <a href="{{ route('platform.tenants.index') }}" class="text-sm font-semibold text-teal-700">View all</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($recentTenants as $tenant)
                <div class="flex items-center justify-between gap-4 px-5 py-4">
                    <div>
                        <p class="font-semibold">{{ $tenant->name }}</p>
                        <p class="text-xs text-slate-500">{{ $tenant->slug }} · {{ str_replace('_', ' ', $tenant->provisioning_status) }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $tenant->status->value }}</span>
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-slate-500">No pharmacies have been created yet.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
