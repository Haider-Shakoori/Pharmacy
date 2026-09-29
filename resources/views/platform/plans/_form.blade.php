@php
    $editing = isset($plan);
    $selectedFeatures = old('features', $plan->features ?? []);
    $featureOptions = [
        'advanced_reports' => 'Advanced reports',
        'multi_branch' => 'Multi-branch controls',
        'api_access' => 'API access',
        'priority_support' => 'Priority support',
        'advanced_analytics' => 'Advanced analytics',
        'offline_windows' => 'Offline Windows installation',
    ];
@endphp

<form method="POST" action="{{ $editing ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="text-sm font-semibold">Plan name</span>
            <input name="name" value="{{ old('name', $plan->name ?? '') }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Code</span>
            <input name="code" value="{{ old('code', $plan->code ?? '') }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 uppercase">
            @error('code')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block sm:col-span-2">
            <span class="text-sm font-semibold">Description</span>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('description', $plan->description ?? '') }}</textarea>
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Price</span>
            <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $plan->price ?? '0.00') }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('price')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Currency</span>
            <input name="currency" maxlength="3" value="{{ old('currency', $plan->currency ?? 'AFN') }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 uppercase">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Billing period</span>
            <select name="billing_period" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                @foreach (['monthly', 'quarterly', 'yearly'] as $period)
                    <option value="{{ $period }}" @selected(old('billing_period', isset($plan) ? $plan->billing_period->value : 'monthly') === $period)>
                        {{ ucfirst($period) }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Sort order</span>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Maximum users</span>
            <input type="number" min="1" name="max_users" value="{{ old('max_users', $plan->max_users ?? '') }}" placeholder="Unlimited"
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Maximum Android devices</span>
            <input type="number" min="1" name="max_android_devices" value="{{ old('max_android_devices', $plan->max_android_devices ?? '') }}" placeholder="Unlimited"
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Maximum branches</span>
            <input type="number" min="1" name="max_branches" value="{{ old('max_branches', $plan->max_branches ?? 1) }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Offline license grace days</span>
            <input type="number" min="0" max="365" name="offline_grace_days" value="{{ old('offline_grace_days', $plan->offline_grace_days ?? 7) }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>
    </div>

    <fieldset>
        <legend class="text-sm font-semibold">Optional commercial features</legend>
        <p class="mt-1 text-xs text-slate-500">Core pharmacy controls such as Daily Closing are included for every pharmacy and are not plan-gated.</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($featureOptions as $key => $label)
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, $selectedFeatures, true))>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <label class="flex items-center gap-2 text-sm font-semibold">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))>
        Plan is active and can be assigned
    </label>

    <div class="flex items-center gap-3">
        <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
            {{ $editing ? 'Save plan' : 'Create plan' }}
        </button>
        <a href="{{ route('platform.plans.index') }}" class="text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
