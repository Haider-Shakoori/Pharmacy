@extends('layouts.platform')

@section('title', 'Licenses — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Activation security</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">Licenses & Devices</h2>
        <p class="mt-2 text-sm text-slate-500">Windows activation keys are one-time. After a PC is activated, another activation requires support to release/replace the device and issue a new key.</p>
    </div>

    <form method="GET" class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <input name="search" value="{{ $search }}" placeholder="Search pharmacy" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Search</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">Pharmacy</th>
                        <th class="px-4 py-3 text-start">Plan</th>
                        <th class="px-4 py-3 text-start">License</th>
                        <th class="px-4 py-3 text-start">Windows PCs</th>
                        <th class="px-4 py-3 text-start">Cloud sync</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subscriptions as $subscription)
                        @php($activeWindows = $subscription->license?->activations?->count() ?? 0)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $subscription->business->pharmacy_name }}</p>
                                <p class="text-xs text-slate-500">{{ $subscription->business->slug }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $subscription->plan->name }}</td>
                            <td class="px-4 py-3">
                                @if ($subscription->license)
                                    <p class="text-xs font-semibold {{ $subscription->license->status->value === 'active' ? 'text-emerald-700' : 'text-red-700' }}">
                                        {{ ucfirst($subscription->license->status->value) }} · v{{ $subscription->license->version }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">Secret stored as hash only</p>
                                @else
                                    <span class="text-xs text-amber-700">Not generated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">{{ $activeWindows }}</span>
                                <span class="text-slate-500">/ {{ $subscription->plan->max_windows_devices ?? '∞' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($subscription->business->desktop_cloud_sync_enabled ?? true)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">ON</span>
                                @else
                                    <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700">OFF</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ AppSupportPlatformRoute::url('licenses.show', $subscription) }}"
                                       class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">
                                        Devices & sessions
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No subscriptions exist yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscriptions->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $subscriptions->links() }}</div>
        @endif
    </div>
</div>
@endsection
