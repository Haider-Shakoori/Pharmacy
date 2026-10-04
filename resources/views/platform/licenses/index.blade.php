@extends('layouts.platform')

@section('title', 'Licenses — Platform Admin')

@section('content')
@php($routePrefix = request()->routeIs('legacy.platform.*') ? 'legacy.platform.' : 'platform.')
<div class="mx-auto max-w-7xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Activation security</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">Licenses & devices</h2>
        <p class="mt-2 text-sm text-slate-500">Windows keys are redeem-once and device-bound. Plaintext keys are never stored and can only be shown when first generated or reset by support.</p>
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
                        <th class="px-4 py-3 text-start">Windows key</th>
                        <th class="px-4 py-3 text-start">Devices</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subscriptions as $subscription)
                        @php($license = $subscription->license)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $subscription->business->pharmacy_name }}</p>
                                <p class="text-xs text-slate-500">{{ $subscription->business->slug }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $subscription->plan->name }}</td>
                            <td class="px-4 py-3">
                                @if ($license)
                                    <p class="font-mono text-xs">{{ $license->key_hint }}</p>
                                    <p class="mt-1 text-xs font-semibold {{ $license->status->value === 'active' ? 'text-emerald-700' : 'text-red-700' }}">{{ $license->status->value }} · v{{ $license->version }}</p>
                                @else
                                    <span class="text-xs text-amber-700">Not generated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($license?->windows_consumed_at)
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">Consumed</span>
                                    <p class="mt-1 text-xs text-slate-500">{{ $license->windows_consumed_at->diffForHumans() }}</p>
                                @elseif ($license)
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">Unused</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">{{ $license?->activations_count ?? 0 }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @if ($license)
                                        <a href="{{ route($routePrefix.'licenses.show', $subscription) }}" class="rounded-lg border border-teal-200 px-2.5 py-1.5 text-xs font-semibold text-teal-800">View devices</a>
                                    @endif
                                    <form method="POST" action="{{ route($routePrefix.'licenses.regenerate', $subscription) }}">
                                        @csrf
                                        <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold">{{ $license ? 'Issue new key' : 'Generate' }}</button>
                                    </form>
                                    @if ($license?->status?->value === 'active')
                                        <form method="POST" action="{{ route($routePrefix.'licenses.revoke', $subscription) }}">
                                            @csrf
                                            <button class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700">Revoke</button>
                                        </form>
                                    @endif
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
