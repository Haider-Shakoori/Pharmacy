<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\StockLocation;
use App\Services\Safe\SafeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_safe_is_created_for_the_default_stock_location(): void
    {
        $tenant = $this->createTenant(['slug' => 'safe-test']);

        $tenant->run(function (): void {
            $branch = Branch::query()->create([
                'code' => 'KBL',
                'name' => 'Kabul',
                'is_default' => true,
                'is_active' => true,
            ]);

            $location = StockLocation::query()->create([
                'branch_id' => $branch->id,
                'code' => 'MAIN',
                'name' => 'Main',
                'is_default' => true,
                'is_active' => true,
            ]);

            $safe = app(SafeService::class)->ensureDefaultSafe();

            $this->assertSame('MAIN-SAFE', $safe->code);
            $this->assertSame($location->id, $safe->stock_location_id);
            $this->assertTrue($safe->is_default);
            $this->assertSame('0.0000', app(SafeService::class)->balance($safe));
        });
    }
}
