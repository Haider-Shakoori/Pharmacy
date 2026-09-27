@php($editing = isset($medicine))

<form method="POST" action="{{ $editing ? route('pharmacy.medicines.update', $medicine) : route('pharmacy.medicines.store') }}" class="space-y-6">
    @csrf
    @if ($editing) @method('PUT') @endif

    <section class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="text-sm font-semibold">Brand name</span>
            <input name="brand_name" value="{{ old('brand_name', $medicine->brand_name ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('brand_name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Generic name</span>
            <input name="generic_name" value="{{ old('generic_name', $medicine->generic_name ?? '') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Medicine code</span>
            <input name="medicine_code" value="{{ old('medicine_code', $medicine->medicine_code ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            @error('medicine_code')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Barcode (optional)</span>
            <input name="barcode" value="{{ old('barcode', $medicine->barcode ?? '') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Strength</span>
            <input name="strength" value="{{ old('strength', $medicine->strength ?? '') }}" placeholder="e.g. 500 mg" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Dosage form</span>
            <input name="dosage_form" value="{{ old('dosage_form', $medicine->dosage_form ?? '') }}" placeholder="Tablet, capsule, syrup..." class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Category</span>
            <select name="medicine_category_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                <option value="">No category</option>
                @foreach ($categories as $item)
                    <option value="{{ $item->id }}" @selected(old('medicine_category_id', $medicine->medicine_category_id ?? '') === $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-semibold">Manufacturer</span>
            <select name="manufacturer_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                <option value="">No manufacturer</option>
                @foreach ($manufacturers as $item)
                    <option value="{{ $item->id }}" @selected(old('manufacturer_id', $medicine->manufacturer_id ?? '') === $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </label>
    </section>

    <section class="rounded-xl bg-slate-50 p-4">
        <h2 class="font-bold">Units & replenishment</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="text-sm font-semibold">Purchase unit</span>
                <input name="purchase_unit" value="{{ old('purchase_unit', $medicine->purchase_unit ?? 'pack') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            </label>
            <label class="block">
                <span class="text-sm font-semibold">Sale unit</span>
                <input name="sale_unit" value="{{ old('sale_unit', $medicine->sale_unit ?? 'unit') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            </label>
            <label class="block">
                <span class="text-sm font-semibold">Units per purchase unit</span>
                <input type="number" step="0.0001" min="0.0001" name="units_per_purchase_unit" value="{{ old('units_per_purchase_unit', $medicine->units_per_purchase_unit ?? 1) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            </label>
            <label class="block">
                <span class="text-sm font-semibold">Reorder level</span>
                <input type="number" step="0.0001" min="0" name="reorder_level" value="{{ old('reorder_level', $medicine->reorder_level ?? 0) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            </label>
        </div>
    </section>

    @php
        $toggles = [
            'prescription_required' => 'Prescription required',
            'batch_tracking_required' => 'Require batch / lot tracking',
            'expiry_tracking_required' => 'Require expiry tracking',
            'is_active' => 'Active medicine',
        ];
    @endphp

    <section class="grid gap-2 sm:grid-cols-2">
        @foreach ($toggles as $key => $label)
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-medium">
                <input type="hidden" name="{{ $key }}" value="0">
                <input type="checkbox" name="{{ $key }}" value="1"
                       @checked(old($key, $editing ? $medicine->{$key} : in_array($key, ['batch_tracking_required', 'expiry_tracking_required', 'is_active'], true)))>
                {{ $label }}
            </label>
        @endforeach
    </section>

    <label class="block">
        <span class="text-sm font-semibold">Notes</span>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('notes', $medicine->notes ?? '') }}</textarea>
    </label>

    <div class="flex items-center gap-3">
        <button class="rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white">{{ $editing ? 'Save medicine' : 'Create medicine' }}</button>
        <a href="{{ route('pharmacy.medicines.index') }}" class="text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
