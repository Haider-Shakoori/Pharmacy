@extends('layouts.pharmacy')

@section('title', 'Medicines — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Medicine catalog</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Medicines</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('pharmacy.medicine-references.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Categories & Manufacturers</a>
            <a href="{{ route('pharmacy.medicines.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add medicine</a>
        </div>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_220px_160px_auto]">
        <input name="search" value="{{ $search }}" placeholder="Brand, generic, code, barcode or strength"
               class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <select name="category" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All categories</option>
            @foreach ($categories as $item)
                <option value="{{ $item->id }}" @selected($category === $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All statuses</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">Medicine</th>
                        <th class="px-4 py-3 text-start">Code</th>
                        <th class="px-4 py-3 text-start">Form / Strength</th>
                        <th class="px-4 py-3 text-start">Category</th>
                        <th class="px-4 py-3 text-start">Pack</th>
                        <th class="px-4 py-3 text-start">Tracking</th>
                        <th class="px-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($medicines as $medicine)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $medicine->brand_name }}</p>
                                <p class="text-xs text-slate-500">{{ $medicine->generic_name ?: '—' }} {{ $medicine->manufacturer ? '· '.$medicine->manufacturer->name : '' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ $medicine->medicine_code }}</p>
                                <p class="text-xs text-slate-400">{{ $medicine->barcode ?: 'No barcode' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $medicine->dosage_form ?: '—' }} {{ $medicine->strength ? '· '.$medicine->strength : '' }}</td>
                            <td class="px-4 py-3">{{ $medicine->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ rtrim(rtrim($medicine->units_per_purchase_unit, '0'), '.') }} {{ $medicine->sale_unit }} / {{ $medicine->purchase_unit }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if ($medicine->batch_tracking_required)<span class="rounded bg-slate-100 px-2 py-1">Batch</span>@endif
                                @if ($medicine->expiry_tracking_required)<span class="rounded bg-amber-50 px-2 py-1 text-amber-800">Expiry</span>@endif
                            </td>
                            <td class="px-4 py-3 text-end"><a class="font-semibold text-teal-700" href="{{ route('pharmacy.medicines.edit', $medicine) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No medicines found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($medicines->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $medicines->links() }}</div>@endif
    </div>
</div>
@endsection
