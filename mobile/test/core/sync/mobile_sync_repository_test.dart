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

  test('malformed boolean flags roll back snapshots and checkpoints', () async {
    for (final entry in <(String, String, Map<String, dynamic>)>[
      ('suppliers', 'is_active', <String, dynamic>{
        'id': 'supplier-invalid', 'name': 'Supplier',
      }),
      ('medicines', 'is_deleted', <String, dynamic>{
        'id': 'medicine-invalid', 'brand_name': 'Medicine',
      }),
      ('inventory', 'is_deleted', <String, dynamic>{
        'id': 'batch-invalid', 'medicine_id': 'medicine-invalid',
        'stock_location_id': 'main', 'available_quantity': '1',
      }),
      ('customers', 'is_deleted', <String, dynamic>{
        'id': 'customer-invalid', 'name': 'Customer',
      }),
      ('purchase_orders', 'is_deleted', <String, dynamic>{
        'id': 'order-invalid', 'supplier_id': 'supplier-invalid',
        'number': 'PO-1', 'status': 'approved',
      }),
    ]) {
      final (stream, field, fields) = entry;
      await expectLater(
        repository.applyPullPage(SyncPullPage(
          stream: stream,
          nextCursor: 'bad-bool',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              ...fields,
              field: 'false',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        )),
        throwsFormatException,
      );
      expect(await repository.checkpoint(stream), isNull);
    }
  });

  test('empty unsupported pull page cannot create a checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'unexpected',
          nextCursor: 'unsafe',
          hasMore: false,
          data: <Map<String, dynamic>>[],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('unexpected'), isNull);
  });

  test('missing continuation cursor cannot persist a partial page', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'suppliers',
          nextCursor: null,
          hasMore: true,
          data: <Map<String, dynamic>>[],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('suppliers'), isNull);
  });

  test('missing purchase order status preserves checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'purchase_orders',
          nextCursor: 'bad-status',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'order-1',
              'supplier_id': 'supplier-1',
              'number': 'PO-1',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('purchase_orders'), isNull);
  });

  test('blank purchase order currency rolls back checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'purchase_orders',
          nextCursor: 'bad-currency',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'po-1',
              'supplier_id': 'supplier-1',
              'number': 'PO-1',
              'status': 'approved',
              'currency': ' ',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('purchase_orders'), isNull);
  });

  test('blank medicine sale unit rolls back checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'medicines',
          nextCursor: 'bad-unit',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'med-1',
              'brand_name': 'Medicine',
              'sale_unit': ' ',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('medicines'), isNull);
  });

  test('direct final pull page rejects blank checkpoint cursor', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'suppliers',
          nextCursor: '  ',
          hasMore: false,
          data: <Map<String, dynamic>>[],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('suppliers'), isNull);
  });

  test('malformed customer balance does not persist checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'customers',
          nextCursor: 'bad-balance',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'customer-1',
              'name': 'Test Customer',
              'balance': 'not-a-number',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('customers'), isNull);
  });

  test('blank inventory status cannot persist checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'inventory',
          nextCursor: 'bad-status',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'batch-1',
              'medicine_id': 'medicine-1',
              'stock_location_id': 'main',
              'available_quantity': '1',
              'status': ' ',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('inventory'), isNull);
  });

  test('negative purchase order total rolls back checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'purchase_orders',
          nextCursor: 'negative-total',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'po-negative',
              'supplier_id': 'supplier-1',
              'number': 'PO-NEG',
              'status': 'approved',
              'grand_total': '-1',
              'lines': <Map<String, dynamic>>[],
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('purchase_orders'), isNull);
  });

  test('malformed order lines do not advance checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'purchase_orders',
          nextCursor: 'bad-lines',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'po-1',
              'supplier_id': 'supplier-1',
              'number': 'PO-1',
              'status': 'approved',
              'lines': 'invalid',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('purchase_orders'), isNull);
  });

  test('blank supplier name rolls back synchronization', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'suppliers',
          nextCursor: 'invalid-name',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'supplier-1',
              'name': ' ',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('suppliers'), isNull);
  });

  test('invalid supplier identity rolls back entire pull page and checkpoint', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'suppliers',
          nextCursor: 'unsafe-cursor',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'supplier-valid',
              'name': 'Valid supplier',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
            <String, dynamic>{
              'id': '   ',
              'name': 'Invalid supplier',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    final count = await database.customSelect(
      'SELECT COUNT(*) AS total FROM local_suppliers',
    ).getSingle();
    expect(count.read<int>('total'), 0);
    expect(await repository.checkpoint('suppliers'), isNull);
  });

  test('purchase order with missing supplier does not advance cursor', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'purchase_orders',
          nextCursor: 'invalid-order-cursor',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'order-1',
              'supplier_id': ' ',
              'number': 'PO-1',
              'status': 'approved',
              'server_updated_at': '2026-11-03T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    expect(await repository.checkpoint('purchase_orders'), isNull);
  });

  test(
    'server acknowledgement marks sale synced then removes outbox',
    () async {
      final SyncOutboxEvent event = (await repository.pendingOutbox()).single;

      await repository.markSending(<SyncOutboxEvent>[event]);
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

  test('acknowledgement rolls back if matching outbox entry is absent', () async {
    final SyncOutboxEvent event = (await repository.pendingOutbox()).single;
    await database.customStatement(
      'DELETE FROM sync_outbox_entries WHERE idempotency_key = ?',
      <Object?>[event.idempotencyKey],
    );
    await expectLater(
      repository.acknowledge(
        event,
        const SyncPushResult(
          idempotencyKey: 'sale:sale-local-1:completed',
          status: 'accepted',
          serverId: 'server-sale-1',
        ),
      ),
      throwsStateError,
    );
    final QueryRow sale = await database.customSelect(
      "SELECT server_id, sync_state FROM local_sales WHERE local_id = 'sale-local-1'",
    ).getSingle();
    expect(sale.readNullable<String>('server_id'), isNull);
    expect(sale.read<String>('sync_state'), 'pending');
  });

  test('stale accepted acknowledgement cannot synchronize a pending sale', () async {
    final SyncOutboxEvent event = (await repository.pendingOutbox()).single;
    await expectLater(
      repository.acknowledge(
        event,
        const SyncPushResult(
          idempotencyKey: 'sale:sale-local-1:completed',
          status: 'accepted',
          serverId: 'server-sale-1',
        ),
      ),
      throwsStateError,
    );
    final QueryRow sale = await database.customSelect(
      "SELECT sync_state, server_id FROM local_sales WHERE local_id = 'sale-local-1'",
    ).getSingle();
    expect(sale.read<String>('sync_state'), 'pending');
    expect(sale.readNullable<String>('server_id'), isNull);
    expect((await repository.pendingOutbox()).single.idempotencyKey,
        event.idempotencyKey);
  });

  test('invalid acknowledgements preserve the sale and outbox', () async {
    final event = (await repository.pendingOutbox()).single;
    for (final result in <SyncPushResult>[
      const SyncPushResult(
        idempotencyKey: 'another-event',
        status: 'accepted',
        serverId: 'server-sale-1',
      ),
      const SyncPushResult(
        idempotencyKey: 'sale:sale-local-1:completed',
        status: 'rejected',
        serverId: 'server-sale-1',
      ),
    ]) {
      await expectLater(
        repository.acknowledge(event, result),
        throwsFormatException,
      );
    }
    final sale = await database.customSelect(
      "SELECT sync_state, server_id FROM local_sales WHERE local_id = 'sale-local-1'",
    ).getSingle();
    expect(sale.read<String>('sync_state'), 'pending');
    expect(sale.readNullable<String>('server_id'), isNull);
    expect((await repository.pendingOutbox()).single.idempotencyKey,
        event.idempotencyKey);
  });


  test('invalid rejections cannot change pending sale or outbox', () async {
    final event = (await repository.pendingOutbox()).single;
    for (final result in <SyncPushResult>[
      const SyncPushResult(
        idempotencyKey: 'wrong-event',
        status: 'rejected',
        message: 'Wrong event.',
      ),
      const SyncPushResult(
        idempotencyKey: 'sale:sale-local-1:completed',
        status: 'accepted',
        serverId: 'server-sale-1',
      ),
    ]) {
      await expectLater(repository.reject(event, result), throwsFormatException);
    }
    final outbox = await database.customSelect(
      'SELECT status, last_error FROM sync_outbox_entries',
    ).getSingle();
    expect(outbox.read<String>('status'), 'pending');
    expect(outbox.readNullable<String>('last_error'), isNull);
    final sale = await database.customSelect(
      "SELECT sync_state FROM local_sales WHERE local_id = 'sale-local-1'",
    ).getSingle();
    expect(sale.read<String>('sync_state'), 'pending');
  });


  test('rejection rolls back when the matching local sale is missing', () async {
    final SyncOutboxEvent event = (await repository.pendingOutbox()).single;
    await repository.markSending(<SyncOutboxEvent>[event]);
    await database.customStatement(
      'DELETE FROM local_sales WHERE local_id = ?',
      <Object?>[event.aggregateId],
    );

    await expectLater(
      repository.reject(
        event,
        const SyncPushResult(
          idempotencyKey: 'sale:sale-local-1:completed',
          status: 'rejected',
          message: 'Rejected remotely.',
        ),
      ),
      throwsStateError,
    );

    final QueryRow outbox = await database.customSelect(
      'SELECT status, last_error FROM sync_outbox_entries',
    ).getSingle();
    expect(outbox.read<String>('status'), 'sending');
    expect(outbox.readNullable<String>('last_error'), isNull);
  });

  test('blank persisted event identity prevents outbox claim', () async {
    await database.customStatement(
      "UPDATE sync_outbox_entries SET aggregate_id = '   '",
    );
    await expectLater(repository.pendingOutbox(), throwsFormatException);
    final QueryRow row = await database.customSelect(
      'SELECT status, attempt_count FROM sync_outbox_entries',
    ).getSingle();
    expect(row.read<String>('status'), 'pending');
    expect(row.read<int>('attempt_count'), 0);
  });

  test('malformed queued payload rolls back expired lease recovery', () async {
    final int expired = DateTime.now().millisecondsSinceEpoch ~/ 1000 - 600;
    await database.customStatement(
      "UPDATE sync_outbox_entries SET status = 'sending', updated_at = ?, payload_json = ?",
      <Object?>[expired, 'not-json'],
    );
    await expectLater(repository.pendingOutbox(), throwsFormatException);
    final QueryRow row = await database.customSelect(
      'SELECT status, updated_at FROM sync_outbox_entries',
    ).getSingle();
    expect(row.read<String>('status'), 'sending');
    expect(row.read<int>('updated_at'), expired);
  });

  test('outbox claim rejects mismatched aggregate without changing status', () async {
    final SyncOutboxEvent original = (await repository.pendingOutbox()).single;
    final SyncOutboxEvent mismatched = SyncOutboxEvent(
      idempotencyKey: original.idempotencyKey,
      aggregateId: 'another-sale',
      eventType: original.eventType,
      payload: original.payload,
    );
    await expectLater(
      repository.markSending(<SyncOutboxEvent>[mismatched]),
      throwsStateError,
    );
    final QueryRow outbox = await database.customSelect(
      'SELECT status, attempt_count FROM sync_outbox_entries',
    ).getSingle();
    expect(outbox.read<String>('status'), 'pending');
    expect(outbox.read<int>('attempt_count'), 0);
  });

  test('stale rejection cannot alter a pending sale', () async {
    final SyncOutboxEvent event = (await repository.pendingOutbox()).single;
    await expectLater(
      repository.reject(
        event,
        const SyncPushResult(
          idempotencyKey: 'sale:sale-local-1:completed',
          status: 'rejected',
          message: 'Late rejection.',
        ),
      ),
      throwsStateError,
    );
    final QueryRow outbox = await database.customSelect(
      'SELECT status, last_error FROM sync_outbox_entries',
    ).getSingle();
    final QueryRow sale = await database.customSelect(
      'SELECT sync_state FROM local_sales',
    ).getSingle();
    expect(outbox.read<String>('status'), 'pending');
    expect(outbox.readNullable<String>('last_error'), isNull);
    expect(sale.read<String>('sync_state'), 'pending');
  });

  test('non-retryable rejection preserves outbox and local sale', () async {
    final SyncOutboxEvent event = (await repository.pendingOutbox()).single;

    await repository.markSending(<SyncOutboxEvent>[event]);
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

  test('mixed inventory page rolls back earlier valid batches on error', () async {
    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'inventory',
          nextCursor: 'should-not-commit',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'valid-first',
              'medicine_id': 'med-1',
              'stock_location_id': 'main',
              'available_quantity': '5.0000',
              'sale_price': '10.0000',
              'purchase_cost': '4.0000',
              'server_updated_at': '2026-11-04T00:00:00Z',
            },
            <String, dynamic>{
              'id': 'invalid-second',
              'available_quantity': '-2.0000',
            },
          ],
        ),
      ),
      throwsFormatException,
    );
    final count = await database.customSelect(
      "SELECT COUNT(*) AS c FROM local_inventory_batches WHERE id IN ('valid-first', 'invalid-second')",
    ).map((row) => row.read<int>('c')).getSingle();
    expect(count, 0);
    expect(await repository.checkpoint('inventory'), isNull);
  });


  test('missing inventory quantity cannot overwrite cached stock', () async {
    await repository.applyPullPage(
      const SyncPullPage(
        stream: 'inventory',
        nextCursor: 'valid-stock',
        hasMore: false,
        data: <Map<String, dynamic>>[
          <String, dynamic>{
            'id': 'batch-protected',
            'medicine_id': 'med-1',
            'stock_location_id': 'main',
            'available_quantity': '7.0000',
            'sale_price': '10.0000',
            'purchase_cost': '4.0000',
            'server_updated_at': '2026-11-03T00:00:00Z',
          },
        ],
      ),
    );

    await expectLater(
      repository.applyPullPage(
        const SyncPullPage(
          stream: 'inventory',
          nextCursor: 'invalid-stock',
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'batch-protected',
              'medicine_id': 'med-1',
              'stock_location_id': 'main',
              'server_updated_at': '2026-11-04T00:00:00Z',
            },
          ],
        ),
      ),
      throwsFormatException,
    );

    final row = await database.customSelect(
      "SELECT available_quantity FROM local_inventory_batches WHERE id = 'batch-protected'",
    ).getSingle();
    expect(row.read<String>('available_quantity'), '7.0000');
    expect(await repository.checkpoint('inventory'), 'valid-stock');
  });


  test('missing inventory identifiers roll back the entire pull page', () async {
    for (final invalid in <Map<String, dynamic>>[
      <String, dynamic>{
        'medicine_id': 'med-1',
        'stock_location_id': 'main',
      },
      <String, dynamic>{
        'id': 'batch-missing-medicine',
        'stock_location_id': 'main',
      },
      <String, dynamic>{
        'id': 'batch-missing-location',
        'medicine_id': 'med-1',
      },
    ]) {
      await expectLater(
        repository.applyPullPage(
          SyncPullPage(
            stream: 'inventory',
            nextCursor: 'invalid-identifiers',
            hasMore: false,
            data: <Map<String, dynamic>>[
              <String, dynamic>{
                ...invalid,
                'available_quantity': '5.0000',
                'server_updated_at': '2026-11-04T00:00:00Z',
              },
            ],
          ),
        ),
        throwsFormatException,
      );
    }
    expect(await repository.checkpoint('inventory'), isNull);
    final count = await database.customSelect(
      'SELECT COUNT(*) AS c FROM local_inventory_batches',
    ).map((row) => row.read<int>('c')).getSingle();
    expect(count, 0);
  });


  test('missing server update timestamp cannot advance supplier checkpoint', () async {
    for (final invalid in <Object?>[null, '', ' ', 123]) {
      await expectLater(
        repository.applyPullPage(
          SyncPullPage(
            stream: 'suppliers',
            nextCursor: 'invalid-update-time',
            hasMore: false,
            data: <Map<String, dynamic>>[
              <String, dynamic>{
                'id': 'supplier-1',
                'name': 'Supplier',
                'server_updated_at': invalid,
              },
            ],
          ),
        ),
        throwsFormatException,
      );
    }
    expect(await repository.checkpoint('suppliers'), isNull);
    final count = await database.customSelect(
      "SELECT COUNT(*) AS c FROM local_suppliers WHERE id = 'supplier-1'",
    ).map((row) => row.read<int>('c')).getSingle();
    expect(count, 0);
  });

  test('invalid inventory timestamp types and dates preserve checkpoint', () async {
    for (final field in <String>['expires_at', 'server_created_at']) {
      for (final invalid in <Object>[123, 'not-a-date']) {
        await expectLater(
          repository.applyPullPage(
            SyncPullPage(
              stream: 'inventory',
              nextCursor: 'invalid-inventory-date',
              hasMore: false,
              data: <Map<String, dynamic>>[
                <String, dynamic>{
                  'id': 'batch-invalid-date',
                  'medicine_id': 'med-1',
                  'stock_location_id': 'main',
                  'available_quantity': '2',
                  'server_updated_at': '2026-11-04T00:00:00Z',
                  field: invalid,
                },
              ],
            ),
          ),
          throwsFormatException,
        );
      }
    }
    expect(await repository.checkpoint('inventory'), isNull);
    final count = await database.customSelect(
      "SELECT COUNT(*) AS c FROM local_inventory_batches WHERE id = 'batch-invalid-date'",
    ).map((row) => row.read<int>('c')).getSingle();
    expect(count, 0);
  });

  test('blank inventory timestamps roll back pull pages', () async {
    for (final field in <String>['expires_at', 'server_created_at']) {
      await expectLater(
        repository.applyPullPage(
          SyncPullPage(
            stream: 'inventory',
            nextCursor: 'invalid-time',
            hasMore: false,
            data: <Map<String, dynamic>>[
              <String, dynamic>{
                'id': 'batch-blank-time',
                'medicine_id': 'med-1',
                'stock_location_id': 'main',
                'available_quantity': '4',
                'server_updated_at': '2026-11-04T00:00:00Z',
                field: ' ',
              },
            ],
          ),
        ),
        throwsFormatException,
      );
    }
    expect(await repository.checkpoint('inventory'), isNull);
    final count = await database.customSelect(
      "SELECT COUNT(*) AS c FROM local_inventory_batches WHERE id = 'batch-blank-time'",
    ).map((row) => row.read<int>('c')).getSingle();
    expect(count, 0);
  });

  test('negative inventory prices and costs cannot advance stock', () async {
    for (final prices in <Map<String, String>>[
      <String, String>{'sale_price': '-1', 'purchase_cost': '2'},
      <String, String>{'sale_price': '3', 'purchase_cost': '-2'},
    ]) {
      await expectLater(
        repository.applyPullPage(
          SyncPullPage(
            stream: 'inventory',
            nextCursor: 'invalid-price',
            hasMore: false,
            data: <Map<String, dynamic>>[
              <String, dynamic>{
                'id': 'batch-invalid-price',
                'medicine_id': 'med-1',
                'stock_location_id': 'main',
                'available_quantity': '4.0000',
                'server_updated_at': '2026-11-04T00:00:00Z',
                ...prices,
              },
            ],
          ),
        ),
        throwsFormatException,
      );
    }
    expect(await repository.checkpoint('inventory'), isNull);
    final count = await database.customSelect(
      "SELECT COUNT(*) AS c FROM local_inventory_batches WHERE id = 'batch-invalid-price'",
    ).map((row) => row.read<int>('c')).getSingle();
    expect(count, 0);
  });


  test('older inventory snapshots cannot replace newer cached stock', () async {
    Future<void> apply(String quantity, String timestamp) {
      return repository.applyPullPage(
        SyncPullPage(
          stream: 'inventory',
          nextCursor: timestamp,
          hasMore: false,
          data: <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 'batch-versioned',
              'medicine_id': 'med-1',
              'stock_location_id': 'main',
              'available_quantity': quantity,
              'sale_price': '10.0000',
              'purchase_cost': '4.0000',
              'server_updated_at': timestamp,
            },
          ],
        ),
      );
    }

    await apply('9.0000', '2026-11-04T10:00:00Z');
    await apply('20.0000', '2026-11-03T10:00:00Z');

    final row = await database.customSelect(
      "SELECT available_quantity FROM local_inventory_batches WHERE id = 'batch-versioned'",
    ).getSingle();
    expect(row.read<String>('available_quantity'), '9.0000');
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
