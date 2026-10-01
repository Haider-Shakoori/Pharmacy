<?php

namespace Tests\Feature\Desktop;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\Plan;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\StockMovementService;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DesktopSyncApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Subscription $subscription;

    private string $licenseKey;

    private string $deviceId = '77777777-7777-4777-8777-777777777777';

    private string $leaseToken;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(
                sodium_crypto_sign_secretkey($keyPair),
            ),
            'pharmacy.license.signing_public_key' => base64_encode(
                sodium_crypto_sign_publickey($keyPair),
            ),
        ]);

        $this->tenant = $this->createTenant([
            'name' => 'Desktop Sync Pharmacy',
            'slug' => 'desktop-sync-pharmacy',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Desktop Sync',
            'code' => 'DESKTOP-SYNC',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_windows_devices' => 3,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'features' => ['desktop_sync'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->subscription = Subscription::query()->create([
            'business_id' => $this->tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]);

        $this->licenseKey = app(LicenseKeyService::class)
            ->rotate($this->subscription);

        $this->tenant->run(function (): void {
            $roles = app(RbacProvisioner::class)
                ->ensureForTenant($this->tenant);

            $cashier = User::query()->create([
                'name' => 'Desktop Cashier',
                'email' => 'desktop@sync.test',
                'password' => Hash::make('secret-password'),
                'is_active' => true,
            ]);
            $cashier->roles()->sync([$roles['cashier']->id]);

            $medicine = Medicine::query()->create([
                'medicine_code' => 'DESK-SYNC-001',
                'brand_name' => 'Desktop Sync Medicine',
                'generic_name' => 'Desktop Generic',
                'sale_unit' => 'tablet',
            ]);

            $branch = Branch::query()->create([
                'code' => 'DESK',
                'name' => 'Desktop Branch',
                'is_default' => true,
                'is_active' => true,
            ]);

            $location = StockLocation::query()->create([
                'branch_id' => $branch->id,
                'code' => 'MAIN',
                'name' => 'Main Store',
                'is_default' => true,
                'is_active' => true,
            ]);

            $batch = ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $branch->id,
                'stock_location_id' => $location->id,
                'batch_number' => 'DESK-LOT',
                'batch_key' => 'DESK-LOT',
                'expires_at' => today()->addMonths(6),
                'status' => 'active',
                'received_quantity' => 20,
                'available_quantity' => 0,
                'purchase_cost' => 4,
                'sale_price' => 10,
            ]);

            app(StockMovementService::class)->record(
                $batch,
                '20',
                'receipt',
                'test',
                'desktop-sync-seed',
                'desktop:sync:seed',
                $cashier->id,
            );
        });

        $activation = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => $this->deviceId,
            'platform' => 'windows',
            'device_name' => 'Desktop Sync Counter',
        ])->assertOk();

        $this->leaseToken = $activation->json('data.lease_token');

        $login = $this->withToken($this->leaseToken)
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $this->deviceId,
                'email' => 'desktop@sync.test',
                'password' => 'secret-password',
            ])->assertOk();

        $this->accessToken = $login->json('data.access_token');
    }

    public function test_desktop_pull_is_tenant_isolated_and_incremental(): void
    {
        $other = $this->createTenant([
            'name' => 'Other Pharmacy',
            'slug' => 'other-pharmacy',
        ]);

        $other->run(function (): void {
            Medicine::query()->create([
                'medicine_code' => 'OTHER-001',
                'brand_name' => 'Other Tenant Medicine',
                'sale_unit' => 'tablet',
            ]);
        });

        $response = $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines?limit=10')
            ->assertOk()
            ->assertJsonPath('data.stream', 'medicines');

        $codes = collect($response->json('data.data'))
            ->pluck('medicine_code')
            ->all();

        $this->assertContains('DESK-SYNC-001', $codes);
        $this->assertNotContains('OTHER-001', $codes);
        $this->assertNotEmpty($response->json('data.next_cursor'));
    }

    public function test_desktop_sale_push_is_idempotent(): void
    {
        $references = $this->tenant->run(fn (): array => [
            'medicine_id' => Medicine::query()
                ->where('medicine_code', 'DESK-SYNC-001')
                ->value('id'),
            'location_id' => StockLocation::query()
                ->where('code', 'MAIN')
                ->value('id'),
            'cashier_id' => User::query()
                ->where('email', 'desktop@sync.test')
                ->value('id'),
        ]);

        $event = [
            'idempotency_key' => 'sale:desktop-sync-001:completed',
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => 'desktop-sync-001',
                'idempotency_key' => 'sale:desktop-sync-001:completed',
                'stock_location_id' => $references['location_id'],
                'customer_id' => null,
                'cashier_user_id' => (string) $references['cashier_id'],
                'business_date' => today()->toDateString(),
                'currency' => 'AFN',
                'lines' => [[
                    'medicine_id' => $references['medicine_id'],
                    'quantity' => '2.0000',
                    'unit_price' => '10.0000',
                    'discount_amount' => '0.0000',
                ]],
                'payments' => [[
                    'method' => 'cash',
                    'amount' => '20.0000',
                ]],
            ],
        ];

        $first = $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted');

        $serverId = $first->json('data.results.0.server_id');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.server_id', $serverId);

        $this->tenant->run(function (): void {
            $this->assertSame(
                1,
                Sale::query()
                    ->where('idempotency_key', 'sale:desktop-sync-001:completed')
                    ->count(),
            );
        });
    }

    public function test_offline_lease_cannot_be_used_as_desktop_sync_access_token(): void
    {
        $this->withToken($this->leaseToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines')
            ->assertUnauthorized();
    }

    public function test_sync_is_rejected_after_subscription_becomes_non_operational(): void
    {
        $this->subscription->forceFill([
            'status' => 'cancelled',
            'ends_at' => now()->subMinute(),
        ])->save();

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines')
            ->assertUnauthorized();
    }
}
