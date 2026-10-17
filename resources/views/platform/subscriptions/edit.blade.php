@extends('layouts.platform')

@section('title', 'Subscription: '.$tenant->name.' — Platform Admin')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Tenant subscription</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">{{ $tenant->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $tenant->slug }}</p>
    </div>

    @if ($subscription?->status?->value === 'trial')
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Trial ends {{ $subscription->trial_ends_at?->format('Y-m-d H:i') }}. Saving this subscription as a paid/non-trial status clears the trial timestamps.
        </div>
    @endif

    @if ($subscription?->license)
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">License</p>
                    <p class="mt-1 font-mono text-sm font-bold">{{ $subscription->license->key_hint }}</p>
                    <p class="mt-1 text-xs text-slate-500">Version {{ $subscription->license->version }} · {{ $subscription->license->status->value }} · {{ $subscription->license->activations->whereNull('revoked_at')->count() }} active devices</p>
                </div>
                <a href="{{ route('platform.licenses.index', ['search' => $tenant->slug]) }}" class="text-sm font-bold text-teal-700">Manage license</a>
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-teal-200 bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-teal-700">Darmaltoon Standard / Launch pricing</p>
                <h3 class="mt-1 text-xl font-black">Verified payments &amp; founding customers</h3>
            </div>
            <div class="rounded-lg bg-teal-50 px-4 py-2 text-sm font-bold text-teal-900">
                {{ $commercialOffer['remaining'] }} of 10 founder places remain
            </div>
        </div>
        <p class="mt-2 text-sm text-slate-600">
            First {{ \App\Support\Commercial\DarmaltoonLaunchPricing::FOUNDING_LIMIT }} <strong>paid</strong> pharmacies:
            AFN 5,000 first year. Later customers: AFN 7,000 first year.
            Renewal: AFN 5,000/year for both. Trials and demo requests never consume places.
        </p>
        <p class="mt-1 text-xs text-amber-700">
            Do not confirm payment before independently verifying receipt. Founder places are allocated atomically at confirmation,
            not reserved by this screen. Existing promised prices require individual review.
        </p>
        @if($commercialEnrollment)
            <div class="mt-4 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm">
                <strong>{{ ucfirst($commercialEnrollment->offer_tier) }} customer
                    @if($commercialEnrollment->founder_number) #{{ $commercialEnrollment->founder_number }} @endif
                </strong>
                <p class="mt-1">First year: AFN {{ number_format((float) $commercialEnrollment->first_year_amount, 0) }}
                    · Renewal: AFN {{ number_format((float) $commercialEnrollment->renewal_amount, 0) }}/year</p>
                <p class="mt-1 text-xs text-slate-600">The agreed renewal rate is saved per pharmacy; changing marketing pricing never updates this record.</p>
            </div>
        @endif
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @if(!$commercialEnrollment)
                <form method="POST" action="{{ route('platform.subscriptions.commercial.initial', $tenant) }}"
                    class="space-y-3 rounded-xl border border-slate-200 p-4">
                    @csrf
                    <h4 class="font-bold">Confirm first payment</h4>
                    <p class="text-sm text-slate-600">
                        Indicative price: <strong>AFN {{ number_format((float) $commercialOffer['first_year_price'], 0) }}</strong>
                        · verified once the payment is recorded.
                    </p>
                    <label class="block text-sm font-semibold">Amount received (AFN)
                        <input required type="number" min="1" step="0.01" name="amount"
                            value="{{ old('amount', $commercialOffer['first_year_price']) }}"
                            class="mt-1 block w-full rounded-lg border-slate-300">
                    </label>
                    <label class="block text-sm font-semibold">External payment reference
                        <input required maxlength="150" name="payment_reference" value="{{ old('payment_reference') }}"
                            class="mt-1 block w-full rounded-lg border-slate-300" placeholder="Bank/cash receipt number">
                    </label>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="payment_verified" value="1" required>
                        I independently confirmed payment and its reference.</label>
                    <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                        Record payment &amp; activate annual license</button>
                </form>
            @else
                <form method="POST" action="{{ route('platform.subscriptions.commercial.renew', $tenant) }}"
                    class="space-y-3 rounded-xl border border-slate-200 p-4">
                    @csrf
                    <h4 class="font-bold">Confirm annual renewal</h4>
                    <label class="block text-sm font-semibold">Amount received (AFN)
                        <input required type="number" min="1" step="0.01" name="amount"
                            value="{{ old('amount', $commercialEnrollment->renewal_amount) }}"
                            class="mt-1 block w-full rounded-lg border-slate-300">
                    </label>
                    <label class="block text-sm font-semibold">New payment reference
                        <input required maxlength="150" name="payment_reference" value="{{ old('payment_reference') }}"
                            class="mt-1 block w-full rounded-lg border-slate-300">
                    </label>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="payment_verified" value="1" required>
                        I independently confirmed this renewal payment.</label>
                    <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Record paid annual renewal</button>
                </form>
            @endif
            <div class="rounded-xl border border-slate-200 p-4">
                <h4 class="font-bold">Payment history</h4>
                @forelse(($commercialEnrollment?->receipts ?? collect()) as $receipt)
                    <div class="border-b border-slate-100 py-3 text-sm last:border-b-0">
                        <strong>{{ ucfirst($receipt->kind) }} · AFN {{ number_format((float) $receipt->amount, 0) }}</strong>
                        <p class="text-xs text-slate-600">Receipt: {{ $receipt->payment_reference }}</p>
                        <p class="text-xs text-slate-600">Recorded {{ $receipt->paid_at?->format('Y-m-d H:i') }}
                            · coverage until {{ $receipt->coverage_ends_at?->format('Y-m-d') }}</p>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-500">No verified launch payments have been recorded for this pharmacy.</p>
                @endforelse
            </div>
        </div>
    </section>

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
                    @foreach (['trial', 'pending', 'active', 'suspended', 'expired', 'cancelled'] as $option)
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
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $subscription?->starts_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            </label>

            <label class="block">
                <span class="text-sm font-semibold">Ends at</span>
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $subscription?->ends_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                @error('ends_at')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block sm:col-span-2">
                <span class="text-sm font-semibold">Notes</span>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('notes', $subscription?->notes) }}</textarea>
            </label>
        </div>

        <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Save subscription</button>
    </form>
</div>
@endsection
