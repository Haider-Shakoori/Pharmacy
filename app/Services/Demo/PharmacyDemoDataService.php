<?php

namespace App\Services\Demo;

use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\PharmacySetting;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Accounting\ExpenseService;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Safe\SafeService;
use App\Services\Sales\PosSaleService;
use Illuminate\Support\Collection;

class PharmacyDemoDataService
{
    public function __construct(
        private readonly RbacProvisioner $rbac,
        private readonly InventoryProvisioner $inventory,
        private readonly AccountingProvisioner $accounting,
        private readonly PosSaleService $sales,
        private readonly ExpenseService $expenses,
        private readonly SafeService $safe,
    ) {}

    public function seed(Tenant $tenant): array
    {
        $this->rbac->ensureForTenant($tenant);
        $this->inventory->ensureDefaults($tenant);
        $this->accounting->ensureDefaults($tenant);

        return $tenant->run(function () use ($tenant): array {
            $owner = User::query()->where('is_active', true)->orderBy('id')->firstOrFail();
            $location = StockLocation::query()->where('is_default', true)->where('is_active', true)->with('branch')->firstOrFail();

            $categories = $this->categories();
            $manufacturers = $this->manufacturers();
            $suppliers = $this->suppliers();
            $customers = $this->customers();
            $medicines = $this->medicines($categories, $manufacturers);
            $batches = $this->batches($medicines, $suppliers, $location, $owner);
            $orders = $this->purchaseOrders($medicines, $suppliers, $owner);
            $sales = $this->sales($medicines, $customers, $location, $owner);
            $expenses = $this->expenses($location, $owner);
            $safeMovements = $this->safe($owner);
            $this->cashierShift($location, $owner);
            $this->settings($tenant);

            return [
                'categories' => $categories->count(),
                'manufacturers' => $manufacturers->count(),
                'suppliers' => $suppliers->count(),
                'customers' => $customers->count(),
                'medicines' => $medicines->count(),
                'batches' => $batches,
                'purchase_orders' => $orders,
                'sales' => $sales,
                'expenses' => $expenses,
                'safe_movements' => $safeMovements,
            ];
        });
    }

    private function categories(): Collection
    {
        return collect([
            'Pain & Fever', 'Antibiotics', 'Gastrointestinal', 'Allergy & Respiratory',
            'Vitamins & Supplements', 'Cardiovascular', 'Diabetes', 'Dermatology',
        ])->mapWithKeys(function (string $name): array {
            $model = MedicineCategory::query()->firstOrCreate(['name' => $name], ['is_active' => true]);

            return [$name => $model];
        });
    }

    private function manufacturers(): Collection
    {
        return collect([
            ['Getz Pharma', 'Pakistan'], ['Hilton Pharma', 'Pakistan'], ['GSK', 'United Kingdom'],
            ['Abbott', 'United States'], ['Sanofi', 'France'], ['Demo Afghan Pharma', 'Afghanistan'],
        ])->mapWithKeys(function (array $row): array {
            $model = Manufacturer::query()->firstOrCreate(['name' => $row[0]], ['country' => $row[1], 'is_active' => true]);

            return [$row[0] => $model];
        });
    }

    private function suppliers(): Collection
    {
        $rows = [
            ['SUP-DEMO-001', 'Kabul Medical Distribution', 'Ahmad Farid', '0700123456', 'Kabul'],
            ['SUP-DEMO-002', 'Aryana Pharma Supply', 'Samiullah', '0799123456', 'Kabul'],
            ['SUP-DEMO-003', 'Afghan Health Wholesalers', 'Mariam Rahimi', '0788123456', 'Herat'],
        ];

        return collect($rows)->mapWithKeys(function (array $row): array {
            $model = Supplier::query()->firstOrCreate(['code' => $row[0]], [
                'name' => $row[1], 'contact_person' => $row[2], 'phone' => $row[3],
                'whatsapp' => $row[3], 'city' => $row[4], 'province' => $row[4],
                'payment_terms_days' => 30, 'is_active' => true, 'notes' => 'DEMO supplier for operational testing.',
            ]);

            return [$row[0] => $model];
        });
    }

