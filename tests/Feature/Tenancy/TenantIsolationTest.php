<?php

namespace Tests\Feature\Tenancy;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_owned_queries_are_isolated_and_fail_closed(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        $tenantB = Tenant::query()->create(['name' => 'B Pharmacy', 'slug' => 'b-pharmacy']);
        $context = app(TenantContext::class);

        $userA = $context->run($tenantA, fn () => User::factory()->create([
            'email' => 'owner@example.test',
        ]));

        $userB = $context->run($tenantB, fn () => User::factory()->create([
            'email' => 'owner@example.test',
        ]));

        $context->set($tenantA);
        $this->assertTrue(User::query()->whereKey($userA->id)->exists());
        $this->assertFalse(User::query()->whereKey($userB->id)->exists());
        $this->assertSame(1, User::query()->count());

        $context->set($tenantB);
        $this->assertFalse(User::query()->whereKey($userA->id)->exists());
        $this->assertTrue(User::query()->whereKey($userB->id)->exists());
        $this->assertSame(1, User::query()->count());

        $context->clear();
        $this->assertSame(0, User::query()->count());
        $this->assertSame(2, User::withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_tenant_owned_records_cannot_be_created_without_context(): void
    {
        $this->expectException(LogicException::class);

        User::factory()->create();
    }

    public function test_tenant_id_cannot_be_spoofed_across_contexts(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        $tenantB = Tenant::query()->create(['name' => 'B Pharmacy', 'slug' => 'b-pharmacy']);

        app(TenantContext::class)->set($tenantA);

        $this->expectException(LogicException::class);

        User::factory()->create(['tenant_id' => $tenantB->id]);
    }
}
