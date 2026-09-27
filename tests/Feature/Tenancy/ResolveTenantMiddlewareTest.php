<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')
            ->middleware('tenant')
            ->get('/_testing/tenant', fn () => response()->json([
                'tenant_id' => app(TenantContext::class)->id(),
            ]));
    }

    public function test_active_tenant_is_resolved_from_server_session(): void
    {
        $tenant = Tenant::query()->create(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);

        $this->withSession(['tenant_id' => $tenant->id])
            ->get('/_testing/tenant')
            ->assertOk()
            ->assertJsonPath('tenant_id', $tenant->id);

        $this->assertFalse(app(TenantContext::class)->has());
    }

    public function test_missing_tenant_context_is_rejected(): void
    {
        $this->get('/_testing/tenant')->assertForbidden();
    }

    public function test_suspended_tenant_is_rejected(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Suspended Pharmacy',
            'slug' => 'suspended-pharmacy',
            'status' => TenantStatus::Suspended,
        ]);

        $this->withSession(['tenant_id' => $tenant->id])
            ->get('/_testing/tenant')
            ->assertForbidden();
    }
}
