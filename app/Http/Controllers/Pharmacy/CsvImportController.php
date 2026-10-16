<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\ProductBatch;
use App\Models\StockLocation;
use App\Services\Inventory\StockMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CsvImportController extends Controller
{
    private const MEDICINE_COLUMNS = ['medicine_code', 'brand_name', 'generic_name', 'strength', 'dosage_form', 'category', 'manufacturer', 'manufacturer_country', 'barcode', 'purchase_unit', 'sale_unit', 'units_per_purchase_unit', 'reorder_level', 'prescription_required', 'batch_tracking_required', 'expiry_tracking_required', 'is_active', 'notes'];
    private const STOCK_COLUMNS = ['medicine_code', 'batch_number', 'expiry_date', 'manufactured_date', 'quantity', 'purchase_cost', 'sale_price', 'notes'];

    public function form(string $kind): View
    {
        $this->assertKind($kind);

        return view('pharmacy.csv-import', [
            'kind' => $kind,
            'locations' => $kind === 'inventory' ? StockLocation::query()->where('is_active', true)->orderBy('name')->get() : collect(),
        ]);
    }

    public function preview(Request $request, string $kind): RedirectResponse
    {
        $this->assertKind($kind);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
            'stock_location_id' => [$kind === 'inventory' ? 'required' : 'nullable', 'nullable', 'exists:stock_locations,id'],
        ]);

        $file = $request->file('file')->openFile('r');
        $header = $file->fgetcsv();
        $expected = $kind === 'medicines' ? self::MEDICINE_COLUMNS : self::STOCK_COLUMNS;
        if (! is_array($header)) {
            throw ValidationException::withMessages(['file' => 'CSV is empty.']);
        }
        $header = array_map(fn ($value) => strtolower(trim((string) $value, "\xEF\xBB\xBF \t\r\n")), $header);
        if (count($header) !== count(array_unique($header)) || array_diff($expected, $header)) {
            throw ValidationException::withMessages(['file' => 'CSV headers must match the downloaded template.']);
        }
        $rows = [];
        $errors = [];
        $seen = [];
        for ($line = 2; ! $file->eof(); $line++) {
            $values = $file->fgetcsv();
            if (! is_array($values) || $values === [null]) {
                continue;
            }
            if (count($values) !== count($header) || count($rows) >= 200) {
                throw ValidationException::withMessages(['file' => 'Invalid column count or more than 200 rows.']);
            }
            $data = array_combine($header, array_map(fn ($v) => trim((string) $v), $values));
            $code = $data['medicine_code'] ?? '';
            if ($code === '' || strlen($code) > 80) {
                $errors[] = "Row {$line}: valid medicine_code required.";
            } elseif ($kind === 'medicines') {
                if (isset($seen[strtolower($code)]) || Medicine::query()->where('medicine_code', $code)->exists()) {
                    $errors[] = "Row {$line}: duplicate medicine code {$code}.";
                }
                $seen[strtolower($code)] = true;
                if ($data['brand_name'] === '' || strlen($data['brand_name']) > 180) {
                    $errors[] = "Row {$line}: brand_name required (max 180).";
                }
                foreach (['units_per_purchase_unit', 'reorder_level'] as $field) {
                    if ($data[$field] !== '' && (! is_numeric($data[$field]) || (float) $data[$field] < ($field === 'reorder_level' ? 0 : 0.0001))) {
                        $errors[] = "Row {$line}: invalid {$field}.";
                    }
                }
                foreach (['prescription_required', 'batch_tracking_required', 'expiry_tracking_required', 'is_active'] as $field) {
                    if ($data[$field] !== '' && ! in_array(strtolower($data[$field]), ['1', '0', 'true', 'false', 'yes', 'no'], true)) {
                        $errors[] = "Row {$line}: invalid {$field}.";
                    }
                }
                if ($data['barcode'] !== '' && (isset($seen['barcode:'.$data['barcode']]) || Medicine::query()->where('barcode', $data['barcode'])->exists())) {
                    $errors[] = "Row {$line}: duplicate barcode.";
                }
                if ($data['barcode'] !== '') {
                    $seen['barcode:'.$data['barcode']] = true;
                }
            } else {
                if (! Medicine::query()->where('medicine_code', $code)->exists()) {
                    $errors[] = "Row {$line}: medicine {$code} not found. Import medicines first.";
                }
                if (! is_numeric($data['quantity']) || (float) $data['quantity'] <= 0) {
                    $errors[] = "Row {$line}: positive quantity required.";
                }
                foreach (['purchase_cost', 'sale_price'] as $field) {
                    if ($data[$field] !== '' && (! is_numeric($data[$field]) || (float) $data[$field] < 0)) {
                        $errors[] = "Row {$line}: invalid {$field}.";
                    }
                }
                foreach (['expiry_date', 'manufactured_date'] as $field) {
                    if ($data[$field] !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$field]) || ! strtotime($data[$field]))) {
                        $errors[] = "Row {$line}: {$field} must be YYYY-MM-DD.";
                    }
                }
            }
            $rows[] = $data;
        }

        if ($rows === []) {
            $errors[] = 'No data rows found.';
        }
        // All-or-nothing imports: do not mutate tenant stock when any row is invalid.
        $request->session()->put('csv_import_preview', [
            'kind' => $kind, 'rows' => $rows, 'errors' => $errors,
            'location' => $request->input('stock_location_id'),
            'tenant' => tenant('id'), 'nonce' => (string) Str::uuid(),
        ]);

        return redirect()->route('pharmacy.csv-import.form', $kind);
    }

    public function commit(Request $request, string $kind, StockMovementService $movements): RedirectResponse
    {
        $this->assertKind($kind);
        $preview = $request->session()->get('csv_import_preview');
        if (! is_array($preview) || $preview['kind'] !== $kind || $preview['tenant'] !== tenant('id') || $preview['errors'] !== []) {
            throw ValidationException::withMessages(['file' => 'Upload a valid CSV and review the preview before importing.']);
        }
        $request->session()->forget('csv_import_preview');

        DB::transaction(function () use ($kind, $preview, $request, $movements): void {
            foreach ($preview['rows'] as $i => $row) {
                if ($kind === 'medicines') {
                    $category = $row['category'] !== '' ? MedicineCategory::query()->firstOrCreate(['name' => $row['category']], ['is_active' => true]) : null;
                    $manufacturer = $row['manufacturer'] !== '' ? Manufacturer::query()->firstOrCreate(['name' => $row['manufacturer']], ['country' => $row['manufacturer_country'] ?: null, 'is_active' => true]) : null;
                    $attrs = [
                        'medicine_code' => $row['medicine_code'], 'brand_name' => $row['brand_name'],
                        'medicine_category_id' => $category?->id, 'manufacturer_id' => $manufacturer?->id,
                    ];
                    foreach (['generic_name', 'strength', 'dosage_form', 'barcode', 'notes'] as $field) {
                        $attrs[$field] = $row[$field] ?: null;
                    }
                    foreach (['purchase_unit' => 'pack', 'sale_unit' => 'unit', 'units_per_purchase_unit' => '1', 'reorder_level' => '0'] as $field => $default) {
                        $attrs[$field] = $row[$field] !== '' ? $row[$field] : $default;
                    }
                    foreach (['prescription_required' => false, 'batch_tracking_required' => true, 'expiry_tracking_required' => true, 'is_active' => true] as $field => $default) {
                        $attrs[$field] = $row[$field] === '' ? $default : in_array(strtolower($row[$field]), ['1', 'true', 'yes'], true);
                    }
                    Medicine::query()->create($attrs);
                } else {
                    $medicine = Medicine::query()->where('medicine_code', $row['medicine_code'])->firstOrFail();
                    $location = StockLocation::query()->where('is_active', true)->findOrFail($preview['location']);
                    $key = $row['batch_number'] !== '' ? $row['batch_number'] : 'CSV-'.substr(hash('sha256', $preview['nonce'].'-'.$i), 0, 24);
                    if (ProductBatch::query()->where('medicine_id', $medicine->id)->where('stock_location_id', $location->id)->where('batch_key', $key)->exists()) {
                        throw ValidationException::withMessages(['file' => "Batch {$key} already exists."]);
                    }
                    $batch = ProductBatch::query()->create([
                        'medicine_id' => $medicine->id, 'branch_id' => $location->branch_id,
                        'stock_location_id' => $location->id, 'batch_number' => $row['batch_number'] ?: null,
                        'batch_key' => $key, 'manufactured_at' => $row['manufactured_date'] ?: null,
                        'expires_at' => $row['expiry_date'] ?: null,
                        'status' => 'active', 'received_quantity' => $row['quantity'], 'available_quantity' => '0',
                        'purchase_cost' => $row['purchase_cost'] !== '' ? $row['purchase_cost'] : '0',
                        'sale_price' => $row['sale_price'] !== '' ? $row['sale_price'] : null,
                    ]);
                    $movements->record($batch, $row['quantity'], 'receipt', 'csv_opening_stock', $preview['nonce'], "csv:{$preview['nonce']}:{$i}", (int) $request->user()->id, (string) $i, $row['notes'] ?: 'CSV opening stock', $row['purchase_cost'] ?: '0');
                }
            }
        });

        return redirect()->route('pharmacy.csv-import.form', $kind)->with('success', count($preview['rows']).' rows imported.');
    }

    private function assertKind(string $kind): void
    {
        abort_unless(in_array($kind, ['medicines', 'inventory'], true), 404);
        abort_unless(request()->user()?->hasPermission($kind === 'medicines' ? 'medicines.manage' : 'inventory.manage'), 403);
    }
}
