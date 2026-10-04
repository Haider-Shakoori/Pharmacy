@extends('layouts.platform')

@section('title', 'Licenses — Platform Admin')

@section('content')
@php($platformRoutePrefix = request()->routeIs('legacy.platform.*') ? 'legacy.platform.' : 'platform.')
<div class="mx-auto max-w-7xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Activation security</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">Licenses & devices</h2>
        <p class="mt-2 text-sm text-slate-500">Windows activation keys are one-time. After first successful activation the key is consumed and support must issue another key for a new or replaced PC.</p>
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
                        <th class="px-4 py-3 text-start">Activation key</th>
                        <th class="px-4 py-3 text-start">Windows devices</th>
                        <th class="px-4 py-3 text-start">Last seen</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subscriptions as $subscription)
                        @php
                            $license = $subscription->license;
                            $windows = $license?->activations ?? collect();
                            $activeWindows = $windows->whereNull('revoked_at');
                            $lastSeen = $windows->sortByDesc('last_seen_at')->first()?->last_seen_at;
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $subscription->business->pharmacy_name }}</p>
                                <p class="text-xs text-slate-500">{{ $subscription->business->slug }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $subscription->plan->name }}</td>
                            <td class="px-4 py-3">
                                @if ($license)
                                    <p class="font-mono text-xs">{{ $license->key_hint }}</p>
                                    @if ($license->activation_key_consumed_at)
                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">Consumed · one-time</span>
                                    @else
                                        <span class="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">Ready · one-time</span>
                                    @endif
                                @else
                                    <span class="text-xs text-amber-700">Not generated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">{{ $activeWindows->count() }}</span>
                                <span class="text-xs text-slate-500"> active / {{ $windows->count() }} known</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $lastSeen?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route($platformRoutePrefix.'licenses.show', $subscription) }}"
                                       class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-bold text-white">Devices & sessions</a>
                                    @if ($license?->status?->value === 'active')
                                        <form method="POST" action="{{ route($platformRoutePrefix.'licenses.revoke', $subscription) }}">
                                            @csrf
                                            <button onclick="return confirm('Revoke the entire license and every active device?')" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700">Revoke all</button>
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
