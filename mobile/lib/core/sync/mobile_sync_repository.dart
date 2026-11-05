import 'dart:convert';

import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/core/sync/sync_models.dart';
import 'package:drift/drift.dart';

class MobileSyncRepository {
  const MobileSyncRepository(this._database);

  final PharmacyDatabase _database;

  Future<List<SyncOutboxEvent>> pendingOutbox({int limit = 25}) async {
    if (limit < 1 || limit > 500) {
      throw RangeError.range(limit, 1, 500, 'limit');
    }
    final int now = _seconds(DateTime.now());
    // A process may terminate after marking events as sending. Reclaim only
    // stale leases; idempotency keys remain unchanged across retries.
    return _database.transaction(() async {
      await _database.customStatement(
        '''
        UPDATE sync_outbox_entries
        SET status = 'retry', next_attempt_at = NULL, updated_at = ?
        WHERE status = 'sending' AND updated_at <= ?
        ''',
        <Object?>[now, now - 300],
      );
      final List<QueryRow> rows = await _database.customSelect(
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
    });
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
          WHERE idempotency_key = ? AND aggregate_id = ? AND event_type = ? AND status IN ('pending', 'retry')
          ''',
          <Object?>[now, event.idempotencyKey, event.aggregateId, event.eventType],
        );
        final QueryRow changed = await _database
            .customSelect('SELECT changes() AS affected')
            .getSingle();
        if (changed.read<int>('affected') != 1) {
          throw StateError(
            'Outbox event changed before it could be marked sending.',
          );
        }
      }
    });
  }

  Future<void> acknowledge(SyncOutboxEvent event, SyncPushResult result) async {
    if (!result.accepted || result.idempotencyKey != event.idempotencyKey) {
      throw const FormatException('Invalid acknowledgement for outbox event.');
    }
    // A sale cannot be considered synchronized without a durable server
    // reference. Keep its outbox event so the original idempotency key retries.
    if (event.eventType == 'sale.completed' &&
        (result.serverId == null || result.serverId!.trim().isEmpty)) {
      throw const FormatException(
        'Accepted sale acknowledgement is missing its server ID.',
      );
    }
    await _database.transaction(() async {
      if (event.eventType == 'sale.completed') {
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
        // Do not discard the outbox entry if its local sale was missing.
        // Otherwise a successful remote write could become invisible locally.
        final QueryRow updated = await _database
            .customSelect('SELECT changes() AS affected')
            .getSingle();
        if (updated.read<int>('affected') != 1) {
          throw const StateError(
            'Sale acknowledgement could not update exactly one local sale.',
          );
        }
      }

      await _database.customStatement(
        "DELETE FROM sync_outbox_entries WHERE idempotency_key = ? AND aggregate_id = ? AND event_type = ? AND status = 'sending'",
        <Object?>[event.idempotencyKey, event.aggregateId, event.eventType],
      );
      final QueryRow removed = await _database
          .customSelect('SELECT changes() AS affected')
          .getSingle();
      if (removed.read<int>('affected') != 1) {
        throw StateError(
          'Sale acknowledgement could not remove exactly one matching outbox event.',
        );
      }
    });
  }

  Future<void> reject(SyncOutboxEvent event, SyncPushResult result) async {
    if (result.accepted || result.idempotencyKey != event.idempotencyKey) {
      throw const FormatException('Invalid rejection for outbox event.');
    }
    final DateTime now = DateTime.now();
    final String nextStatus = result.retryable ? 'retry' : 'rejected';

    await _database.transaction(() async {
      final int attempt = await _attemptCount(event.idempotencyKey);
      final int delaySeconds = _retryDelaySeconds(attempt);
      await _database.customStatement(
        '''
        UPDATE sync_outbox_entries
        SET status = ?,
            last_error = ?,
            next_attempt_at = ?,
            updated_at = ?
        WHERE idempotency_key = ? AND aggregate_id = ? AND event_type = ? AND status = 'sending'
        ''',
        <Object?>[
          nextStatus,
          result.message ?? result.code ?? 'Synchronization rejected.',
          result.retryable
              ? _seconds(now.add(Duration(seconds: delaySeconds)))
              : null,
          _seconds(now),
          event.idempotencyKey,
          event.aggregateId,
          event.eventType,
        ],
      );

      final QueryRow changed = await _database
          .customSelect('SELECT changes() AS affected')
          .getSingle();
      if (changed.read<int>('affected') != 1) {
        throw StateError('Outbox rejection requires one matching sending event.');
      }

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
        final QueryRow saleChanged = await _database
            .customSelect('SELECT changes() AS affected')
            .getSingle();
        if (saleChanged.read<int>('affected') != 1) {
          throw StateError(
            'Sale rejection could not update exactly one local sale.',
          );
        }
      }
    });
  }

  Future<void> retryTransportFailure(
    List<SyncOutboxEvent> events,
    String message,
  ) async {
    for (final SyncOutboxEvent event in events) {
      // Acknowledged events may already have been removed before a later
      // database or transport operation failed. Never recreate them.
      final row = await _database.customSelect(
        'SELECT status FROM sync_outbox_entries WHERE idempotency_key = ?',
        variables: <Variable<Object>>[
          Variable<String>(event.idempotencyKey),
        ],
      ).getSingleOrNull();
      if (row?.read<String>('status') != 'sending') continue;
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
    const supportedStreams = <String>{
      'medicines',
      'inventory',
      'customers',
      'suppliers',
      'purchase_orders',
    };
    if (!supportedStreams.contains(page.stream)) {
      throw const FormatException('Unsupported local synchronization stream.');
    }
    if (page.nextCursor != null && page.nextCursor!.trim().isEmpty) {
      throw const FormatException('Invalid synchronization cursor.');
    }
    if (page.hasMore && page.nextCursor == null) {
      throw const FormatException('Missing synchronization continuation cursor.');
    }
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
          case 'suppliers':
            await _applySupplier(item);
            break;
          case 'purchase_orders':
            await _applyPurchaseOrder(item);
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

  bool _snapshotBool(Map<String, dynamic> item, String field) {
    final Object? value = item[field];
    if (value == null) return false;
    if (value is! bool) {
      throw FormatException('Synchronization snapshot has invalid $field.');
    }
    return value;
  }

  String _requiredSnapshotId(Map<String, dynamic> item, String field) {
    final Object? value = item[field];
    if (value is! String || value.trim().isEmpty) {
      throw FormatException('Synchronization snapshot has invalid $field.');
    }
    return value.trim();
  }

  Future<void> _applySupplier(Map<String, dynamic> item) {
    return _database.customStatement(
      '''
      INSERT INTO local_suppliers
        (id, code, name, phone, contact_person, is_active, is_deleted, server_updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON CONFLICT(id) DO UPDATE SET
        code = excluded.code, name = excluded.name,
        phone = excluded.phone, contact_person = excluded.contact_person,
        is_active = excluded.is_active, is_deleted = excluded.is_deleted,
        server_updated_at = excluded.server_updated_at
      WHERE excluded.server_updated_at >= local_suppliers.server_updated_at
      ''',
      <Object?>[
        _requiredSnapshotId(item, 'id'),
        item['code']?.toString(),
        _requiredSnapshotId(item, 'name'),
        item['phone']?.toString(),
        item['contact_person']?.toString(),
        _snapshotBool(item, 'is_active') ? 1 : 0,
        _snapshotBool(item, 'is_deleted') ? 1 : 0,
        _serverSeconds(item),
      ],
    );
  }

  Future<void> _applyPurchaseOrder(Map<String, dynamic> item) {
    final Object? rawLines = item['lines'];
    if (rawLines != null &&
        (rawLines is! List || rawLines.any((line) => line is! Map))) {
      throw const FormatException('Purchase order lines must be a list of records.');
    }
    final String grandTotal = item['grand_total']?.toString() ?? '0';
    if (FixedDecimal.parse(grandTotal).isNegative) {
      throw const FormatException('Purchase order total cannot be negative.');
    }
    return _database.customStatement(
      '''
      INSERT INTO local_purchase_orders
        (id, supplier_id, number, status, order_date, expected_date,
         currency, grand_total, lines_json, is_deleted, server_updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON CONFLICT(id) DO UPDATE SET
        supplier_id = excluded.supplier_id, number = excluded.number,
        status = excluded.status, order_date = excluded.order_date,
        expected_date = excluded.expected_date, currency = excluded.currency,
        grand_total = excluded.grand_total, lines_json = excluded.lines_json,
        is_deleted = excluded.is_deleted,
        server_updated_at = excluded.server_updated_at
      WHERE excluded.server_updated_at >= local_purchase_orders.server_updated_at
      ''',
      <Object?>[
        _requiredSnapshotId(item, 'id'),
        _requiredSnapshotId(item, 'supplier_id'),
        _requiredSnapshotId(item, 'number'),
        _requiredSnapshotId(item, 'status'),
        item['order_date']?.toString(),
        item['expected_date']?.toString(),
        item['currency'] == null
            ? 'AFN'
            : _requiredSnapshotId(item, 'currency'),
        grandTotal,
        jsonEncode(item['lines'] ?? <Object>[]),
        _snapshotBool(item, 'is_deleted') ? 1 : 0,
        _serverSeconds(item),
      ],
    );
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
      WHERE excluded.server_updated_at >= local_medicines.server_updated_at
      ''',
      <Object?>[
        _requiredSnapshotId(item, 'id'),
        item['medicine_code']?.toString(),
        _requiredSnapshotId(item, 'brand_name'),
        item['generic_name']?.toString(),
        item['sale_unit'] == null
            ? 'unit'
            : _requiredSnapshotId(item, 'sale_unit'),
        _snapshotBool(item, 'is_active') ? 1 : 0,
        _snapshotBool(item, 'is_deleted') ? 1 : 0,
        _serverSeconds(item),
      ],
    );
  }

  Future<void> _applyInventory(Map<String, dynamic> item) async {
    final Object? rawId = item['id'];
    final Object? rawMedicineId = item['medicine_id'];
    final Object? rawLocationId = item['stock_location_id'];
    if (rawId is! String || rawId.trim().isEmpty ||
        rawMedicineId is! String || rawMedicineId.trim().isEmpty ||
        rawLocationId is! String || rawLocationId.trim().isEmpty) {
      throw const FormatException('Inventory snapshot is missing required identifiers.');
    }
    final String batchId = rawId.trim();
    final String batchStatus = item['status'] == null
        ? 'active'
        : _requiredSnapshotId(item, 'status');
    final String? expiresAt = _optionalSnapshotTimestamp(item, 'expires_at');
    final String? serverCreatedAt =
        _optionalSnapshotTimestamp(item, 'server_created_at');
    final Object? rawAvailable = item['available_quantity'];
    if (rawAvailable == null || rawAvailable.toString().trim().isEmpty) {
      throw const FormatException('Inventory snapshot is missing available stock.');
    }
    final FixedDecimal serverAvailable = FixedDecimal.parse(
      rawAvailable.toString(),
    );
    if (serverAvailable.isNegative) {
      throw const FormatException('Inventory snapshot cannot contain negative available stock.');
    }
    final String salePrice = item['sale_price']?.toString() ?? '0';
    final String purchaseCost = item['purchase_cost']?.toString() ?? '0';
    if (FixedDecimal.parse(salePrice).isNegative ||
        FixedDecimal.parse(purchaseCost).isNegative) {
      throw const FormatException('Inventory prices cannot be negative.');
    }
    final FixedDecimal outstanding = await _outstandingAllocationQuantity(
      batchId,
    );
    final FixedDecimal effective = serverAvailable - outstanding;
    final FixedDecimal available = effective.isNegative
        ? FixedDecimal.zero
        : effective;

    await _database.customStatement(
      '''
      INSERT INTO local_inventory_batches (
        id, medicine_id, stock_location_id, batch_number, expires_at,
        available_quantity, sale_price, purchase_cost, status,
        is_deleted, server_created_at, server_updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        server_created_at = excluded.server_created_at,
        server_updated_at = excluded.server_updated_at
      WHERE excluded.server_updated_at >= local_inventory_batches.server_updated_at
      ''',
      <Object?>[
        batchId,
        rawMedicineId.trim(),
        rawLocationId.trim(),
        item['batch_number']?.toString() ?? '',
        expiresAt == null ? null : _seconds(DateTime.parse(expiresAt).toUtc()),
        available.toString(),
        salePrice,
        purchaseCost,
        batchStatus,
        _snapshotBool(item, 'is_deleted') ? 1 : 0,
        serverCreatedAt == null
            ? null
            : _seconds(DateTime.parse(serverCreatedAt).toUtc()),
        _serverSeconds(item),
      ],
    );
  }

  Future<FixedDecimal> _outstandingAllocationQuantity(String batchId) async {
    final List<QueryRow> rows = await _database
        .customSelect(
          '''
      SELECT allocation.quantity
      FROM local_sale_batch_allocations allocation
      INNER JOIN local_sale_lines line
        ON line.local_id = allocation.sale_line_local_id
      INNER JOIN local_sales sale
        ON sale.local_id = line.sale_local_id
      WHERE allocation.product_batch_id = ?
        AND sale.sync_state <> 'synced'
      ''',
          variables: <Variable<Object>>[Variable<String>(batchId)],
        )
        .get();

    FixedDecimal total = FixedDecimal.zero;
    for (final QueryRow row in rows) {
      total += FixedDecimal.parse(row.read<String>('quantity'));
    }

    return total;
  }

  Future<void> _applyCustomer(Map<String, dynamic> item) {
    final String balance = item['balance']?.toString() ?? '0.0000';
    FixedDecimal.parse(balance);
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
      WHERE excluded.server_updated_at >= local_customers.server_updated_at
      ''',
      <Object?>[
        _requiredSnapshotId(item, 'id'),
        _requiredSnapshotId(item, 'name'),
        item['phone']?.toString(),
        balance,
        _snapshotBool(item, 'is_deleted') ? 1 : 0,
        _serverSeconds(item),
      ],
    );
  }

  String? _optionalSnapshotTimestamp(Map<String, dynamic> item, String field) {
    final Object? raw = item[field];
    if (raw == null) return null;
    if (raw is! String || raw.trim().isEmpty) {
      throw FormatException('Inventory snapshot has an invalid $field.');
    }
    DateTime.parse(raw.trim());
    return raw.trim();
  }

  int _serverSeconds(Map<String, dynamic> item) {
    final Object? raw = item['server_updated_at'];
    if (raw is! String || raw.trim().isEmpty) {
      throw const FormatException('Synchronization snapshot has no server update timestamp.');
    }
    return _seconds(DateTime.parse(raw.trim()).toUtc());
  }

  int _seconds(DateTime value) => value.millisecondsSinceEpoch ~/ 1000;
}
