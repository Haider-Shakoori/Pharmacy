@extends('layouts.platform')

@section('title', 'Subscription Plans — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Commercial configuration</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Subscription plans</h2>
        </div>
        <a href="{{ route('platform.plans.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
            Add plan
        </a>
    </div>

    <form method="GET" class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <input name="search" value="{{ $search }}" placeholder="Search plan name or code"
               class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Search</button>
    </form>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($plans as $plan)
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-teal-700">{{ $plan->code }}</p>
                        <h3 class="mt-1 text-xl font-bold">{{ $plan->name }}</h3>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $plan->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <p class="mt-4 text-2xl font-bold">{{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}</p>
                <p class="text-sm text-slate-500">{{ ucfirst($plan->billing_period->value) }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">Users</dt><dd class="font-semibold">{{ $plan->max_users ?? 'Unlimited' }}</dd></div>
                    <div><dt class="text-slate-500">Android devices</dt><dd class="font-semibold">{{ $plan->max_android_devices ?? 'Unlimited' }}</dd></div>
                    <div><dt class="text-slate-500">Branches</dt><dd class="font-semibold">{{ $plan->max_branches }}</dd></div>
                    <div><dt class="text-slate-500">Offline grace</dt><dd class="font-semibold">{{ $plan->offline_grace_days }} days</dd></div>
                </dl>

                <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                    <span class="text-xs text-slate-500">{{ $plan->subscriptions_count }} subscriptions</span>
                    <a href="{{ route('platform.plans.edit', $plan) }}" class="text-sm font-bold text-teal-700">Edit plan</a>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                No subscription plans found.
            </div>
        @endforelse
    </div>

    @if ($plans->hasPages())
        <div>{{ $plans->links() }}</div>
    @endif
</div>
@endsection
