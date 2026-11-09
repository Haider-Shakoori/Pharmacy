<?php

namespace Tests\Feature\Mobile;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\LicenseActivation;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\StockTransferService;
use App\Services\Mobile\MobileGoodsReceiptService;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_cashier_can_list_active_stock_locations_but_cannot_create_them(): void
    {
        $response = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/stock-locations')
            ->assertOk();

        $this->assertContains('MAIN', collect($response->json('data'))->pluck('code')->all());

        $branchId = $this->tenant->run(
            fn () => Branch::query()->where('code', 'SYNC')->value('id')
        );

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/stock-location-branches')
            ->assertForbidden();

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/stock-locations', [
                'branch_id' => $branchId,
                'code' => 'NEW',
                'name' => 'New Warehouse',
                'kind' => 'warehouse',
            ])
            ->assertForbidden();

        $this->tenant->run(function (): void {
            $this->assertDatabaseMissing('stock_locations', ['code' => 'NEW']);
        });
    }

    public function test_stock_transfer_preserves_totals_and_is_idempotent(): void
    {
        $this->tenant->run(function (): void {
            $source = ProductBatch::query()->where('batch_key', 'SYNC-LOT')->firstOrFail();
            $destination = StockLocation::query()->create([
                'branch_id' => $source->branch_id,
                'code' => 'SECOND',
                'name' => 'Second Store',
                'kind' => 'store',
                'is_default' => false,
                'is_active' => true,
            ]);
            $service = app(StockTransferService::class);
            $before = ProductBatch::query()->sum('available_quantity');
            $first = $service->transfer($source->id, $destination->id, '5.0000', 'transfer-test-1', null);
            $again = $service->transfer($source->id, $destination->id, '5.0000', 'transfer-test-1', null);

            $this->assertFalse($first['replayed']);
            $this->assertTrue($again['replayed']);
            $this->assertSame($first['source_movement_id'], $again['source_movement_id']);
            $this->assertEquals('15.0000', $source->fresh()->available_quantity);
            $this->assertEquals('5.0000', ProductBatch::query()
                ->where('stock_location_id', $destination->id)->firstOrFail()->available_quantity);
            $this->assertEquals($before, ProductBatch::query()->sum('available_quantity'));
            $this->assertSame(2, StockMovement::query()
                ->where('source_id', 'transfer-test-1')->count());

            try {
                $service->transfer($source->id, $destination->id, '6.0000', 'transfer-test-1', null);
                $this->fail('Reused key with different quantity must be rejected');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('request_id', $exception->errors());
            }
            $this->assertEquals($before, ProductBatch::query()->sum('available_quantity'));
        });
    }

    public function test_stock_transfer_rejects_insufficient_quantity_without_movements(): void
    {
        $this->tenant->run(function (): void {
            $source = ProductBatch::query()->where('batch_key', 'SYNC-LOT')->firstOrFail();
            $destination = StockLocation::query()->create([
                'branch_id' => $source->branch_id,
                'code' => 'THIRD',
                'name' => 'Third Store',
                'kind' => 'store',
                'is_active' => true,
            ]);
            $before = StockMovement::query()->count();
            try {
                app(StockTransferService::class)->transfer(
                    $source->id, $destination->id, '999.0000', 'transfer-too-large', null
                );
                $this->fail('Insufficient quantity must be rejected');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('quantity', $exception->errors());
            }
            $this->assertSame($before, StockMovement::query()->count());
        });
    }

    public function test_cashier_cannot_access_transfer_batch_inventory(): void
    {
        $locationId = $this->tenant->run(
            fn () => StockLocation::query()->where('code', 'MAIN')->value('id')
        );

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/stock-location-batches?stock_location_id='.$locationId)
            ->assertForbidden();
    }

    public function test_stock_location_endpoints_require_mobile_authentication(): void
    {
        $this->withToken('')
            ->getJson('/api/v1/mobile/stock-locations')
            ->assertUnauthorized();

        $this->withToken('')
            ->getJson('/api/v1/mobile/stock-location-batches?stock_location_id=invalid')
            ->assertUnauthorized();

        $this->withToken('')
            ->getJson('/api/v1/mobile/stock-location-branches')
            ->assertUnauthorized();

        $this->withToken('')
            ->postJson('/api/v1/mobile/stock-locations', [
                'branch_id' => 'not-a-branch',
                'code' => 'NEW',
                'name' => 'New Warehouse',
                'kind' => 'warehouse',
            ])
            ->assertUnauthorized();
    }

    public function test_revoked_device_cannot_pull_or_refresh_with_existing_token(): void
    {
        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/medicines?limit=1')
            ->assertOk();

        LicenseActivation::query()
            ->where('device_id', $this->deviceId)
            ->update(['revoked_at' => now()]);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/medicines?limit=1')
            ->assertUnauthorized();

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/session/refresh')
            ->assertUnauthorized();
    }

    public function test_deactivated_pharmacy_user_cannot_sync_or_refresh_existing_session(): void
    {
        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/medicines?limit=1')
            ->assertOk();

        $this->tenant->run(function (): void {
            User::query()->where('email', 'mobile@sync.test')->update(['is_active' => false]);
        });

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/medicines?limit=1')
            ->assertUnauthorized();

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/session/refresh')
            ->assertUnauthorized();
    }

    public function test_mobile_catalog_pull_does_not_leak_another_tenants_medicines(): void
    {
        $otherTenant = $this->createTenant([
            'name' => 'Separate Pharmacy',
            'slug' => 'separate-mobile-pharmacy',
        ]);
        $otherTenant->run(function (): void {
            Medicine::query()->create([
                'medicine_code' => 'OTHER-TENANT-ONLY',
                'brand_name' => 'Other Tenant Secret Medicine',
                'sale_unit' => 'tablet',
            ]);
        });

        $response = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/medicines?limit=250')
            ->assertOk();

        $codes = collect($response->json('data.data'))->pluck('medicine_code')->all();
        $this->assertContains('SYNC-001', $codes);
        $this->assertNotContains('OTHER-TENANT-ONLY', $codes);
    }

    public function test_mobile_sync_rejects_duplicate_event_keys_in_one_batch(): void
    {
        $event = [
            'idempotency_key' => 'duplicate:mobile-event-001',
            'event_type' => 'sale.completed',
            'payload' => ['local_id' => 'mobile-event-001'],
        ];

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [$event, $event],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'events.0.idempotency_key',
                'events.1.idempotency_key',
            ]);
    }

    public function test_duplicate_batch_keys_do_not_partially_create_sales(): void
    {
        $references = $this->tenant->run(function (): array {
            return [
                'medicine_id' => Medicine::query()
                    ->where('medicine_code', 'SYNC-001')->value('id'),
                'location_id' => StockLocation::query()
                    ->where('code', 'MAIN')->value('id'),
            ];
        });

        $key = 'sale:duplicate-batch-001:completed';
        $event = [
            'idempotency_key' => $key,
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => 'duplicate-batch-001',
                'idempotency_key' => $key,
                'stock_location_id' => $references['location_id'],
                'customer_id' => null,
                'cashier_user_id' => '1',
                'business_date' => today()->toDateString(),
                'currency' => 'AFN',
                'lines' => [[
                    'medicine_id' => $references['medicine_id'],
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
            ->postJson('/api/v1/mobile/sync/push', ['events' => [$event, $event]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'events.0.idempotency_key',
                'events.1.idempotency_key',
            ]);

        $this->tenant->run(function () use ($key): void {
            $this->assertSame(0, Sale::query()->where('idempotency_key', $key)->count());
        });
    }

    public function test_mobile_sync_rejects_sale_payload_key_mismatch_without_creating_sale(): void
    {
        $key = 'sale:mismatched-mobile-key:completed';
        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [[
                    'idempotency_key' => $key,
                    'event_type' => 'sale.completed',
                    'payload' => [
                        'local_id' => 'mismatched-mobile-key',
                        'idempotency_key' => 'sale:another-key:completed',
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.idempotency_key', $key)
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.code', 'idempotency_mismatch')
            ->assertJsonPath('data.results.0.retryable', false);

        $this->tenant->run(function () use ($key): void {
            $this->assertSame(0, Sale::query()->where('idempotency_key', $key)->count());
        });
    }

    public function test_mobile_sync_rejects_sale_claimed_by_another_cashier_without_side_effects(): void
    {
        $key = 'sale:foreign-cashier-001:completed';

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [[
                    'idempotency_key' => $key,
                    'event_type' => 'sale.completed',
                    'payload' => [
                        'local_id' => 'foreign-cashier-001',
                        'idempotency_key' => $key,
                        'cashier_user_id' => 'different-user',
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.code', 'cashier_mismatch')
            ->assertJsonPath('data.results.0.retryable', false);

        $this->tenant->run(function () use ($key): void {
            $this->assertSame(0, Sale::query()->where('idempotency_key', $key)->count());
        });
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

    public function test_unbalanced_offline_sale_is_rejected_without_creating_sale(): void
    {
        $references = $this->tenant->run(function (): array {
            return [
                'medicine_id' => Medicine::query()->where('medicine_code', 'SYNC-001')->value('id'),
                'location_id' => StockLocation::query()->where('code', 'MAIN')->value('id'),
                'cashier_id' => User::query()->where('email', 'mobile@sync.test')->value('id'),
            ];
        });

        $key = 'sale:unbalanced-mobile-001:completed';
        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [[
                    'idempotency_key' => $key,
                    'event_type' => 'sale.completed',
                    'payload' => [
                        'v' => 1,
                        'local_id' => 'unbalanced-mobile-001',
                        'idempotency_key' => $key,
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
                            'amount' => '5.0000',
                        ]],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.retryable', false);

        $this->assertNotEmpty($response->json('data.results.0.code'));
        $this->tenant->run(function () use ($key): void {
            $this->assertSame(0, Sale::query()->where('idempotency_key', $key)->count());
        });
    }

    public function test_offline_sale_with_insufficient_server_stock_returns_stock_conflict(): void
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
            'idempotency_key' => 'sale:mobile-stock-conflict:completed',
            'event_type' => 'sale.completed',
            'payload' => [
                'v' => 1,
                'local_id' => 'mobile-stock-conflict',
                'idempotency_key' => 'sale:mobile-stock-conflict:completed',
                'stock_location_id' => $references['location_id'],
                'customer_id' => null,
                'cashier_user_id' => '1',
                'business_date' => today()->toDateString(),
                'currency' => 'AFN',
                'lines' => [[
                    'medicine_id' => $references['medicine_id'],
                    'quantity' => '25.0000',
                    'unit_price' => '10.0000',
                    'discount_amount' => '0.0000',
                    'batch_allocations' => [],
                ]],
                'payments' => [[
                    'method' => 'cash',
                    'amount' => '250.0000',
                ]],
            ],
        ];

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.code', 'stock_conflict')
            ->assertJsonPath('data.results.0.retryable', false);

        $this->tenant->run(function (): void {
            $this->assertSame(
                0,
                Sale::query()
                    ->where('idempotency_key', 'sale:mobile-stock-conflict:completed')
                    ->count(),
            );
        });
    }

    public function test_sync_pull_rejects_pages_above_low_bandwidth_limit(): void
    {
        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/medicines?limit=251')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
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

    public function test_supplier_sync_pull_is_tenant_scoped_and_cursor_paginated(): void
    {
        $this->tenant->run(function (): void {
            Supplier::query()->create([
                'code' => 'SUP-A',
                'name' => 'Alpha Medical',
                'phone' => '0700000001',
                'is_active' => true,
            ]);
            $second = Supplier::query()->create([
                'code' => 'SUP-B',
                'name' => 'Beta Medical',
                'is_active' => false,
            ]);
            $second->timestamps = false;
            $second->updated_at = now()->addMinutes(5);
            $second->save();
        });

        $first = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/suppliers?limit=1')
            ->assertOk()
            ->assertJsonPath('data.stream', 'suppliers')
            ->assertJsonPath('data.has_more', true);

        $this->assertCount(1, $first->json('data.data'));
        $this->assertArrayHasKey('is_active', $first->json('data.data.0'));
        $cursor = $first->json('data.next_cursor');
        $this->assertNotEmpty($cursor);

        $second = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/suppliers?limit=1&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.stream', 'suppliers');

        $this->assertCount(1, $second->json('data.data'));
        $this->assertNotSame(
            $first->json('data.data.0.id'),
            $second->json('data.data.0.id'),
        );
    }

    public function test_purchase_order_pull_includes_lines_and_amounts(): void
    {
        $this->tenant->run(function (): void {
            $supplier = Supplier::query()->create([
                'code' => 'PO-SUP',
                'name' => 'Purchase Test Supplier',
                'is_active' => true,
            ]);
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->id,
                'number' => 'PO-MOBILE-001',
                'created_by' => User::query()->where('email', 'mobile@sync.test')->value('id'),
                'status' => 'draft',
                'order_date' => today(),
                'currency' => 'AFN',
                'subtotal' => '100.0000',
                'discount_total' => '0.0000',
                'landed_cost_total' => '0.0000',
                'grand_total' => '100.0000',
            ]);
            $order->lines()->create([
                'medicine_id' => Medicine::query()->where('medicine_code', 'SYNC-001')->value('id'),
                'description' => 'Sync Medicine',
                'ordered_quantity' => '10.0000',
                'received_quantity' => '0.0000',
                'unit_cost' => '10.0000',
                'discount_amount' => '0.0000',
                'landed_cost_allocated' => '0.0000',
                'line_total' => '100.0000',
            ]);
        });

        $response = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/sync/pull/purchase_orders?limit=10')
            ->assertOk()
            ->assertJsonPath('data.stream', 'purchase_orders');

        $orders = collect($response->json('data.data'));
        $order = $orders->firstWhere('number', 'PO-MOBILE-001');
        $this->assertNotNull($order);
        $this->assertSame('100.0000', $order['grand_total']);
        $this->assertCount(1, $order['lines']);
        $this->assertSame('10.0000', $order['lines'][0]['ordered_quantity']);
    }

    public function test_cashier_cannot_capture_goods_receipt_through_mobile_sync(): void
    {
        $event = [
            'idempotency_key' => 'grn:unauthorized-cashier-001',
            'event_type' => 'goods_receipt.create',
            'payload' => [
                'local_id' => 'grn:unauthorized-cashier-001',
                'idempotency_key' => 'grn:unauthorized-cashier-001',
                'purchase_order_id' => '01J00000000000000000000000',
                'received_at' => now()->toISOString(),
                'lines' => [[
                    'purchase_order_line_id' => '01J00000000000000000000001',
                    'received_quantity' => '1',
                    'bonus_quantity' => '0',
                    'unit_cost' => '10',
                ]],
            ],
        ];

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/sync/push', [
                'events' => [$event],
            ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.code', 'permission_denied')
            ->assertJsonPath('data.results.0.retryable', false);
    }

    public function test_goods_receipt_capture_is_idempotent_and_does_not_post_inventory(): void
    {
        $this->tenant->run(function (): void {
            $supplier = Supplier::query()->create([
                'code' => 'GRN-SUP',
                'name' => 'Receipt Supplier',
                'is_active' => true,
            ]);
            $user = User::query()->where('email', 'mobile@sync.test')->firstOrFail();
            $medicine = Medicine::query()->where('medicine_code', 'SYNC-001')->firstOrFail();
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->id,
                'number' => 'PO-GRN-001',
                'created_by' => $user->id,
                'status' => 'approved',
                'order_date' => today(),
                'currency' => 'AFN',
                'subtotal' => '100.0000',
                'discount_total' => '0.0000',
                'landed_cost_total' => '0.0000',
                'grand_total' => '100.0000',
            ]);
            $line = $order->lines()->create([
                'medicine_id' => $medicine->id,
                'description' => 'Sync Medicine',
                'ordered_quantity' => '10.0000',
                'received_quantity' => '0.0000',
                'unit_cost' => '10.0000',
                'discount_amount' => '0.0000',
                'landed_cost_allocated' => '0.0000',
                'line_total' => '100.0000',
            ]);
            $payload = [
                'purchase_order_id' => $order->id,
                'received_at' => now()->toISOString(),
                'lines' => [[
                    'purchase_order_line_id' => $line->id,
                    'received_quantity' => '4',
                    'bonus_quantity' => '0',
                    'unit_cost' => '10',
                    'batch_number' => 'GRN-LOT',
                    'expires_at' => today()->addMonths(6)->toDateString(),
                ]],
            ];
            $service = app(MobileGoodsReceiptService::class);
            $before = ProductBatch::query()->where('medicine_id', $medicine->id)->sum('available_quantity');
            $first = $service->capture($user, $payload, 'grn:integration-001');
            $again = $service->capture($user, $payload, 'grn:integration-001');
            $this->assertSame($first->id, $again->id);
            $this->assertSame('pending_inventory', $first->status);
            $this->assertNull($first->inventory_posted_at);
            $this->assertSame(1, GoodsReceipt::query()->where('idempotency_key', 'grn:integration-001')->count());
            $this->assertEquals($before, ProductBatch::query()->where('medicine_id', $medicine->id)->sum('available_quantity'));

            $over = $payload;
            $over['lines'][0]['received_quantity'] = '7';
            $this->expectException(ValidationException::class);
            $service->capture($user, $over, 'grn:integration-002');
        });
    }

    public function test_session_refresh_renews_access_without_plaintext_license_key(): void
    {
        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/session/refresh')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'mobile@sync.test')
            ->assertJsonPath('data.device_id', $this->deviceId)
            ->assertJsonPath('data.offline_lease.public_key', config('pharmacy.license.signing_public_key'));

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
