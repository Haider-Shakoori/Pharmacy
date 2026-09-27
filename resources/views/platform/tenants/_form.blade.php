@php($editing = isset($tenant))

<form method="POST" action="{{ $editing ? route('platform.tenants.update', $tenant) : route('platform.tenants.store') }}" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2">
            <span class="text-sm font-semibold">Pharmacy name</span>
            <input name="name" value="{{ old('name', $tenant->name ?? '') }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block sm:col-span-2">
            <span class="text-sm font-semibold">Tenant slug</span>
            <input name="slug" value="{{ old('slug', $tenant->slug ?? '') }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('slug')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Timezone</span>
            <input name="timezone" value="{{ old('timezone', $tenant->timezone ?? 'Asia/Kabul') }}" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('timezone')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Currency</span>
            <input name="currency" value="{{ old('currency', $tenant->currency ?? 'AFN') }}" maxlength="3" required
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 uppercase">
            @error('currency')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Default language</span>
            <select name="locale" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                @foreach (['en' => 'English', 'fa' => 'Dari', 'ps' => 'Pashto'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('locale', $tenant->locale ?? 'en') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('locale')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
    </div>

    <div class="flex items-center gap-3">
        <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
            {{ $editing ? 'Save pharmacy' : 'Create pharmacy' }}
        </button>
        <a href="{{ route('platform.tenants.index') }}" class="text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
