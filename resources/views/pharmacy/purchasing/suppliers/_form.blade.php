@php($editing = isset($supplier))
<form method="POST" action="{{ $editing ? route('pharmacy.suppliers.update', $supplier) : route('pharmacy.suppliers.store') }}" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['code', 'Supplier code', $supplier->code ?? ''],
            ['name', 'Supplier name', $supplier->name ?? ''],
            ['contact_person', 'Contact person', $supplier->contact_person ?? ''],
            ['phone', 'Phone', $supplier->phone ?? ''],
            ['whatsapp', 'WhatsApp', $supplier->whatsapp ?? ''],
            ['email', 'Email', $supplier->email ?? ''],
            ['city', 'City', $supplier->city ?? ''],
            ['province', 'Province', $supplier->province ?? ''],
        ] as [$name, $label, $value])
            <label class="block">
                <span class="text-sm font-semibold">{{ $label }}</span>
                <input name="{{ $name }}" value="{{ old($name, $value) }}" {{ in_array($name, ['code', 'name'], true) ? 'required' : '' }} class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                @error($name)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        @endforeach
        <label class="block">
            <span class="text-sm font-semibold">Payment terms (days)</span>
            <input type="number" min="0" name="payment_terms_days" value="{{ old('payment_terms_days', $supplier->payment_terms_days ?? 0) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>
        <label class="block sm:col-span-2">
            <span class="text-sm font-semibold">Address</span>
            <input name="address" value="{{ old('address', $supplier->address ?? '') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>
        <label class="block sm:col-span-2">
            <span class="text-sm font-semibold">Notes</span>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('notes', $supplier->notes ?? '') }}</textarea>
        </label>
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true))>
        Active supplier
    </label>
    <div class="flex gap-3">
        <button class="rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white">{{ $editing ? 'Save supplier' : 'Create supplier' }}</button>
        <a href="{{ route('pharmacy.suppliers.index') }}" class="px-3 py-3 text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
