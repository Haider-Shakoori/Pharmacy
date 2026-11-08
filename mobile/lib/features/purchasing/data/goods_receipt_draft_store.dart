import 'dart:convert';

import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:drift/drift.dart';

/// Locally persisted worksheet inputs. Saving a draft never posts inventory.
class GoodsReceiptDraftStore {
  const GoodsReceiptDraftStore(this.database);

  final PharmacyDatabase database;

  String _orderId(String value) {
    final id = value.trim();
    if (id.isEmpty) {
      throw const FormatException('Purchase order ID is required.');
    }
    return id;
  }

  Future<void> _ensureTable() => database.customStatement('''
    CREATE TABLE IF NOT EXISTS local_goods_receipt_drafts (
      purchase_order_id TEXT PRIMARY KEY,
      lines_json TEXT NOT NULL,
      updated_at INTEGER NOT NULL
    )
  ''');

  Future<Map<String, Map<String, String>>> load(String purchaseOrderId) async {
    purchaseOrderId = _orderId(purchaseOrderId);
    await _ensureTable();
    final row = await database
        .customSelect(
          'SELECT lines_json FROM local_goods_receipt_drafts WHERE purchase_order_id = ?',
          variables: <Variable<Object>>[Variable<String>(purchaseOrderId)],
        )
        .getSingleOrNull();
    if (row == null) return <String, Map<String, String>>{};
    final decoded = jsonDecode(row.read<String>('lines_json'));
    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Invalid saved receiving worksheet.');
    }
    final Map<String, Map<String, String>> restored =
        <String, Map<String, String>>{};
    for (final entry in decoded.entries) {
      if (entry.value is! Map) {
        throw const FormatException('Invalid saved receiving worksheet line.');
      }
      try {
        restored[entry.key] = Map<String, String>.from(entry.value as Map);
      } on TypeError {
        throw const FormatException('Invalid saved receiving worksheet line.');
      }
    }
    return restored;
  }

  Future<void> save(
    String purchaseOrderId,
    Map<String, Map<String, String>> lines,
  ) async {
    purchaseOrderId = _orderId(purchaseOrderId);
    await _ensureTable();
    await database.customStatement(
      '''
      INSERT INTO local_goods_receipt_drafts
        (purchase_order_id, lines_json, updated_at)
      VALUES (?, ?, ?)
      ON CONFLICT(purchase_order_id) DO UPDATE SET
        lines_json = excluded.lines_json,
        updated_at = excluded.updated_at
    ''',
      <Object?>[
        purchaseOrderId,
        jsonEncode(lines),
        DateTime.now().millisecondsSinceEpoch ~/ 1000,
      ],
    );
  }

  Future<void> delete(String purchaseOrderId) async {
    purchaseOrderId = _orderId(purchaseOrderId);
    await _ensureTable();
    await database.customStatement(
      'DELETE FROM local_goods_receipt_drafts WHERE purchase_order_id = ?',
      <Object?>[purchaseOrderId],
    );
  }
}
