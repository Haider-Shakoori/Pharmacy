import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/sync/sync_models.dart';
import 'package:drift/drift.dart';

class MobileSyncRepository {
  const MobileSyncRepository(this._database);

  final PharmacyDatabase _database;

  Future<List<SyncOutboxEvent>> pendingOutbox({int limit = 25}) async {
    final int now = _seconds(DateTime.now());
    final List<QueryRow> rows = await _database
        .customSelect(
          '''
      SELECT idempotency_key, aggregate_id, event_type, payload_json
      FROM sync_outbox_entries
      WHERE status IN ('pending', 'retry')
        AND (next_attempt_at IS NULL OR next_attempt_at <= ?)
      ORDER BY created_at, idempotency_key
      LIMIT ?
      ''',
          variables: <Variable<Object>>[
            Variable<int>(now),
            Variable<int>(limit),
          ],
        )
        .get();

    return rows
        .map(
          (QueryRow row) => SyncOutboxEvent.fromRow(
            idempotencyKey: row.read<String>('idempotency_key'),
            aggregateId: row.read<String>('aggregate_id'),
            eventType: row.read<String>('event_type'),
            payloadJson: row.read<String>('payload_json'),
          ),
        )
        .toList(growable: false);
  }

  Future<void> markSending(List<SyncOutboxEvent> events) async {
    if (events.isEmpty) {
      return;
    }

    final int now = _seconds(DateTime.now());

    await _database.transaction(() async {
      for (final SyncOutboxEvent event in events) {
        await _database.customStatement(
          '''
          UPDATE sync_outbox_entries
          SET status = 'sending',
              attempt_count = attempt_count + 1,
              last_error = NULL,
              updated_at = ?
          WHERE idempotency_key = ?
          ''',
          <Object?>[now, event.idempotencyKey],
        );
      }
    });
  }

  Future<void> acknowledge(SyncOutboxEvent event, SyncPushResult result) async {
    await _database.transaction(() async {
      if (event.eventType == 'sale.completed' && result.serverId != null) {
        await _database.customStatement(
          '''
          UPDATE local_sales
          SET server_id = ?,
              sync_state = 'synced',
              updated_at = ?
          WHERE local_id = ?
          ''',
          <Object?>[
            result.serverId,
            _seconds(DateTime.now()),
            event.aggregateId,
          ],
        );
      }

      await _database.customStatement(
        'DELETE FROM sync_outbox_entries WHERE idempotency_key = ?',
        <Object?>[event.idempotencyKey],
      );
    });
  }

  Future<void> reject(SyncOutboxEvent event, SyncPushResult result) async {
    final DateTime now = DateTime.now();
    final int attempt = await _attemptCount(event.idempotencyKey);
    final int delaySeconds = _retryDelaySeconds(attempt);
    final String nextStatus = result.retryable ? 'retry' : 'rejected';

    await _database.transaction(() async {
      await _database.customStatement(
        '''
        UPDATE sync_outbox_entries
        SET status = ?,
            last_error = ?,
            next_attempt_at = ?,
            updated_at = ?
        WHERE idempotency_key = ?
        ''',
        <Object?>[
          nextStatus,
          result.message ?? result.code ?? 'Synchronization rejected.',
          result.retryable
              ? _seconds(now.add(Duration(seconds: delaySeconds)))
              : null,
          _seconds(now),
          event.idempotencyKey,
        ],
      );

      if (event.eventType == 'sale.completed') {
        await _database.customStatement(
          '''
          UPDATE local_sales
          SET sync_state = ?,
              updated_at = ?
          WHERE local_id = ?
          ''',
          <Object?>[
            result.retryable ? 'pending' : 'rejected',
            _seconds(now),
            event.aggregateId,
          ],
        );
      }
    });
  }

  Future<void> retryTransportFailure(
    List<SyncOutboxEvent> events,
    String message,
  ) async {
    for (final SyncOutboxEvent event in events) {
      await reject(
        event,
        SyncPushResult(
          idempotencyKey: event.idempotencyKey,
          status: 'rejected',
          code: 'transport_error',
          message: message,
          retryable: true,
        ),
      );
    }
  }

  Future<String?> checkpoint(String stream) async {
    final QueryRow? row = await _database
        .customSelect(
          'SELECT cursor FROM sync_checkpoints WHERE stream = ? LIMIT 1',
          variables: <Variable<Object>>[Variable<String>(stream)],
        )
        .getSingleOrNull();

    return row?.readNullable<String>('cursor');
  }

  Future<void> applyPullPage(SyncPullPage page) async {
    await _database.transaction(() async {
      for (final Map<String, dynamic> item in page.data) {
        switch (page.stream) {
          case 'medicines':
            await _applyMedicine(item);
            break;
          case 'inventory':
            await _applyInventory(item);
            break;
          case 'customers':
            await _applyCustomer(item);
            break;
          default:
            throw const FormatException(
              'Unsupported local synchronization stream.',
            );
        }
      }

      final int now = _seconds(DateTime.now());
      await _database.customStatement(
        '''
        INSERT INTO sync_checkpoints (stream, cursor, last_synced_at)
        VALUES (?, ?, ?)
        ON CONFLICT(stream) DO UPDATE SET
          cursor = excluded.cursor,
          last_synced_at = excluded.last_synced_at
        ''',
        <Object?>[page.stream, page.nextCursor, now],
      );
    });
  }

