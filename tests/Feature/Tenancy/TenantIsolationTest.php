<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacies_use_different_databases_and_cannot_read_each_others_users(): void
    {
        $tenantA = $this->createTenant(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        $tenantB = $this->createTenant(['name' => 'B Pharmacy', 'slug' => 'b-pharmacy']);
        $context = app(TenantContext::class);

        $context->run($tenantA, fn () => User::factory()->create([
            'name' => 'Owner A',
            'email' => 'owner@example.test',
        ]));

        $context->run($tenantB, fn () => User::factory()->create([
            'name' => 'Owner B',
            'email' => 'owner@example.test',
        ]));

        $this->assertNotSame($tenantA->database()->getName(), $tenantB->database()->getName());
        $this->assertFalse(Schema::connection('central')->hasTable('users'));

        $context->run($tenantA, function (): void {
            $this->assertSame(['Owner A'], User::query()->pluck('name')->all());
        });

        $context->run($tenantB, function (): void {
            $this->assertSame(['Owner B'], User::query()->pluck('name')->all());
        });
    }

    public function test_same_email_is_valid_in_two_pharmacy_databases(): void
    {
        $tenantA = $this->createTenant(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        $tenantB = $this->createTenant(['name' => 'B Pharmacy', 'slug' => 'b-pharmacy']);
        $context = app(TenantContext::class);

        $context->run($tenantA, fn () => User::factory()->create(['email' => 'same@example.test']));
        $context->run($tenantB, fn () => User::factory()->create(['email' => 'same@example.test']));

        $context->run($tenantA, fn () => $this->assertSame(1, User::query()->where('email', 'same@example.test')->count()));
        $context->run($tenantB, fn () => $this->assertSame(1, User::query()->where('email', 'same@example.test')->count()));
    }
}