    private function customers(): Collection
    {
        $rows = [
            ['Ahmad Wali', '0701112233', 5000], ['Fatima Zahra', '0793112233', 3000],
            ['Mohammad Nabi', '0782112233', 10000], ['Laila Rahimi', '0771112233', 2500],
            ['Sayed Karim', '0708112233', 8000], ['Amina Safi', '0799112233', 4000],
            ['Farid Ahmad', '0788112233', 6000], ['Maryam Noori', '0777112233', 5000],
        ];

        return collect($rows)->mapWithKeys(function (array $row, int $index): array {
            $model = Customer::query()->firstOrCreate(['phone' => $row[1]], [
                'name' => $row[0], 'email' => 'demo.customer'.($index + 1).'@example.test',
                'credit_limit' => $row[2], 'is_active' => true, 'notes' => 'DEMO customer for POS and credit testing.',
            ]);

            return [$row[1] => $model];
        });
    }

    private function medicines(Collection $categories, Collection $manufacturers): Collection
    {
        $rows = [
            ['MED-DEMO-001', '6291100001001', 'Panadol', 'Paracetamol', '500 mg', 'Tablet', 'Pain & Fever', 'GSK', 12, false],
            ['MED-DEMO-002', '6291100001002', 'Brufen', 'Ibuprofen', '400 mg', 'Tablet', 'Pain & Fever', 'Abbott', 10, false],
            ['MED-DEMO-003', '6291100001003', 'Augmentin', 'Amoxicillin + Clavulanate', '625 mg', 'Tablet', 'Antibiotics', 'GSK', 8, true],
            ['MED-DEMO-004', '6291100001004', 'Amoxicillin', 'Amoxicillin', '500 mg', 'Capsule', 'Antibiotics', 'Demo Afghan Pharma', 10, true],
            ['MED-DEMO-005', '6291100001005', 'Azithromycin', 'Azithromycin', '500 mg', 'Tablet', 'Antibiotics', 'Getz Pharma', 8, true],
            ['MED-DEMO-006', '6291100001006', 'Cefixime', 'Cefixime', '400 mg', 'Tablet', 'Antibiotics', 'Hilton Pharma', 8, true],
            ['MED-DEMO-007', '6291100001007', 'Omeprazole', 'Omeprazole', '20 mg', 'Capsule', 'Gastrointestinal', 'Getz Pharma', 12, false],
            ['MED-DEMO-008', '6291100001008', 'ORS', 'Oral Rehydration Salts', '20.5 g', 'Sachet', 'Gastrointestinal', 'Demo Afghan Pharma', 20, false],
            ['MED-DEMO-009', '6291100001009', 'Cetirizine', 'Cetirizine', '10 mg', 'Tablet', 'Allergy & Respiratory', 'Hilton Pharma', 10, false],
            ['MED-DEMO-010', '6291100001010', 'Salbutamol Inhaler', 'Salbutamol', '100 mcg', 'Inhaler', 'Allergy & Respiratory', 'GSK', 5, true],
            ['MED-DEMO-011', '6291100001011', 'Vitamin C', 'Ascorbic Acid', '500 mg', 'Tablet', 'Vitamins & Supplements', 'Demo Afghan Pharma', 15, false],
            ['MED-DEMO-012', '6291100001012', 'Folic Acid', 'Folic Acid', '5 mg', 'Tablet', 'Vitamins & Supplements', 'Demo Afghan Pharma', 15, false],
            ['MED-DEMO-013', '6291100001013', 'Zinc', 'Zinc Sulfate', '20 mg', 'Tablet', 'Vitamins & Supplements', 'Demo Afghan Pharma', 15, false],
            ['MED-DEMO-014', '6291100001014', 'Amlodipine', 'Amlodipine', '5 mg', 'Tablet', 'Cardiovascular', 'Getz Pharma', 10, true],
            ['MED-DEMO-015', '6291100001015', 'Atorvastatin', 'Atorvastatin', '20 mg', 'Tablet', 'Cardiovascular', 'Getz Pharma', 10, true],
            ['MED-DEMO-016', '6291100001016', 'Metformin', 'Metformin', '500 mg', 'Tablet', 'Diabetes', 'Hilton Pharma', 12, true],
            ['MED-DEMO-017', '6291100001017', 'Diclofenac Gel', 'Diclofenac', '1%', 'Gel', 'Dermatology', 'Novartis Demo', 6, false],
            ['MED-DEMO-018', '6291100001018', 'Clotrimazole Cream', 'Clotrimazole', '1%', 'Cream', 'Dermatology', 'Demo Afghan Pharma', 6, false],
        ];

        return collect($rows)->mapWithKeys(function (array $row) use ($categories, $manufacturers): array {
            $manufacturer = $manufacturers->get($row[7]) ?? Manufacturer::query()->firstOrCreate(['name' => $row[7]], ['country' => null, 'is_active' => true]);
            $model = Medicine::query()->firstOrCreate(['medicine_code' => $row[0]], [
                'barcode' => $row[1], 'brand_name' => $row[2], 'generic_name' => $row[3],
                'strength' => $row[4], 'dosage_form' => $row[5],
                'medicine_category_id' => $categories[$row[6]]->id, 'manufacturer_id' => $manufacturer->id,
                'purchase_unit' => 'pack', 'sale_unit' => 'unit', 'units_per_purchase_unit' => 10,
                'reorder_level' => $row[8], 'prescription_required' => $row[9],
                'batch_tracking_required' => true, 'expiry_tracking_required' => true,
                'is_active' => true, 'notes' => 'DEMO medicine for pharmacy testing.',
            ]);

            return [$row[0] => $model];
        });
    }