  Future<SyncStatusSnapshot> status() async {
    final QueryRow counts = await _database.customSelect('''
      SELECT
        SUM(CASE WHEN status IN ('pending', 'retry', 'sending') THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
      FROM sync_outbox_entries
      ''').getSingle();

    final QueryRow last = await _database
        .customSelect(
          'SELECT MAX(last_synced_at) AS last_synced_at FROM sync_checkpoints',
        )
        .getSingle();

    final int? lastSeconds = last.readNullable<int>('last_synced_at');

    return SyncStatusSnapshot(
      pending: counts.readNullable<int>('pending') ?? 0,
      rejected: counts.readNullable<int>('rejected') ?? 0,
      lastSyncedAt: lastSeconds == null
          ? null
          : DateTime.fromMillisecondsSinceEpoch(
              lastSeconds * 1000,
              isUtc: true,
            ).toLocal(),
    );
  }

  Future<int> _attemptCount(String idempotencyKey) async {
    final QueryRow? row = await _database
        .customSelect(
          '''
          SELECT attempt_count
          FROM sync_outbox_entries
          WHERE idempotency_key = ?
          LIMIT 1
          ''',
          variables: <Variable<Object>>[Variable<String>(idempotencyKey)],
        )
        .getSingleOrNull();

    return row?.read<int>('attempt_count') ?? 0;
  }

  int _retryDelaySeconds(int attempt) {
    final int exponent = attempt < 0 ? 0 : (attempt > 6 ? 6 : attempt);
    return 30 * (1 << exponent);
  }

  Future<void> _applyMedicine(Map<String, dynamic> item) {
    return _database.customStatement(
      '''
      INSERT INTO local_medicines (
        id, medicine_code, brand_name, generic_name, sale_unit,
        is_active, is_deleted, server_updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON CONFLICT(id) DO UPDATE SET
        medicine_code = excluded.medicine_code,
        brand_name = excluded.brand_name,
        generic_name = excluded.generic_name,
        sale_unit = excluded.sale_unit,
        is_active = excluded.is_active,
        is_deleted = excluded.is_deleted,
        server_updated_at = excluded.server_updated_at
      ''',
      <Object?>[
        item['id'].toString(),
        item['medicine_code']?.toString(),
        item['brand_name'].toString(),
        item['generic_name']?.toString(),
        item['sale_unit']?.toString() ?? 'unit',
        item['is_active'] == true ? 1 : 0,
        item['is_deleted'] == true ? 1 : 0,
        _serverSeconds(item),
      ],
    );
  }

  Future<void> _applyInventory(Map<String, dynamic> item) {
    final String? expiresAt = item['expires_at']?.toString();

    return _database.customStatement(
      '''
      INSERT INTO local_inventory_batches (
        id, medicine_id, stock_location_id, batch_number, expires_at,
        available_quantity, sale_price, purchase_cost, status,
        is_deleted, server_updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON CONFLICT(id) DO UPDATE SET
        medicine_id = excluded.medicine_id,
        stock_location_id = excluded.stock_location_id,
        batch_number = excluded.batch_number,
        expires_at = excluded.expires_at,
        available_quantity = excluded.available_quantity,
        sale_price = excluded.sale_price,
        purchase_cost = excluded.purchase_cost,
        status = excluded.status,
        is_deleted = excluded.is_deleted,
        server_updated_at = excluded.server_updated_at
      ''',
      <Object?>[
        item['id'].toString(),
        item['medicine_id'].toString(),
        item['stock_location_id'].toString(),
        item['batch_number']?.toString() ?? '',
        expiresAt == null ? null : _seconds(DateTime.parse(expiresAt).toUtc()),
        item['available_quantity']?.toString() ?? '0',
        item['sale_price']?.toString() ?? '0',
        item['purchase_cost']?.toString() ?? '0',
        item['status']?.toString() ?? 'active',
        item['is_deleted'] == true ? 1 : 0,
        _serverSeconds(item),
      ],
    );
  }

  Future<void> _applyCustomer(Map<String, dynamic> item) {
    return _database.customStatement(
      '''
      INSERT INTO local_customers (
        id, name, phone, balance, is_deleted, server_updated_at
      ) VALUES (?, ?, ?, ?, ?, ?)
      ON CONFLICT(id) DO UPDATE SET
        name = excluded.name,
        phone = excluded.phone,
        balance = excluded.balance,
        is_deleted = excluded.is_deleted,
        server_updated_at = excluded.server_updated_at
      ''',
      <Object?>[
        item['id'].toString(),
        item['name'].toString(),
        item['phone']?.toString(),
        item['balance']?.toString() ?? '0.0000',
        item['is_deleted'] == true ? 1 : 0,
        _serverSeconds(item),
      ],
    );
  }

  int _serverSeconds(Map<String, dynamic> item) {
    final String value = item['server_updated_at'].toString();
    return _seconds(DateTime.parse(value).toUtc());
  }

  int _seconds(DateTime value) => value.millisecondsSinceEpoch ~/ 1000;
}
