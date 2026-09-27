<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicineMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_medicine_master_is_tenant_isolated(): void
    {
        $tenantA = $this->createTenant(['name' => 'A Pharmacy', 'slug' => 'a']);
        $tenantB = $this->createTenant(['name' => 'B Pharmacy', 'slug' => 'b']);

        $medicineA = app(TenantContext::class)->run($tenantA, function () {
            $category = MedicineCategory::query()->create(['name' => 'Analgesics']);
            $manufacturer = Manufacturer::query()->create(['name' => 'Example Pharma', 'country' => 'Afghanistan']);

            return Medicine::query()->create([
                'medicine_category_id' => $category->id,
                'manufacturer_id' => $manufacturer->id,
                'medicine_code' => 'MED-001',
                'brand_name' => 'Example Paracetamol',
                'generic_name' => 'Paracetamol',
                'strength' => '500 mg',
                'dosage_form' => 'Tablet',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => 100,
                'reorder_level' => 50,
                'prescription_required' => false,
                'batch_tracking_required' => true,
                'expiry_tracking_required' => true,
                'is_active' => true,
            ]);
        });

        app(TenantContext::class)->run($tenantB, function (): void {
            Medicine::query()->create([
                'medicine_code' => 'MED-001',
                'brand_name' => 'Other Medicine',
                'purchase_unit' => 'pack',
                'sale_unit' => 'unit',
                'units_per_purchase_unit' => 1,
                'reorder_level' => 0,
                'prescription_required' => false,
                'batch_tracking_required' => true,
                'expiry_tracking_required' => true,
                'is_active' => true,
            ]);
        });

        app(TenantContext::class)->set($tenantA);

        $this->assertTrue(Medicine::query()->whereKey($medicineA->id)->exists());
        $this->assertSame(1, Medicine::query()->count());
        $this->assertSame(1, MedicineCategory::query()->count());
        $this->assertSame(1, Manufacturer::query()->count());

        app(TenantContext::class)->clear();

        $this->assertFalse(Schema::connection('central')->hasTable('medicines'));
    }

    public function test_batch_and_expiry_tracking_default_to_enabled(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);

        $medicine = app(TenantContext::class)->run($tenant, fn () => Medicine::query()->create([
            'medicine_code' => 'AMOX-500',
            'brand_name' => 'Amoxicillin 500',
            'purchase_unit' => 'box',
            'sale_unit' => 'capsule',
            'units_per_purchase_unit' => 20,
            'reorder_level' => 10,
        ]));

        $this->assertTrue($medicine->batch_tracking_required);
        $this->assertTrue($medicine->expiry_tracking_required);
        $this->assertTrue($medicine->is_active);
    }
}
