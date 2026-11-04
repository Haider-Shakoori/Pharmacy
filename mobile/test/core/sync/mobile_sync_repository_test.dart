import 'dart:convert';

import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_repository.dart';
import 'package:businessos_pharmacy/core/sync/sync_models.dart';
import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;
  late MobileSyncRepository repository;

  setUp(() async {
    database = PharmacyDatabase(NativeDatabase.memory());
    repository = MobileSyncRepository(database);

    final int now = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    await database.customStatement(
      '''
      INSERT INTO local_sales (
        local_id, stock_location_id, business_date, status, sync_state,
        subtotal, discount_amount, total, tendered_amount, change_amount,
        created_at, updated_at, completed_at
      ) VALUES (?, ?, ?, 'completed', 'pending', ?, ?, ?, ?, ?, ?, ?, ?)
      ''',
      <Object?>[
        'sale-local-1',
        'main',
        '2026-09-28',
        '10.0000',
        '0.0000',
        '10.0000',
        '10.0000',
        '0.0000',
        now,
        now,
        now,
      ],
    );

    await database.customStatement(
      '''
      INSERT INTO sync_outbox_entries (
        idempotency_key, aggregate_type, aggregate_id, event_type,
        payload_json, status, attempt_count, created_at, updated_at
      ) VALUES (?, 'sale', ?, 'sale.completed', ?, 'pending', 0, ?, ?)
      ''',
      <Object?>[
        'sale:sale-local-1:completed',
        'sale-local-1',
        jsonEncode(<String, Object?>{
          'local_id': 'sale-local-1',
          'idempotency_key': 'sale:sale-local-1:completed',
        }),
        now,
        now,
      ],
    );
  });

  tearDown(() async {
    await database.close();
  });

  test(
    'supplier and purchase order pull pages persist for offline browsing',
    () async {
      await repository.applyPullPage(
        const SyncPullPage(
          stream: 'suppliers',
          nextCursor: 'supplier-cursor',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'supplier-1',
              'code': 'SUP-1',
              'name': 'Afghan Medical',
              'phone': '0700000000',
              'contact_person': 'Sales',
              'is_active': true,
              'is_deleted': false,
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      );
      await repository.applyPullPage(
        const SyncPullPage(
          stream: 'purchase_orders',
          nextCursor: 'orders-cursor',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'order-1',
              'supplier_id': 'supplier-1',
              'number': 'PO-001',
              'status': 'draft',
              'currency': 'AFN',
              'grand_total': '150.0000',
              'lines': <Map<String, dynamic>>[
                <String, dynamic>{
                  'medicine_id': 'med-1',
                  'ordered_quantity': '3.0000',
                  'line_total': '150.0000',
                },
              ],
              'is_deleted': false,
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      );
      final supplier = await database
          .customSelect(
            "SELECT name FROM local_suppliers WHERE id = 'supplier-1'",
          )
          .getSingle();
      expect(supplier.read<String>('name'), 'Afghan Medical');
      final order = await database
          .customSelect(
            "SELECT lines_json FROM local_purchase_orders WHERE id = 'order-1'",
          )
          .getSingle();
      final lines =
          jsonDecode(order.read<String>('lines_json')) as List<dynamic>;
      expect(
        (lines.single as Map<String, dynamic>)['ordered_quantity'],
        '3.0000',
      );
      expect(await repository.checkpoint('purchase_orders'), 'orders-cursor');
    },
  );

  test(
    'server acknowledgement marks sale synced then removes outbox',
    () async {
      final SyncOutboxEvent event = (await repository.pendingOutbox()).single;

      await repository.acknowledge(
        event,
        const SyncPushResult(
          idempotencyKey: 'sale:sale-local-1:completed',
          status: 'accepted',
          serverId: 'server-sale-1',
        ),
      );

      final QueryRow sale = await database
          .customSelect(
            'SELECT server_id, sync_state FROM local_sales WHERE local_id = ?',
            variables: <Variable<Object>>[
              const Variable<String>('sale-local-1'),
            ],
          )
          .getSingle();

      expect(sale.read<String>('server_id'), 'server-sale-1');
      expect(sale.read<String>('sync_state'), 'synced');

      final int count = await database
          .customSelect('SELECT COUNT(*) AS c FROM sync_outbox_entries')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle();
      expect(count, 0);
    },
  );

  test('non-retryable rejection preserves outbox and local sale', () async {
    final SyncOutboxEvent event = (await repository.pendingOutbox()).single;

    await repository.reject(
      event,
      const SyncPushResult(
        idempotencyKey: 'sale:sale-local-1:completed',
        status: 'rejected',
        code: 'validation_failed',
        message: 'Insufficient stock.',
      ),
    );

    final QueryRow outbox = await database
        .customSelect('SELECT status, last_error FROM sync_outbox_entries')
        .getSingle();
    final QueryRow sale = await database
        .customSelect('SELECT sync_state FROM local_sales')
        .getSingle();

    expect(outbox.read<String>('status'), 'rejected');
    expect(outbox.read<String>('last_error'), 'Insufficient stock.');
    expect(sale.read<String>('sync_state'), 'rejected');
  });

  test('inventory pull preserves stock reserved by an unresolved offline sale', () async {
    final int now = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    await database.customStatement(
      '''
        INSERT INTO local_sale_lines (
          local_id, sale_local_id, medicine_id, quantity,
          unit_price, discount_amount, line_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ''',
      <Object?>[
        'line-local-1',
        'sale-local-1',
        'med-1',
        '2.0000',
        '10.0000',
        '0.0000',
        '20.0000',
      ],
    );
    await database.customStatement(
      '''
        INSERT INTO local_sale_batch_allocations (
          local_id, sale_line_local_id, product_batch_id,
          quantity, unit_cost, created_at
        ) VALUES (?, ?, ?, ?, ?, ?)
        ''',
      <Object?>[
        'allocation-local-1',
        'line-local-1',
        'batch-1',
        '2.0000',
        '4.0000',
        now,
      ],
    );

    await repository.applyPullPage(
      const SyncPullPage(
        stream: 'inventory',
        data: <Map<String, dynamic>>[
          <String, dynamic>{
            'id': 'batch-1',
            'medicine_id': 'med-1',
            'stock_location_id': 'main',
            'batch_number': 'LOT-1',
            'expires_at': '2027-01-01',
            'available_quantity': '20.0000',
            'sale_price': '10.0000',
            'purchase_cost': '4.0000',
            'status': 'active',
            'is_deleted': false,
            'server_created_at': '2026-09-01T00:00:00Z',
            'server_updated_at': '2026-09-28T10:00:00Z',
          },
        ],
        nextCursor: 'inventory-cursor-1',
        hasMore: false,
      ),
    );

    final QueryRow batch = await database
        .customSelect(
          'SELECT available_quantity FROM local_inventory_batches WHERE id = ?',
          variables: <Variable<Object>>[const Variable<String>('batch-1')],
        )
        .getSingle();

    expect(batch.read<String>('available_quantity'), '18.0000');
  });

  test('pull page is applied before checkpoint advances', () async {
    await repository.applyPullPage(
      const SyncPullPage(
        stream: 'medicines',
        data: <Map<String, dynamic>>[
          <String, dynamic>{
            'id': 'med-1',
            'medicine_code': 'MED-1',
            'brand_name': 'Synced Medicine',
            'generic_name': 'Generic',
            'sale_unit': 'tablet',
            'is_active': true,
            'is_deleted': false,
            'server_updated_at': '2026-09-28T10:00:00Z',
          },
        ],
        nextCursor: 'cursor-1',
        hasMore: false,
      ),
    );

    final QueryRow medicine = await database
        .customSelect(
          'SELECT brand_name FROM local_medicines WHERE id = ?',
          variables: <Variable<Object>>[const Variable<String>('med-1')],
        )
        .getSingle();

    expect(medicine.read<String>('brand_name'), 'Synced Medicine');
    expect(await repository.checkpoint('medicines'), 'cursor-1');
  });
  test('negative inventory snapshots do not advance the checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'inventory',
          nextCursor: 'invalid-inventory',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'batch-negative',
              'available_quantity': '-1.0000',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('inventory'), isNull);
    final rows = await database.customSelect(
      "SELECT COUNT(*) AS c FROM local_inventory_batches WHERE id = 'batch-negative'",
    ).getSingle();
    expect(rows.read<int>('c'), 0);
  });

  test('older supplier snapshots cannot replace newer local data', () async {
    Future<void> apply(String name, String timestamp) {
      return repository.applyPullPage(
        SyncPullPage(
          stream: 'suppliers',
          nextCursor: timestamp,
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'supplier-versioned',
              'name': name,
              'is_active': true,
              'is_deleted': false,
              'server_updated_at': timestamp,
            },
          ],
        ),
      );
    }

    await apply('New supplier name', '2026-11-04T10:00:00Z');
    await apply('Old supplier name', '2026-11-03T10:00:00Z');

    final QueryRow supplier = await database.customSelect(
      "SELECT name FROM local_suppliers WHERE id = 'supplier-versioned'",
    ).getSingle();
    expect(supplier.read<String>('name'), 'New supplier name');
  });

  test('outbox claims cannot overwrite an already sending event', () async {
    final event = (await repository.pendingOutbox()).single;
    await repository.markSending(<SyncOutboxEvent>[event]);
    await expectLater(
      repository.markSending(<SyncOutboxEvent>[event]),
      throwsA(isA<StateError>()),
    );
    final row = await database.customSelect(
      'SELECT status, attempt_count FROM sync_outbox_entries',
    ).getSingle();
    expect(row.read<String>('status'), 'sending');
    expect(row.read<int>('attempt_count'), 1);
  });

  test('outbox rejects invalid batch sizes', () async {
    await expectLater(repository.pendingOutbox(limit: 0), throwsRangeError);
    await expectLater(repository.pendingOutbox(limit: 501), throwsRangeError);
  });

}
