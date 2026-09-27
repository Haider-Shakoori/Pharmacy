@php($selected = old('permission_ids', isset($role) ? $role->permissions->pluck('id')->all() : []))
<div class="grid gap-2 sm:grid-cols-2">
@foreach($permissions as $permission)
    <label class="flex items-start gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
        <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selected, true))>
        <span><strong>{{ $permission->name }}</strong><span class="block text-xs text-slate-500">{{ $permission->code }}</span></span>
    </label>
@endforeach
</div>
