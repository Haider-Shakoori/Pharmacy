@extends('layouts.pharmacy')

@section('title', ($kind === 'medicines' ? 'Medicine' : 'Inventory').' CSV Import')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div>
        <h1 class="text-3xl font-bold">{{ $kind === 'medicines' ? 'Import medicines from CSV' : 'Import opening inventory from CSV' }}</h1>
        <p class="mt-2 text-sm text-slate-600">Download the same CSV columns as PharmaDesktop, upload your completed file and preview it before saving. Imports are all-or-nothing.</p>
    </div>

    @if (session('success'))
        <div role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <a class="inline-flex rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 font-semibold text-blue-800" href="{{ $kind === 'medicines' ? route('pharmacy.medicines.csv.template') : route('pharmacy.inventory.csv.template') }}">
            Download {{ $kind === 'medicines' ? 'medicine' : 'opening stock' }} CSV template
        </a>
        <form class="mt-5 space-y-4" method="POST" enctype="multipart/form-data" action="{{ route('pharmacy.csv-import.preview', $kind) }}">
            @csrf
            <label class="block text-sm font-semibold text-slate-700">Choose completed CSV (max 1 MB, 200 rows)
                <input class="mt-2 block w-full rounded-lg border border-slate-300 p-3" type="file" name="file" accept=".csv,text/csv,text/plain" required>
            </label>
            @if ($kind === 'inventory')
                <label class="block text-sm font-semibold text-slate-700">Opening stock location
                    <select name="stock_location_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3">
                        <option value="">Select location</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->code }})</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="rounded-lg bg-blue-700 px-5 py-2.5 font-semibold text-white">Validate and preview</button>
        </form>
    </div>

    @php($preview = session('csv_import_preview'))
    @if (is_array($preview) && $preview['kind'] === $kind && $preview['tenant'] === tenant('id'))
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-xl font-bold">Import preview — {{ count($preview['rows']) }} rows</h2>
            @if ($preview['errors'])
                <div role="alert" class="my-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-bold">Correct the following errors and upload again:</p>
                    @foreach ($preview['errors'] as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @else
                <p class="my-3 text-sm text-green-700">Validated. No data has been imported yet.</p>
                <form method="POST" action="{{ route('pharmacy.csv-import.commit', $kind) }}">
                    @csrf
                    <button class="rounded-lg bg-green-700 px-5 py-2.5 font-bold text-white" onclick="return confirm('Import {{ count($preview['rows']) }} rows into this pharmacy?')">Confirm import</button>
                </form>
            @endif
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead><tr>@foreach (array_keys($preview['rows'][0] ?? []) as $column)<th class="border-b p-2">{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach (array_slice($preview['rows'], 0, 20) as $row)
                        <tr class="border-b">@foreach ($row as $value)<td class="p-2">{{ $value }}</td>@endforeach</tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($preview['rows']) > 20)<p class="mt-2 text-xs text-slate-600">Showing first 20 rows.</p>@endif
        </section>
    @endif
</div>
@endsection
