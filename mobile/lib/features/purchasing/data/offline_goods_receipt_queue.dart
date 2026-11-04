import 'dart:convert';
import 'dart:math';

import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/features/purchasing/data/goods_receipt_draft.dart';

/// Durable offline outbox: the server must validate before any stock changes.
class OfflineGoodsReceiptQueue {
  const OfflineGoodsReceiptQueue(this.database);

  final PharmacyDatabase database;

  Future<String> enqueue({
    required String purchaseOrderId,
    required String orderStatus,
    required List<GoodsReceiptDraftLine> lines,
    required DateTime receivedAt,
  }) async {
    final errors = GoodsReceiptDraftValidator.validate(
      lines,
      orderStatus: orderStatus,
    );
    if (errors.isNotEmpty) {
      throw FormatException(errors.join(' '));
    }
    if (purchaseOrderId.trim().isEmpty) {
      throw const FormatException('Purchase order ID is required.');
    }

    final String normalizedOrderId = purchaseOrderId.trim();
    final random = Random.secure();
    final nonce = List<int>.generate(
      16,
      (_) => random.nextInt(256),
    ).map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
    final key = 'grn:${DateTime.now().microsecondsSinceEpoch}:$nonce';
    final payload = <String, Object?>{
      'local_id': key,
      'idempotency_key': key,
      'purchase_order_id': normalizedOrderId,
      'received_at': receivedAt.toUtc().toIso8601String(),
      'lines': lines
          .where((line) => FixedDecimal.parse(line.receivedQuantity).isPositive)
          .map((line) => line.toPayload())
          .toList(),
    };
    final now = DateTime.now().millisecondsSinceEpoch ~/ 1000;

    // Do not allow two unresolved receipts for the same purchase order.
    // Their quantities would each be based on the same cached stock state.
    await database.transaction(() async {
      final outstanding = await database.customSelect(
        '''
        SELECT payload_json
        FROM sync_outbox_entries
        WHERE event_type = 'goods_receipt.create'
          AND status IN ('pending', 'retry', 'sending', 'rejected')
        ''',
      ).get();
      for (final row in outstanding) {
        final Object? existing;
        try {
          existing = jsonDecode(row.read<String>('payload_json'));
        } on FormatException {
          throw const FormatException(
            'An existing offline receipt is corrupted. Resolve the local synchronization queue before adding another receipt.',
          );
        }
        if (existing is! Map) {
          throw const FormatException(
            'An existing offline receipt has an invalid payload.',
          );
        }
        if (existing['purchase_order_id']?.toString().trim() ==
            normalizedOrderId) {
          throw const FormatException(
            'This purchase order already has an unresolved offline receipt. '
            'Synchronize or resolve that receipt before queuing another.',
          );
        }
      }
      await database.customStatement(
        '''
        INSERT INTO sync_outbox_entries (
          idempotency_key, aggregate_type, aggregate_id, event_type,
          payload_json, status, attempt_count, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', 0, ?, ?)
        ''',
        <Object?>[
          key,
          'goods_receipt',
          key,
          'goods_receipt.create',
          jsonEncode(payload),
          now,
          now,
        ],
      );
    });
    return key;
  }
}
