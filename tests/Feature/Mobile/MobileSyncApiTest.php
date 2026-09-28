<?php

namespace Tests\Feature\Mobile;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\StockMovementService;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSyncApiTest extends TestCase
{
    use RefreshDatabase;

    private string $licenseKey;

    private string $accessToken;

    private string $deviceId = '33333333-3333-4333-8333-333333333333';

    private $tenant;

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
            'name' => 'Sync Pharmacy',
            'slug' => 'sync-pharmacy',
        ]);

        $this->licenseKey = app(TrialProvisioner::class)
            ->provision($this->tenant);

        $this->tenant->run(function (): void {
            $roles = app(RbacProvisioner::class)
                ->ensureForTenant($this->tenant);

            $cashier = User::query()->create([
                'name' => 'Mobile Cashier',
                'email' => 'mobile@sync.test',
                'password' => 'password',
                'is_active' => true,
            ]);
            $cashier->roles()->sync([$roles['cashier']->id]);

            $medicine = Medicine::query()->create([
                'medicine_code' => 'SYNC-001',
                'brand_name' => 'Sync Medicine',
                'generic_name' => 'Sync Generic',
                'sale_unit' => 'tablet',
            ]);

            $branch = Branch::query()->create([
                'code' => 'SYNC',
                'name' => 'Sync Branch',
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
                'batch_number' => 'SYNC-LOT',
                'batch_key' => 'SYNC-LOT',
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
                'sync-seed',
                'sync:seed',
                $cashier->id,
            );

            Customer::query()->create([
                'name' => 'Offline Customer',
                'phone' => '0700000000',
                'credit_limit' => 0,
                'is_active' => true,
            ]);
        });

        $registration = $this->postJson('/api/v1/mobile/register', [
            'license_key' => $this->licenseKey,
            'device_id' => $this->deviceId,
            'email' => 'mobile@sync.test',
            'password' => 'password',
        ])->assertOk();

        $this->accessToken = $registration->json('data.access_token');
    }

    public function test_mobile_sale_push_is_idempotent_and_returns_acknowledgement(): void
    {
        $references = $this->tenant->run(function (): array {
            return [
                'medicine_id' => Medicine::query()
                    ->where('medicine_code', 'SYNC-001')
                    ->value('id'),
                'location_id' => StockLocation::query()
                    ->where('code', 'MAIN')
                    ->value('id'),
            ];
        });

        $event = [
            'idempotency_key' => 'sale:mobile-sync-001:completed',
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => 'mobile-sync-001',
                'idempotency_key' => 'sale:mobile-sync-001:completed',
                'stock_location_id' => $references['location_id'],
                'customer_id' => null,
                'cashier_user_id' => '1',
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
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted');

        $serverId = $first->json('data.results.0.server_id');

        $second = $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted')
            ->assertJsonPath('data.results.0.server_id', $serverId);

        $this->tenant->run(function () use ($serverId): void {
            $this->assertSame(
                1,
                Sale::query()
                    ->where('idempotency_key', 'sale:mobile-sync-001:completed')
                    ->count(),
            );
            $this->assertNotNull(Sale::query()->find($serverId));
        });
    }

    public function test_incremental_pull_returns_catalog_and_cursor(): void
    {
        foreach (['medicines', 'inventory', 'customers'] as $stream) {
            $response = $this->withToken($this->accessToken)
                ->getJson('/api/v1/mobile/sync/pull/'.$stream.'?limit=1')
                ->assertOk()
                ->assertJsonPath('data.stream', $stream);

            $this->assertCount(1, $response->json('data.data'));
            $this->assertNotEmpty($response->json('data.next_cursor'));
        }
    }

    public function test_session_refresh_renews_access_without_plaintext_license_key(): void
    {
        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/session/refresh')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'mobile@sync.test');

        $this->assertStringStartsWith(
            'v1.',
            $response->json('data.access_token'),
        );
        $this->assertStringStartsWith(
            'v1.',
            $response->json('data.offline_lease.token'),
        );
    }
}
