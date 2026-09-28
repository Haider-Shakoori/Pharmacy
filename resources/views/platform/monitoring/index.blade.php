@extends('layouts.platform')

@section('title', 'Subscription Monitoring — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">SaaS health operations</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Subscription monitoring</h2>
            <p class="mt-2 text-sm text-slate-500">Trials, renewals, license health and Android device check-ins across every pharmacy.</p>
        </div>
        <a href="{{ route('platform.subscriptions.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-700">Manage subscriptions</a>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Needs attention', $metrics['attention'], 'text-red-700'],
            ['Operational', $metrics['operational'], 'text-emerald-700'],
            ['Active devices', $metrics['active_devices'], 'text-slate-900'],
            ['Stale devices', $metrics['stale_devices'], 'text-amber-700'],
            ['Trials', $metrics['trials'], 'text-blue-700'],
            ['Expiring paid', $metrics['expiring'], 'text-orange-700'],
            ['No subscription', $metrics['no_subscription'], 'text-red-700'],
            ['Total pharmacies', $metrics['total_pharmacies'], 'text-slate-900'],
        ] as [$label, $value, $tone])
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-black {{ $tone }}">{{ $value }}</p>
            </article>
        @endforeach
    </section>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_220px_auto]">
        <input name="search" value="{{ $search }}" placeholder="Search pharmacy, slug or plan" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <select name="state" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="attention" @selected($state === 'attention')>Needs attention</option>
            <option value="all" @selected($state === 'all')>All pharmacies</option>
            <option value="operational" @selected($state === 'operational')>Operational only</option>
            <option value="non_operational" @selected($state === 'non_operational')>Non-operational only</option>
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">Pharmacy</th>
                        <th class="px-4 py-3 text-start">Health</th>
                        <th class="px-4 py-3 text-start">Plan / deadline</th>
                        <th class="px-4 py-3 text-start">License</th>
                        <th class="px-4 py-3 text-start">Devices</th>
                        <th class="px-4 py-3 text-start">Operator attention</th>
                        <th class="px-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="{{ $row['severity'] === 'critical' ? 'bg-red-50/50' : ($row['severity'] === 'warning' ? 'bg-amber-50/40' : '') }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $row['pharmacy_name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $row['slug'] }} · {{ $row['tenant_status'] }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $row['operational'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $row['health_label'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $row['plan_name'] ?? 'No plan' }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    @if ($row['deadline'])
                                        Ends {{ $row['deadline']->format('Y-m-d H:i') }} · {{ $row['deadline']->diffForHumans() }}
                                    @else
                                        No end date
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-xs font-semibold">{{ $row['license_status'] ?? 'missing' }}</p>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $row['license_hint'] ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $row['active_devices'] }}{{ $row['max_devices'] !== null ? ' / '.$row['max_devices'] : '' }} active</p>
                                <p class="mt-1 text-xs {{ $row['stale_devices'] > 0 ? 'font-semibold text-amber-700' : 'text-slate-500' }}">{{ $row['stale_devices'] }} stale · last seen {{ $row['last_seen_at']?->diffForHumans() ?? 'never' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @if ($row['reasons'])
                                    <ul class="space-y-1 text-xs">
                                        @foreach ($row['reasons'] as $reason)
                                            <li class="{{ $row['severity'] === 'critical' ? 'font-semibold text-red-700' : 'text-amber-800' }}">• {{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-xs text-emerald-700">No action required</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('platform.subscriptions.edit', $row['tenant_id']) }}" class="font-bold text-teal-700">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No pharmacies match this monitoring view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </section>

    <p class="text-xs text-slate-500">A device is marked stale when an active activation has not checked in for {{ \App\Services\Subscriptions\PlatformSubscriptionMonitoringService::DEVICE_STALE_AFTER_HOURS }} hours.</p>
</div>
@endsection