    private function batches(Collection $medicines, Collection $suppliers, StockLocation $location, User $owner): int
    {
        $count = 0;
        $supplier = $suppliers->first();
        $prices = [
            'MED-DEMO-001' => [3, 5], 'MED-DEMO-002' => [4, 7], 'MED-DEMO-003' => [18, 28], 'MED-DEMO-004' => [8, 13],
            'MED-DEMO-005' => [15, 24], 'MED-DEMO-006' => [20, 32], 'MED-DEMO-007' => [5, 9], 'MED-DEMO-008' => [6, 10],
            'MED-DEMO-009' => [3, 6], 'MED-DEMO-010' => [95, 135], 'MED-DEMO-011' => [4, 8], 'MED-DEMO-012' => [2, 4],
            'MED-DEMO-013' => [3, 6], 'MED-DEMO-014' => [4, 8], 'MED-DEMO-015' => [7, 12], 'MED-DEMO-016' => [3, 6],
            'MED-DEMO-017' => [55, 85], 'MED-DEMO-018' => [45, 70],
        ];

        foreach ($medicines as $code => $medicine) {
            [$cost, $price] = $prices[$code];
            $qty = $code === 'MED-DEMO-005' ? 6 : ($code === 'MED-DEMO-010' ? 4 : 35 + ((int) substr($code, -2) % 4) * 10);
            $expires = match ($code) {
                'MED-DEMO-002' => today()->addDays(14),
                'MED-DEMO-011' => today()->addDays(45),
                default => today()->addMonths(8 + ((int) substr($code, -2) % 10)),
            };

            $this->openingBatch($medicine, $supplier, $location, $owner, 'A', $qty, $cost, $price, $expires);
            $count++;
        }

        $this->openingBatch($medicines['MED-DEMO-001'], $supplier, $location, $owner, 'EXPIRED', 12, 2.5, 5, today()->subDays(20));
        $count++;

        // Same medicine, multiple sellable batches with different prices so FEFO price splitting can be tested easily.
        $this->openingBatch($medicines['MED-DEMO-004'], $supplier, $location, $owner, 'PRICE-A', 2, 8, 12, today()->addMonths(3));
        $this->openingBatch($medicines['MED-DEMO-004'], $supplier, $location, $owner, 'PRICE-B', 20, 9, 15, today()->addMonths(5));
        $count += 2;

        return $count;
    }

