<div class="grid gap-4 sm:grid-cols-2">
    <label class="block sm:col-span-2">
        <span class="text-sm font-semibold">Customer name</span>
        <input name="name" value="{{ old('name', $customer->name ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        @error('name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
    </label>
    <label class="block">
        <span class="text-sm font-semibold">Phone</span>
        <input name="phone" value="{{ old('phone', $customer->phone ?? '') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
    </label>
    <label class="block">
        <span class="text-sm font-semibold">Email</span>
        <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
    </label>
    <label class="block">
        <span class="text-sm font-semibold">Credit limit (AFN)</span>
        <input type="number" min="0" step="0.01" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
    </label>
    <label class="flex items-center gap-2 pt-7 text-sm font-semibold">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true))>
        Active customer
    </label>
    <label class="block sm:col-span-2">
        <span class="text-sm font-semibold">Notes</span>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('notes', $customer->notes ?? '') }}</textarea>
    </label>
</div>
