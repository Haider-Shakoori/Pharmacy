@extends('layouts.platform')

@section('title', 'Subscriptions — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Tenant subscriptions</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">Subscriptions</h2>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_180px_auto]">
        <input name="search" value="{{ $search }}" placeholder="Search pharmacy" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All statuses</option>
            @foreach (['trial', 'pending', 'active', 'suspended', 'expired', 'cancelled'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">Pharmacy</th>
                        <th class="px-4 py-3 text-start">Plan</th>
                        <th class="px-4 py-3 text-start">Status</th>
                        <th class="px-4 py-3 text-start">Health</th>
                        <th class="px-4 py-3 text-start">Period</th>
                        <th class="px-4 py-3 text-end">Action</th>
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
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ $subscription->status->value }}</span></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $subscription->health_state->isOperational() ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $subscription->health_state->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                @if ($subscription->status->value === 'trial')
                                    Trial until {{ $subscription->trial_ends_at?->format('Y-m-d H:i') ?? '—' }}
                                @else
                                    {{ $subscription->starts_at?->format('Y-m-d') ?? '—' }}
                                    →
                                    {{ $subscription->ends_at?->format('Y-m-d') ?? 'Open' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ \App\Support\PlatformRoute::url('subscriptions.edit', $subscription->business->tenant) }}" class="text-sm font-bold text-teal-700">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No subscriptions have been assigned yet.</td></tr>
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