    private function openingBatch(Medicine $medicine, Supplier $supplier, StockLocation $location, User $owner, string $suffix, float $quantity, float $cost, float $price, $expiresAt): void
    {
        $batchKey = 'DEMO-'.$medicine->medicine_code.'-'.$suffix;
        $batch = ProductBatch::query()->firstOrCreate(
            ['medicine_id' => $medicine->id, 'stock_location_id' => $location->id, 'batch_key' => $batchKey],
            [
                'supplier_id' => $supplier->id, 'branch_id' => $location->branch_id,
                'batch_number' => $medicine->medicine_code.'-'.$suffix, 'manufactured_at' => today()->subMonths(4),
                'expires_at' => $expiresAt, 'status' => 'active', 'received_quantity' => $quantity,
                'available_quantity' => $quantity, 'purchase_cost' => $cost, 'sale_price' => $price,
                'last_movement_at' => now(),
            ],
        );

        StockMovement::query()->firstOrCreate(['idempotency_key' => 'demo:opening:'.$batchKey], [
            'product_batch_id' => $batch->id, 'medicine_id' => $medicine->id,
            'branch_id' => $location->branch_id, 'stock_location_id' => $location->id,
            'movement_type' => 'opening_balance', 'quantity_delta' => $quantity,
            'balance_after' => $quantity, 'unit_cost' => $cost, 'source_type' => 'demo_seed',
            'source_id' => $batch->id, 'reason' => 'DEMO opening stock', 'actor_id' => $owner->id,
            'occurred_at' => now(), 'metadata' => ['demo' => true],
        ]);
    }

