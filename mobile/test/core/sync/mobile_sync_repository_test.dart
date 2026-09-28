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

  test('server acknowledgement marks sale synced then removes outbox', () async {
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
  });

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
        .customSelect(
          'SELECT status, last_error FROM sync_outbox_entries',
        )
        .getSingle();
    final QueryRow sale = await database
        .customSelect('SELECT sync_state FROM local_sales')
        .getSingle();

    expect(outbox.read<String>('status'), 'rejected');
    expect(outbox.read<String>('last_error'), 'Insufficient stock.');
    expect(sale.read<String>('sync_state'), 'rejected');
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
        .customSelect('SELECT brand_name FROM local_medicines WHERE id = ?',
            variables: <Variable<Object>>[
              const Variable<String>('med-1'),
            ])
        .getSingle();

    expect(medicine.read<String>('brand_name'), 'Synced Medicine');
    expect(await repository.checkpoint('medicines'), 'cursor-1');
  });
}
