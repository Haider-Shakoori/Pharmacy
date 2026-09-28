@extends('layouts.platform')

@section('title', 'Licenses — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Activation security</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">Licenses</h2>
        <p class="mt-2 text-sm text-slate-500">Keys are stored only as hashes. Regenerating a key invalidates existing device activations.</p>
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
                        <th class="px-4 py-3 text-start">Version</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subscriptions as $subscription)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $subscription->business->pharmacy_name }}</p>
                                <p class="text-xs text-slate-500">{{ $subscription->business->slug }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $subscription->plan->name }}</td>
                            <td class="px-4 py-3">
                                @if ($subscription->license)
                                    <p class="font-mono text-xs">{{ $subscription->license->key_hint }}</p>
                                    <p class="mt-1 text-xs font-semibold {{ $subscription->license->status->value === 'active' ? 'text-emerald-700' : 'text-red-700' }}">{{ $subscription->license->status->value }}</p>
                                @else
                                    <span class="text-xs text-amber-700">Not generated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $subscription->license?->version ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('platform.licenses.regenerate', $subscription) }}">
                                        @csrf
                                        <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold">{{ $subscription->license ? 'Regenerate' : 'Generate' }}</button>
                                    </form>
                                    @if ($subscription->license?->status?->value === 'active')
                                        <form method="POST" action="{{ route('platform.licenses.revoke', $subscription) }}">
                                            @csrf
                                            <button class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700">Revoke</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No subscriptions exist yet.</td></tr>
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
