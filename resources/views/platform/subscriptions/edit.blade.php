@extends('layouts.platform')

@section('title', 'Subscription: '.$tenant->name.' — Platform Admin')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Tenant subscription</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">{{ $tenant->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $tenant->slug }}</p>
    </div>

    <form method="POST" action="{{ route('platform.subscriptions.update', $tenant) }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        @csrf
        @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="text-sm font-semibold">Subscription plan</span>
                <select name="plan_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    <option value="">Select plan</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('plan_id', $subscription?->plan_id) === $plan->id)>
                            {{ $plan->name }} — {{ $plan->currency }} {{ number_format((float) $plan->price, 2) }} / {{ $plan->billing_period->value }}
                        </option>
                    @endforeach
                </select>
                @error('plan_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="text-sm font-semibold">Status</span>
                <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    @foreach (['pending', 'active', 'suspended', 'expired', 'cancelled'] as $option)
                        <option value="{{ $option }}" @selected(old('status', $subscription?->status?->value ?? 'pending') === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex items-end gap-2 pb-3 text-sm font-semibold">
                <input type="hidden" name="auto_renew" value="0">
                <input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew', $subscription?->auto_renew ?? false))>
                Auto-renew
            </label>

            <label class="block">
                <span class="text-sm font-semibold">Starts at</span>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $subscription?->starts_at?->format('Y-m-d\TH:i')) }}"
                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            </label>

            <label class="block">
                <span class="text-sm font-semibold">Ends at</span>
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $subscription?->ends_at?->format('Y-m-d\TH:i')) }}"
                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                @error('ends_at')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block sm:col-span-2">
                <span class="text-sm font-semibold">Notes</span>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('notes', $subscription?->notes) }}</textarea>
            </label>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            License keys and offline activation leases are intentionally not created here yet; they are added in Batch 5.
        </div>

        <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Save subscription</button>
    </form>
</div>
@endsection