    private function purchaseOrders(Collection $medicines, Collection $suppliers, User $owner): int
    {
        $definitions = [
            ['PO-DEMO-001', 'SUP-DEMO-001', 'approved', ['MED-DEMO-001' => 100, 'MED-DEMO-003' => 60, 'MED-DEMO-007' => 80]],
            ['PO-DEMO-002', 'SUP-DEMO-002', 'draft', ['MED-DEMO-005' => 50, 'MED-DEMO-010' => 20, 'MED-DEMO-016' => 80]],
        ];

        foreach ($definitions as [$number,$supplierCode,$status,$lines]) {
            $order = PurchaseOrder::query()->firstOrCreate(['number' => $number], [
                'supplier_id' => $suppliers[$supplierCode]->id, 'status' => $status,
                'order_date' => today()->subDays($status === 'approved' ? 6 : 1),
                'expected_date' => today()->addDays(5), 'currency' => 'AFN',
                'subtotal' => 0, 'discount_total' => 0, 'landed_cost_total' => 0, 'grand_total' => 0,
                'notes' => 'DEMO purchase order for workflow testing.', 'created_by' => $owner->id,
                'approved_by' => $status === 'approved' ? $owner->id : null,
                'submitted_at' => $status === 'approved' ? now()->subDays(5) : null,
                'approved_at' => $status === 'approved' ? now()->subDays(5) : null,
            ]);
            if ($order->lines()->exists()) {
                continue;
            }
            $subtotal = 0;
            foreach ($lines as $code => $qty) {
                $medicine = $medicines[$code];
                $batch = $medicine->batches()->orderBy('expires_at')->first();
                $cost = (float) ($batch?->purchase_cost ?? 5);
                $lineTotal = $qty * $cost;
                $subtotal += $lineTotal;
                PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $order->id, 'medicine_id' => $medicine->id,
                    'description' => $medicine->brand_name.' '.$medicine->strength,
                    'ordered_quantity' => $qty, 'received_quantity' => 0, 'unit_cost' => $cost,
                    'discount_amount' => 0, 'landed_cost_allocated' => 0, 'line_total' => $lineTotal,
                ]);
            }
            $order->update(['subtotal' => $subtotal, 'grand_total' => $subtotal]);
        }

        return count($definitions);
    }

    private function sales(Collection $medicines, Collection $customers, StockLocation $location, User $owner): int
    {
        $definitions = [
            ['001', null, ['MED-DEMO-001' => 2, 'MED-DEMO-008' => 1], 'cash'],
            ['002', '0701112233', ['MED-DEMO-002' => 2, 'MED-DEMO-009' => 1], 'cash'],
            ['003', '0793112233', ['MED-DEMO-003' => 1, 'MED-DEMO-001' => 1], 'cash'],
            ['004', null, ['MED-DEMO-007' => 2, 'MED-DEMO-011' => 2], 'cash'],
            ['005', '0782112233', ['MED-DEMO-016' => 3, 'MED-DEMO-014' => 2], 'credit'],
            ['006', null, ['MED-DEMO-012' => 3, 'MED-DEMO-013' => 2], 'cash'],
            ['007', '0771112233', ['MED-DEMO-004' => 2, 'MED-DEMO-008' => 2], 'cash'],
            ['008', null, ['MED-DEMO-018' => 1, 'MED-DEMO-009' => 2], 'cash'],
        ];

        foreach ($definitions as [$key,$phone,$items,$method]) {
            $lines = [];
            $total = 0;
            $requiresRx = false;
            foreach ($items as $code => $qty) {
                $medicine = $medicines[$code];
                $batch = ProductBatch::query()->where('medicine_id', $medicine->id)
                    ->where('stock_location_id', $location->id)->where('status', 'active')
                    ->where('available_quantity', '>', 0)->whereNotNull('sale_price')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                    ->orderBy('expires_at')->firstOrFail();
                $lines[] = ['medicine_id' => $medicine->id, 'quantity' => $qty];
                $total += $qty * (float) $batch->sale_price;
                $requiresRx = $requiresRx || $medicine->prescription_required;
            }
            $data = [
                'stock_location_id' => $location->id,
                'customer_id' => $phone ? $customers[$phone]->id : null,
                'idempotency_key' => 'demo:sale:'.$key,
                'lines' => $lines,
                'payments' => [['method' => $method, 'amount' => $total, 'reference' => $method === 'credit' ? 'DEMO-CREDIT-'.$key : null]],
                'notes' => 'DEMO sale for live dashboard/POS testing.',
            ];
            if ($requiresRx) {
                $data['prescription_reference'] = 'RX-DEMO-'.$key;
                $data['prescriber_name'] = 'Dr. Demo Kabul';
                $data['prescription_date'] = today()->toDateString();
            }
            $this->sales->checkout($owner, $data);
        }

        return count($definitions);
    }

    private function expenses(StockLocation $location, User $owner): int
    {
        $operating = $this->accounting->account('operating_expense');
        $cash = $this->accounting->account('cash_on_hand');
        $definitions = [
            ['rent', 2500, 'Shop rent allocation'], ['electricity', 450, 'Electricity'], ['cleaning', 180, 'Cleaning supplies'],
        ];
        foreach ($definitions as [$key,$amount,$payee]) {
            $this->expenses->post([
                'expense_account_id' => $operating->id, 'payment_account_id' => $cash->id,
                'stock_location_id' => $location->id, 'business_date' => today()->toDateString(),
                'currency' => 'AFN', 'amount' => $amount, 'payee' => $payee,
                'reference' => 'DEMO-'.$key, 'notes' => 'DEMO expense for accounting testing.',
                'idempotency_key' => 'demo:expense:'.$key,
            ], $owner->id);
        }

        return count($definitions);
    }

    private function safe(User $owner): int
    {
        $safe = $this->safe->ensureDefaultSafe();
        $this->safe->postManual($safe, $owner, [
            'movement_type' => 'owner_deposit',
            'amount' => 25000,
            'reference' => 'DEMO-SAFE-OPENING',
            'reason' => 'DEMO opening cash for Safe and Safe Closing testing.',
            'idempotency_key' => 'demo:safe:opening',
        ]);

        return $safe->movements()->count();
    }

    private function cashierShift(StockLocation $location, User $owner): void
    {
        CashierShift::query()->firstOrCreate([
            'stock_location_id' => $location->id,
            'user_id' => $owner->id,
            'business_date' => today(),
            'status' => 'open',
        ], [
            'opening_cash' => 5000, 'expected_cash' => 5000, 'opened_at' => now()->subHours(2),
        ]);
    }

    private function settings(Tenant $tenant): void
    {
        $setting = PharmacySetting::query()->first();
        if (! $setting) {
            return;
        }
        $setting->update([
            'phone' => $setting->phone ?: '0700000000',
            'address' => $setting->address ?: 'Kabul, Afghanistan',
            'receipt_footer' => $setting->receipt_footer ?: 'Thank you for choosing '.$tenant->name.'.',
        ]);
    }
}
