<?php

namespace Tests\Feature\Pharmacy;

use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalMutationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_cashier_cannot_post_inventory_adjustments_or_accounting_expenses(): void
    {
        $tenant = $this->createTenant(['slug' => 'mutation-access']);
        app(TrialProvisioner::class)->provision($tenant);
        $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);

        $cashier = $tenant->run(function () use ($roles): User {
            $user = User::query()->create([
                'name' => 'Restricted Cashier',
                'email' => 'cashier@mutation-access.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $user->roles()->sync([$roles['cashier']->id]);

            return $user;
        });

        $this->onTenantDomain($tenant);

        $this->actingAs($cashier)->post('/inventory/adjustments', [
            'product_batch_id' => 'unknown',
            'quantity_delta' => '1000',
            'reason_code' => 'found_stock',
            'reason' => 'Attempt to change stock without permission',
        ])->assertForbidden();

        $this->actingAs($cashier)->post('/accounting/expenses', [
            'amount' => '1000',
            'description' => 'Unauthorized expense',
        ])->assertForbidden();

        $this->actingAs($cashier)->get('/settings')->assertForbidden();
    }
}
