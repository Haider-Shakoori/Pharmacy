<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\StockLocation;
use App\Models\User;
use App\Notifications\InventoryActivityNotification;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_notification_history_is_tenant_local_and_read_clear_are_user_scoped(): void
    {
        $tenant = $this->createTenant(['slug' => 'notification-history']);
        app(TrialProvisioner::class)->provision($tenant);
        $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);

        $users = $tenant->run(function () use ($roles): array {
            $owner = User::query()->create([
                'name' => 'Owner',
                'email' => 'owner@notifications.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $owner->roles()->sync([$roles['owner']->id]);

            $cashier = User::query()->create([
                'name' => 'Cashier',
                'email' => 'cashier@notifications.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $cashier->roles()->sync([$roles['cashier']->id]);

            $owner->notify(new InventoryActivityNotification('Private owner event', 'Only owner can see this', 'one'));
            $cashier->notify(new InventoryActivityNotification('Private cashier event', 'Only cashier can see this', 'two'));

            $this->assertDatabaseCount('notifications', 2);

            return [$owner, $cashier];
        });

        $this->assertFalse(Schema::connection('central')->hasTable('notifications'));
        $this->onTenantDomain($tenant);

        $tenant->run(function () use ($users): void {
            [$owner, $cashier] = $users;

            $this->actingAs($cashier)->get('/alerts')
                ->assertOk()
                ->assertSee('Private cashier event')
                ->assertDontSee('Private owner event');

            $this->actingAs($cashier)->post('/alerts/history/read')->assertRedirect();
            $this->assertSame(0, $cashier->unreadNotifications()->count());
            $this->assertSame(1, $owner->unreadNotifications()->count());

            $this->actingAs($cashier)->post('/alerts/history/clear')->assertRedirect();
            $this->assertSame(0, $cashier->notifications()->count());
            $this->assertSame(1, $owner->notifications()->count());
        });
    }

    public function test_batch_status_change_creates_one_notification_but_noop_does_not(): void
    {
        $tenant = $this->createTenant(['slug' => 'notification-status']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'status@notifications.test', 'password123');

        $batch = $tenant->run(function (): ProductBatch {
            $medicine = Medicine::query()->create(['medicine_code' => 'NOTIFY', 'brand_name' => 'Notification Medicine']);
            $branch = Branch::query()->create(['code' => 'NOT', 'name' => 'Test branch', 'is_active' => true]);
            $location = StockLocation::query()->create([
                'branch_id' => $branch->id,
                'code' => 'MAIN',
                'name' => 'Main store',
                'is_active' => true,
            ]);

            return ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $branch->id,
                'stock_location_id' => $location->id,
                'batch_number' => 'NOTIFY-1',
                'batch_key' => hash('sha256', 'NOTIFY-1'),
                'status' => 'active',
                'received_quantity' => '0',
                'available_quantity' => '0',
                'purchase_cost' => '1',
            ]);
        });

        $this->onTenantDomain($tenant);
        $tenant->run(function () use ($owner, $batch): void {
            $url = '/inventory/batches/'.$batch->id.'/status';
            $payload = ['status' => 'quarantined', 'reason' => 'Quality hold'];

            $this->actingAs($owner)->post($url, $payload)->assertRedirect();
            $this->assertSame(1, $owner->notifications()->count());
            $this->assertSame('inventory', $owner->notifications()->first()->data['category']);

            $this->actingAs($owner)->post($url, $payload)->assertRedirect();
            $this->assertSame(1, $owner->notifications()->count());
        });
    }
}
