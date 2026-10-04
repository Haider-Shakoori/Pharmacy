@php($editing = isset($tenant))

<form method="POST" action="{{ $editing ? \App\Support\PlatformRoute::url('tenants.update', $tenant) : \App\Support\PlatformRoute::url('tenants.store') }}" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2"><span class="text-sm font-semibold">Pharmacy name</span><input name="name" value="{{ old('name', $tenant->name ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
        <label class="block sm:col-span-2"><span class="text-sm font-semibold">Pharmacy code / slug</span><input name="slug" value="{{ old('slug', $tenant->slug ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('slug')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-sm font-semibold">Contact person</span><input name="contact_person" value="{{ old('contact_person', $tenant->business?->contact_person ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('contact_person')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-sm font-semibold">Phone / WhatsApp</span><input name="phone_whatsapp" value="{{ old('phone_whatsapp', $tenant->business?->phone_whatsapp ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('phone_whatsapp')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
        <label class="block sm:col-span-2"><span class="text-sm font-semibold">Location</span><input name="location" value="{{ old('location', $tenant->business?->location ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('location')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-sm font-semibold">Timezone</span><input name="timezone" value="{{ old('timezone', $tenant->timezone ?? 'Asia/Kabul') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="block"><span class="text-sm font-semibold">Currency</span><input name="currency" value="{{ old('currency', $tenant->currency ?? 'AFN') }}" maxlength="3" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 uppercase"></label>
        <label class="block"><span class="text-sm font-semibold">Default language</span><select name="locale" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@foreach (['en'=>'English','fa'=>'Dari','ps'=>'Pashto'] as $code=>$label)<option value="{{ $code }}" @selected(old('locale', $tenant->locale ?? 'en') === $code)>{{ $label }}</option>@endforeach</select></label>
    </div>

    @if($editing)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-slate-900">Live server connection & synchronization</p>
                    <p class="mt-1 text-xs leading-5 text-slate-600">
                        Platform-managed desktop policy. When disabled, Darmaltoon continues working locally, cloud push/pull stops, and pending local changes remain queued until this is enabled again.
                    </p>
                </div>
                <label class="inline-flex shrink-0 items-center gap-2 text-sm font-bold text-slate-800">
                    <input type="hidden" name="desktop_cloud_sync_enabled" value="0">
                    <input type="checkbox"
                           name="desktop_cloud_sync_enabled"
                           value="1"
                           @checked(old('desktop_cloud_sync_enabled', $tenant->business?->desktop_cloud_sync_enabled ?? true))
                           class="h-5 w-5 rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                    Enabled
                </label>
            </div>
            @error('desktop_cloud_sync_enabled')<span class="mt-2 block text-xs text-red-600">{{ $message }}</span>@enderror
        </div>
    @endif

    @unless($editing)
        <fieldset class="rounded-xl border border-slate-200 p-4">
            <legend class="px-2 text-sm font-bold">Initial pharmacy owner</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="text-sm font-semibold">Owner name</span><input name="owner_name" value="{{ old('owner_name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('owner_name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold">Owner email</span><input type="email" name="owner_email" value="{{ old('owner_email') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('owner_email')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block sm:col-span-2"><span class="text-sm font-semibold">Temporary password</span><input type="password" name="owner_password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@error('owner_password')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            </div>
        </fieldset>
    @endunless

    <div class="flex items-center gap-3"><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">{{ $editing ? 'Save pharmacy' : 'Create pharmacy + owner' }}</button><a href="{{ \App\Support\PlatformRoute::url('tenants.index') }}" class="text-sm font-semibold text-slate-600">Cancel</a></div>
</form>
