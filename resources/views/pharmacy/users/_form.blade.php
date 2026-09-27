@php($editing = isset($user))

<form method="POST" action="{{ $editing ? route('pharmacy.users.update', $user) : route('pharmacy.users.store') }}" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="text-sm font-semibold">Name</span>
            <input name="name" value="{{ old('name', $user->name ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Email</span>
            <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('email')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Password {{ $editing ? '(leave blank to keep current)' : '' }}</span>
            <input type="password" name="password" {{ $editing ? '' : 'required' }} class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('password')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Preferred language</span>
            <select name="preferred_locale" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                @foreach (['en' => 'English', 'fa' => 'Dari', 'ps' => 'Pashto'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('preferred_locale', $user->preferred_locale ?? 'en') === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <fieldset>
        <legend class="text-sm font-semibold">Roles</legend>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach ($roles as $role)
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                           @checked(in_array($role->id, old('roles', isset($user) ? $user->roles->pluck('id')->all() : [])))>
                    <span>{{ $role->name }}</span>
                </label>
            @endforeach
        </div>
        @error('roles')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
    </fieldset>

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $user->is_active ?? true))>
        Active account
    </label>

    <div class="flex items-center gap-3">
        <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">{{ $editing ? 'Save user' : 'Create user' }}</button>
        <a href="{{ route('pharmacy.users.index') }}" class="text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
