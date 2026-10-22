<?php

namespace Tests\Feature\Licensing;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\Plan;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\StockMovementService;
use App\Services\Licensing\LicenseKeyService;
use App\Support\Commercial\DarmaltoonStandardOffer as Standard;
use Database\Seeders\DarmaltoonStandardPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarmaltoonMixedDeviceSyncTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $licenseKey;

    private int $cashierId;

    private string $medicineId;

    private string $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);

        $this->tenant = $this->createTenant(['name' => 'Darmaltoon Mixed Devices', 'slug' => 'darmaltoon-mixed']);
        $this->seed(DarmaltoonStandardPlanSeeder::class);

        // Test enables the otherwise deliberately inactive commercial preset.
        $plan = Plan::query()->where('code', Standard::PLAN_CODE)->sole();
        $plan->update(['is_active' => true]);

        $subscription = Subscription::query()->create([
            'business_id' => $this->tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'auto_renew' => false,
        ]);

        $this->licenseKey = app(LicenseKeyService::class)->rotate($subscription);

        $owner = app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Owner',
            'owner@darmaltoon.test',
            'secret-password',
        );
        $this->cashierId = (int) $owner->id;

        $this->tenant->run(function (): void {
            $medicine = Medicine::query()->create([
                'medicine_code' => 'DARM-STOCK-01',
                'brand_name' => 'Integration Test Medicine',
                'generic_name' => 'Integration Test',
                'sale_unit' => 'tablet',
            ]);
            $branch = Branch::query()->create([
                'code' => 'MAIN',
                'name' => 'Main Pharmacy',
                'is_default' => true,
                'is_active' => true,
            ]);
            $location = StockLocation::query()->create([
                'branch_id' => $branch->id,
                'code' => 'POS',
                'name' => 'Counter',
                'is_default' => true,
                'is_active' => true,
            ]);
            $batch = ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $branch->id,
                'stock_location_id' => $location->id,
                'batch_number' => 'DARM-BATCH',
                'batch_key' => 'DARM-BATCH',
                'expires_at' => today()->addMonths(6),
                'status' => 'active',
                'received_quantity' => 20,
                'available_quantity' => 0,
                'purchase_cost' => 4,
                'sale_price' => 10,
            ]);
            app(StockMovementService::class)->record(
                $batch, '20', 'receipt', 'test',
                'darmaltoon-mixed-inventory', 'darmaltoon:seed', $this->cashierId,
            );
            $this->medicineId = (string) $medicine->id;
            $this->locationId = (string) $location->id;
        });
    }

    public function test_standard_license_supports_one_windows_and_two_android_devices_but_no_more(): void
    {
        $first = $this->registerAndroid('11111111-1111-4111-8111-111111111111')
            ->assertOk()
            ->assertJsonPath('data.plan.max_android_devices', 2)
            ->assertJsonPath('data.plan.max_windows_devices', 1);
        $second = $this->registerAndroid('22222222-2222-4222-8222-222222222222')->assertOk();

        $windows = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
        ])->assertOk();

        $this->registerAndroid('44444444-4444-4444-8444-444444444444')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '55555555-5555-4555-8555-555555555555',
            'platform' => 'windows',
        ])->assertUnprocessable()->assertJsonValidationErrors('license_key');

        $this->assertNotSame($first->json('data.activation_id'), $second->json('data.activation_id'));
        $this->assertNotSame($first->json('data.activation_id'), $windows->json('data.activation_id'));
        $this->assertDatabaseCount('license_activations', 3);
        $this->assertDatabaseHas('license_activations', ['platform' => 'windows', 'revoked_at' => null]);
    }

    public function test_mobile_and_desktop_sales_reconcile_to_shared_server_stock_without_duplicate_posting(): void
    {
        $android1 = $this->registerAndroid('11111111-1111-4111-8111-111111111111')->assertOk();
        $android2 = $this->registerAndroid('22222222-2222-4222-8222-222222222222')->assertOk();
        $pc = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
        ])->assertOk();

        $desktopLogin = $this->withToken($pc->json('data.lease_token'))
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => '33333333-3333-4333-8333-333333333333',
                'email' => 'owner@darmaltoon.test',
                'password' => 'secret-password',
            ])->assertOk();
        $desktopToken = (string) $desktopLogin->json('data.access_token');

        $mobile1Token = (string) $android1->json('data.access_token');
        $mobile2Token = (string) $android2->json('data.access_token');

        foreach ([
            ['token' => $mobile1Token, 'endpoint' => 'mobile', 'key' => 'sale:darm-android-1:completed'],
            ['token' => $desktopToken, 'endpoint' => 'desktop', 'key' => 'sale:darm-windows:completed'],
            ['token' => $mobile2Token, 'endpoint' => 'mobile', 'key' => 'sale:darm-android-2:completed'],
        ] as $source) {
            $event = $this->saleEvent($source['key']);

            $first = $this->withToken($source['token'])
                ->postJson('/api/v1/'.$source['endpoint'].'/sync/push', ['events' => [$event]])
                ->assertOk()->assertJsonPath('data.results.0.status', 'accepted');

            $this->withToken($source['token'])
                ->postJson('/api/v1/'.$source['endpoint'].'/sync/push', ['events' => [$event]])
                ->assertOk()
                ->assertJsonPath('data.results.0.status', 'accepted')
                ->assertJsonPath('data.results.0.server_id', $first->json('data.results.0.server_id'));
        }

        $this->tenant->run(function (): void {
            $this->assertSame(3, Sale::query()->count());
            $this->assertSame('17.0000', ProductBatch::query()
                ->where('batch_number', 'DARM-BATCH')->sole()->available_quantity);
        });

        foreach ([$mobile1Token, $mobile2Token] as $token) {
            $this->withToken($token)
                ->getJson('/api/v1/mobile/sync/pull/inventory?limit=25')
                ->assertOk()->assertJsonPath('data.stream', 'inventory');
        }

        $this->withToken($desktopToken)
            ->getJson('/api/v1/desktop/sync/pull/inventory?limit=25')
            ->assertOk()->assertJsonPath('data.stream', 'inventory');
    }

    public function test_late_android_offline_sale_is_rejected_if_windows_already_consumed_the_stock(): void
    {
        // These sales represent outbox entries created while separate
        // clients were disconnected and later submitted to the shared API.
        $android = $this->registerAndroid('11111111-1111-4111-8111-111111111111')->assertOk();
        $pc = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
        ])->assertOk();

        $desktopToken = $this->withToken($pc->json('data.lease_token'))
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => '33333333-3333-4333-8333-333333333333',
                'email' => 'owner@darmaltoon.test',
                'password' => 'secret-password',
            ])->assertOk()->json('data.access_token');

        $desktopEvent = $this->saleEvent('sale:offline-windows-18:completed');
        $desktopEvent['payload']['lines'][0]['quantity'] = '18.0000';
        $desktopEvent['payload']['payments'][0]['amount'] = '180.0000';

        $this->withToken($desktopToken)->postJson('/api/v1/desktop/sync/push', [
            'events' => [$desktopEvent],
        ])->assertOk()->assertJsonPath('data.results.0.status', 'accepted');

        $staleAndroidSale = $this->saleEvent('sale:offline-android-4:completed');
        $staleAndroidSale['payload']['lines'][0]['quantity'] = '4.0000';
        $staleAndroidSale['payload']['payments'][0]['amount'] = '40.0000';

        $this->withToken($android->json('data.access_token'))
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [$staleAndroidSale],
            ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.code', 'stock_conflict')
            ->assertJsonPath('data.results.0.retryable', false);

        $this->tenant->run(function (): void {
            $this->assertSame(1, Sale::query()->count());
            $this->assertSame('2.0000', ProductBatch::query()
                ->where('batch_number', 'DARM-BATCH')->sole()->available_quantity);
        });
    }

    private function registerAndroid(string $deviceId)
    {
        return $this->postJson('/api/v1/mobile/register', [
            'license_key' => $this->licenseKey,
            'device_id' => $deviceId,
            'device_name' => 'Android POS',
            'email' => 'owner@darmaltoon.test',
            'password' => 'secret-password',
        ]);
    }

    private function saleEvent(string $key): array
    {
        return [
            'idempotency_key' => $key,
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => $key,
                'idempotency_key' => $key,
                'stock_location_id' => $this->locationId,
                'customer_id' => null,
                'cashier_user_id' => (string) $this->cashierId,
                'business_date' => today()->toDateString(),
                'currency' => 'AFN',
                'lines' => [[
                    'medicine_id' => $this->medicineId,
                    'quantity' => '1.0000',
                    'unit_price' => '10.0000',
                    'discount_amount' => '0.0000',
                ]],
                'payments' => [['method' => 'cash', 'amount' => '10.0000']],
            ],
        ];
    }
}
