<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenant_is_resolved_from_server_session(): void
    {
        $tenant = Tenant::query()->create(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        $response = app(ResolveTenant::class)->handle(
            $this->requestWithTenant($tenant),
            fn () => response()->json([
                'tenant_id' => app(TenantContext::class)->id(),
            ]),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($tenant->id, $response->getData(true)['tenant_id']);
        $this->assertFalse(app(TenantContext::class)->has());
    }

    public function test_missing_tenant_context_is_rejected(): void
    {
        try {
            app(ResolveTenant::class)->handle(
                $this->requestWithTenant(),
                fn () => response()->noContent(),
            );

            $this->fail('Missing tenant context was not rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_suspended_tenant_is_rejected(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Suspended Pharmacy',
            'slug' => 'suspended-pharmacy',
            'status' => TenantStatus::Suspended,
        ]);

        try {
            app(ResolveTenant::class)->handle(
                $this->requestWithTenant($tenant),
                fn () => response()->noContent(),
            );

            $this->fail('Suspended tenant was not rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function requestWithTenant(?Tenant $tenant = null): Request
    {
        $request = Request::create('/pharmacy', 'GET');
        $session = app('session')->driver();
        $session->flush();

        if ($tenant !== null) {
            $session->put('tenant_id', $tenant->id);
        }

        $request->setLaravelSession($session);

        return $request;
    }
}
