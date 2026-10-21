<?php

namespace Tests\Feature\Desktop;

use App\Models\Branch;
use App\Models\Customer;
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
use App\Services\Licensing\OfflineLeaseSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopSyncApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $licenseKey;

    private string $accessToken;

    private string $activationId;

    private string $deviceId = '77777777-7777-4777-8777-777777777777';

    private int $userId;

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
            'code' => 'DESKTOP_SYNC',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_windows_devices' => 2,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'features' => ['advanced_reports'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'business_id' => $this->tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]);

        $this->licenseKey = app(LicenseKeyService::class)->rotate($subscription);

        $owner = app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Desktop Owner',
            'desktop-owner@example.test',
            'secret-password',
        );
        $this->userId = (int) $owner->id;

        $this->seedInventory();

        $activation = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => $this->deviceId,
            'platform' => 'windows',
            'device_name' => 'Main Pharmacy Server',
        ])->assertOk();

        $this->activationId = (string) $activation->json('data.activation_id');

        $login = $this->withToken($activation->json('data.lease_token'))
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $this->deviceId,
                'email' => 'desktop-owner@example.test',
                'password' => 'secret-password',
            ])
            ->assertOk();

        $this->accessToken = (string) $login->json('data.access_token');
    }

    public function test_platform_can_disable_desktop_cloud_sync_without_disabling_local_session(): void
    {
        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.managed_by', 'platform');

        $this->tenant->business->forceFill([
            'desktop_cloud_sync_enabled' => false,
        ])->save();

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.managed_by', 'platform');

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines?limit=1')
            ->assertStatus(409)
            ->assertJsonPath('code', 'desktop_sync_disabled');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [[
                    'idempotency_key' => 'sync-disabled:test',
                    'event_type' => 'customer.upsert',
                    'payload' => [
                        'v' => 1,
                        'local_id' => 'disabled-sync-customer',
                        'idempotency_key' => 'sync-disabled:test',
                        'name' => 'Queued Offline Customer',
                        'phone' => null,
                        'email' => null,
                        'credit_limit' => '0.0000',
                        'is_active' => true,
                        'notes' => null,
                    ],
                ]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'desktop_sync_disabled');
    }

    public function test_desktop_sync_pull_rejects_invalid_pagination_parameters(): void
    {
        $maxPageSize = (int) config('pharmacy.performance.sync_max_page_size', 250);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines?limit=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines?limit='.($maxPageSize + 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines?cursor='.str_repeat('a', 1001))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cursor');
    }

    public function test_desktop_sync_push_validates_event_batch_size_and_required_fields(): void
    {
        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', ['events' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('events');

        $tooManyEvents = array_fill(0, 26, [
            'idempotency_key' => 'batch-test',
            'event_type' => 'customer.upsert',
            'payload' => ['v' => 1],
        ]);

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', ['events' => $tooManyEvents])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('events');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [['event_type' => 'customer.upsert', 'payload' => ['v' => 1]]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('events.0.idempotency_key');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [['idempotency_key' => 'missing-type', 'payload' => ['v' => 1]]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('events.0.event_type');
    }

    public function test_desktop_session_can_pull_incremental_medicine_stream(): void
    {
        $response = $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines?limit=1')
            ->assertOk()
            ->assertJsonPath('data.stream', 'medicines')
            ->assertJsonPath('data.has_more', false);

        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(
            'DESKTOP-SYNC-001',
            $response->json('data.data.0.medicine_code'),
        );
        $this->assertNotEmpty($response->json('data.next_cursor'));
    }

    public function test_desktop_can_pull_dependency_and_access_streams(): void
    {
        foreach ([
            'branches',
            'stock_locations',
            'permissions',
            'roles',
            'users',
        ] as $stream) {
            $response = $this->withToken($this->accessToken)
                ->getJson("/api/v1/desktop/sync/pull/{$stream}?limit=100")
                ->assertOk()
                ->assertJsonPath('data.stream', $stream);

            $this->assertNotEmpty($response->json('data.data'));
        }

        $users = $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/sync/pull/users?limit=100')
            ->assertOk();

        $owner = collect($users->json('data.data'))
            ->firstWhere('email', 'desktop-owner@example.test');

        $this->assertNotNull($owner);
        $this->assertTrue($owner['is_active']);
        $this->assertNotEmpty($owner['roles']);
        $this->assertNotEmpty($owner['permissions']);
    }

    public function test_desktop_master_data_upserts_are_idempotent_by_desktop_source_id(): void
    {
        $medicineEvent = [
            'idempotency_key' => 'medicine:desktop-local-1:upsert:1',
            'event_type' => 'medicine.upsert',
            'payload' => [
                'v' => 1,
                'local_id' => 'desktop-local-1',
                'idempotency_key' => 'medicine:desktop-local-1:upsert:1',
                'medicine_code' => 'DESKTOP-LOCAL-001',
                'barcode' => '99000112233',
                'brand_name' => 'Desktop Local Medicine',
                'generic_name' => 'Local Generic',
                'strength' => '250 mg',
                'dosage_form' => 'Tablet',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => '100.0000',
                'reorder_level' => '20.0000',
                'prescription_required' => false,
                'batch_tracking_required' => true,
                'expiry_tracking_required' => true,
                'is_active' => true,
                'notes' => 'Created from desktop sync.',
            ],
        ];

        $firstMedicine = $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$medicineEvent],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted');

        $medicineId = $firstMedicine->json('data.results.0.server_id');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$medicineEvent],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted')
            ->assertJsonPath('data.results.0.server_id', $medicineId);

        $customerEvent = [
            'idempotency_key' => 'customer:desktop-customer-1:upsert:1',
            'event_type' => 'customer.upsert',
            'payload' => [
                'v' => 1,
                'local_id' => 'desktop-customer-1',
                'idempotency_key' => 'customer:desktop-customer-1:upsert:1',
                'name' => 'Desktop Synced Customer',
                'phone' => '0700888999',
                'email' => 'desktop-customer@example.test',
                'credit_limit' => '700.0000',
                'is_active' => true,
                'notes' => 'Created from desktop sync.',
            ],
        ];

        $firstCustomer = $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$customerEvent],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted');

        $customerId = $firstCustomer->json('data.results.0.server_id');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$customerEvent],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted')
            ->assertJsonPath('data.results.0.server_id', $customerId);

        $this->tenant->run(function (): void {
            $this->assertSame(
                1,
                Medicine::query()
                    ->where('desktop_source_id', 'desktop-local-1')
                    ->count(),
            );

            $this->assertSame(
                1,
                Customer::query()
                    ->where('desktop_source_id', 'desktop-customer-1')
                    ->count(),
            );
        });
    }

    public function test_latest_customer_edit_wins_across_delayed_desktop_events_and_web_updates(): void
    {
        $baseKey = 'customer:master-version-test:upsert:';
        $base = [
            'v' => 1,
            'local_id' => 'master-version-test',
            'name' => 'Initial Desktop Name',
            'email' => 'version-test@example.test',
            'phone' => null,
            'credit_limit' => '0.0000',
            'is_active' => true,
            'notes' => null,
        ];
        $push = function (int $number, string $name, string $timestamp) use ($baseKey, $base) {
            $key = $baseKey.$number;

            return $this->withToken($this->accessToken)
                ->postJson('/api/v1/desktop/sync/push', [
                    'events' => [[
                        'idempotency_key' => $key,
                        'event_type' => 'customer.upsert',
                        'payload' => array_merge($base, [
                            'idempotency_key' => $key,
                            'name' => $name,
                            'local_updated_at' => $timestamp,
                        ]),
                    ]],
                ])
                ->assertOk()
                ->assertJsonPath('data.results.0.status', 'accepted');
        };

        $push(1, 'Initial Desktop Name', now()->subMinutes(10)->toISOString());
        // Two edits accumulated while offline must be processed in source order,
        // despite the server assigning a later updated_at to the first arrival.
        $push(2, 'Later Offline Desktop Name', now()->subMinutes(5)->toISOString());
        $this->tenant->run(function (): void {
            $record = Customer::query()->where('desktop_source_id', 'master-version-test')->firstOrFail();
            $this->assertSame('Later Offline Desktop Name', $record->name);
            $record->update(['name' => 'Latest Web Name']);
        });

        $push(3, 'Stale Offline Desktop Name', now()->subMinutes(3)->toISOString())
            ->assertJsonPath('data.results.0.code', 'server_newer');

        $this->tenant->run(function (): void {
            $this->assertSame(
                'Latest Web Name',
                Customer::query()->where('desktop_source_id', 'master-version-test')->value('name'),
            );
        });

        $push(4, 'Fresh Desktop Name', now()->addSeconds(2)->toISOString());
        $this->tenant->run(function (): void {
            $this->assertSame(
                'Fresh Desktop Name',
                Customer::query()->where('desktop_source_id', 'master-version-test')->value('name'),
            );
        });
    }

    public function test_latest_medicine_edit_wins_and_web_edit_is_not_overwritten_by_stale_desktop(): void
    {
        $base = [
            'v' => 1,
            'local_id' => 'medicine-master-version-test',
            'medicine_code' => 'MASTER-VERSION-MED',
            'brand_name' => 'First Local Brand',
            'barcode' => null,
            'generic_name' => null,
            'strength' => null,
            'dosage_form' => null,
            'purchase_unit' => 'box',
            'sale_unit' => 'unit',
            'units_per_purchase_unit' => '10.0000',
            'reorder_level' => '0.0000',
            'prescription_required' => false,
            'batch_tracking_required' => true,
            'expiry_tracking_required' => true,
            'is_active' => true,
            'notes' => null,
        ];
        $send = function (int $n, string $name, string $modifiedAt) use ($base) {
            $key = 'medicine:master-version:'.(string) $n;

            return $this->withToken($this->accessToken)
                ->postJson('/api/v1/desktop/sync/push', ['events' => [[
                    'idempotency_key' => $key,
                    'event_type' => 'medicine.upsert',
                    'payload' => array_merge($base, [
                        'idempotency_key' => $key,
                        'brand_name' => $name,
                        'local_updated_at' => $modifiedAt,
                    ]),
                ]]])
                ->assertOk()
                ->assertJsonPath('data.results.0.status', 'accepted');
        };

        $send(1, 'First Local Brand', now()->subMinutes(20)->toISOString());
        $send(2, 'Later Local Brand', now()->subMinutes(10)->toISOString());
        $this->tenant->run(function (): void {
            $medicine = Medicine::query()->where('medicine_code', 'MASTER-VERSION-MED')->firstOrFail();
            $this->assertSame('Later Local Brand', $medicine->brand_name);
            $medicine->update(['brand_name' => 'Newer Web Brand']);
        });

        $send(3, 'Stale Desktop Brand', now()->subMinutes(5)->toISOString())
            ->assertJsonPath('data.results.0.code', 'server_newer');
        $this->tenant->run(function (): void {
            $this->assertSame('Newer Web Brand',
                Medicine::query()->where('medicine_code', 'MASTER-VERSION-MED')->value('brand_name'));
        });

        $send(4, 'Newest Desktop Brand', now()->addSeconds(2)->toISOString());
        $this->tenant->run(function (): void {
            $this->assertSame('Newest Desktop Brand',
                Medicine::query()->where('medicine_code', 'MASTER-VERSION-MED')->value('brand_name'));
        });
    }

    public function test_mobile_purpose_token_is_rejected_by_desktop_sync_boundary(): void
    {
        $now = now();

        $mobileToken = app(OfflineLeaseSigner::class)->sign([
            'v' => 1,
            'purpose' => 'mobile_access',
            'tenant_id' => $this->tenant->id,
            'activation_id' => $this->activationId,
            'device_id' => $this->deviceId,
            'user_id' => $this->userId,
            'issued_at' => $now->getTimestamp(),
            'expires_at' => $now->copy()->addHour()->getTimestamp(),
        ]);

        $this->withToken($mobileToken)
            ->getJson('/api/v1/desktop/sync/pull/medicines?limit=1')
            ->assertUnauthorized();
    }

    public function test_desktop_sale_push_is_idempotent(): void
    {
        $references = $this->tenant->run(function (): array {
            return [
                'medicine_id' => Medicine::query()
                    ->where('medicine_code', 'DESKTOP-SYNC-001')
                    ->value('id'),
                'location_id' => StockLocation::query()
                    ->where('code', 'MAIN')
                    ->value('id'),
            ];
        });

        $event = [
            'idempotency_key' => 'sale:desktop-sync-001:completed',
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => 'desktop-sync-001',
                'idempotency_key' => 'sale:desktop-sync-001:completed',
                'stock_location_id' => $references['location_id'],
                'customer_id' => null,
                'cashier_user_id' => (string) $this->userId,
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
            ->assertJsonPath('data.results.0.status', 'accepted')
            ->assertJsonPath('data.results.0.server_id', $serverId);

        $this->tenant->run(function () use ($serverId): void {
            $this->assertSame(
                1,
                Sale::query()
                    ->where('idempotency_key', 'sale:desktop-sync-001:completed')
                    ->count(),
            );
            $this->assertNotNull(Sale::query()->find($serverId));
        });
    }

    public function test_desktop_sale_push_resolves_local_reference_ids_by_stable_codes(): void
    {
        $event = [
            'idempotency_key' => 'sale:desktop-local-reference:completed',
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => 'desktop-local-reference',
                'idempotency_key' => 'sale:desktop-local-reference:completed',
                'stock_location_id' => 'local-location-uuid',
                'stock_location_code' => 'MAIN',
                'customer_id' => null,
                'cashier_user_id' => (string) $this->userId,
                'business_date' => today()->toDateString(),
                'currency' => 'AFN',
                'lines' => [[
                    'medicine_id' => 'local-medicine-uuid',
                    'medicine_code' => 'DESKTOP-SYNC-001',
                    'quantity' => '1.0000',
                    'unit_price' => '10.0000',
                    'discount_amount' => '0.0000',
                ]],
                'payments' => [[
                    'method' => 'cash',
                    'amount' => '10.0000',
                ]],
            ],
        ];

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted');

        $this->tenant->run(function (): void {
            $this->assertSame(
                1,
                Sale::query()
                    ->where('idempotency_key', 'sale:desktop-local-reference:completed')
                    ->count(),
            );
        });
    }

    public function test_desktop_historical_sale_can_sync_when_medicine_is_now_inactive(): void
    {
        $this->tenant->run(function (): void {
            Medicine::query()
                ->where('medicine_code', 'DESKTOP-SYNC-001')
                ->update(['is_active' => false]);
        });

        $event = [
            'idempotency_key' => 'sale:desktop-inactive-reference:completed',
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => 'desktop-inactive-reference',
                'idempotency_key' => 'sale:desktop-inactive-reference:completed',
                'stock_location_id' => 'local-location-uuid',
                'stock_location_code' => 'MAIN',
                'customer_id' => null,
                'cashier_user_id' => (string) $this->userId,
                'business_date' => today()->toDateString(),
                'currency' => 'AFN',
                'lines' => [[
                    'medicine_id' => 'local-inactive-medicine-uuid',
                    'medicine_code' => 'DESKTOP-SYNC-001',
                    'quantity' => '1.0000',
                    'unit_price' => '10.0000',
                    'discount_amount' => '0.0000',
                ]],
                'payments' => [[
                    'method' => 'cash',
                    'amount' => '10.0000',
                ]],
            ],
        ];

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'accepted');

        $this->tenant->run(function (): void {
            $this->assertSame(
                1,
                Sale::query()
                    ->where('idempotency_key', 'sale:desktop-inactive-reference:completed')
                    ->count(),
            );
        });
    }

    private function seedInventory(): void
    {
        $userId = $this->userId;

        $this->tenant->run(function () use ($userId): void {
            $medicine = Medicine::query()->create([
                'medicine_code' => 'DESKTOP-SYNC-001',
                'brand_name' => 'Desktop Sync Medicine',
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
                'batch_number' => 'DESKTOP-SYNC-LOT',
                'batch_key' => 'DESKTOP-SYNC-LOT',
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
                'desktop-sync:seed',
                $userId,
            );
        });
    }
}
