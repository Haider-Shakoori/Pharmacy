<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_medicine_import_preview_and_confirm_create_only_after_confirmation(): void
    {
        $tenant = $this->createTenant(['name' => 'CSV Import A', 'slug' => 'csv-import-a']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'csv-owner@example.test', 'password123');

        $csv = "medicine_code,brand_name,generic_name,strength,dosage_form,category,manufacturer,manufacturer_country,barcode,purchase_unit,sale_unit,units_per_purchase_unit,reorder_level,prescription_required,batch_tracking_required,expiry_tracking_required,is_active,notes\nCSV-001,Test Tablet,,,,,,,,box,tablet,20,5,0,1,1,1,\n";
        $file = UploadedFile::fake()->createWithContent('medicines.csv', $csv);

        $this->onTenantDomain($tenant)->actingAs($owner)
            ->post('/csv-import/medicines/preview', ['file' => $file])
            ->assertRedirect();
        $this->onTenantDomain($tenant)->assertDatabaseCount('medicines', 0);

        $this->onTenantDomain($tenant)->post('/csv-import/medicines/commit')->assertRedirect();
        $this->onTenantDomain($tenant)->assertDatabaseHas('medicines', [
            'medicine_code' => 'CSV-001', 'brand_name' => 'Test Tablet',
        ]);
        $this->onTenantDomain($tenant)->post('/csv-import/medicines/commit')->assertSessionHasErrors();
        $this->onTenantDomain($tenant)->assertDatabaseCount('medicines', 1);
    }

    public function test_inventory_csv_posts_one_auditable_stock_receipt(): void
    {
        $tenant = $this->createTenant(['name' => 'CSV Import B', 'slug' => 'csv-import-b']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'csv-inventory@example.test', 'password123');
        $location = app(InventoryProvisioner::class)->ensureDefaults($tenant);
        $tenant->run(fn () => Medicine::query()->create([
            'medicine_code' => 'CSV-STOCK-001', 'brand_name' => 'Stock Test',
        ]));

        $csv = "medicine_code,batch_number,expiry_date,manufactured_date,quantity,purchase_cost,sale_price,notes\nCSV-STOCK-001,B-CSV-1,2028-01-01,,7,10.50,15.00,Opening stock\n";
        $this->onTenantDomain($tenant)->actingAs($owner)
            ->post('/csv-import/inventory/preview', [
                'file' => UploadedFile::fake()->createWithContent('inventory.csv', $csv),
                'stock_location_id' => $location->id,
            ])->assertRedirect();
        $tenant->run(fn () => $this->assertSame(0, ProductBatch::query()->count()));

        $this->onTenantDomain($tenant)->post('/csv-import/inventory/commit')->assertRedirect();
        $tenant->run(function (): void {
            $this->assertSame('7.0000', ProductBatch::query()->firstOrFail()->available_quantity);
            $this->assertSame('7.0000', StockMovement::query()->firstOrFail()->quantity_delta);
            $this->assertSame('csv_opening_stock', StockMovement::query()->firstOrFail()->source_type);
        });
    }
}
