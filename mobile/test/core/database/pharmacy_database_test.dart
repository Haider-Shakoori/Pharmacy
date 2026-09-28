import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;

  setUp(() {
    database = PharmacyDatabase(NativeDatabase.memory());
  });

  tearDown(() async {
    await database.close();
  });

  test('creates the complete Batch 17 offline schema', () async {
    final List<QueryRow> rows = await database
        .customSelect(
          "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name",
        )
        .get();

    final Set<String> names = rows
        .map((QueryRow row) => row.read<String>('name'))
        .toSet();

    expect(
      names,
      containsAll(<String>[
        'app_metadata',
        'local_customers',
        'local_inventory_batches',
        'local_medicines',
        'local_sale_batch_allocations',
        'local_sale_lines',
        'local_sale_payments',
        'local_sales',
        'sync_checkpoints',
        'sync_outbox_entries',
      ]),
    );
  });

  test('Batch 22 schema upgrades local stock allocation storage', () async {
    expect(database.schemaVersion, 2);
  });

  test('financial and quantity values round-trip as exact text', () async {
    final int now = DateTime.now().millisecondsSinceEpoch;

    await database.customStatement(
      '''
      INSERT INTO local_sales (
        local_id, stock_location_id, business_date, subtotal, total,
        tendered_amount, change_amount, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ''',
      <Object?>[
        'sale-local-1',
        'main',
        '2026-09-28',
        '123.4567',
        '120.0000',
        '150.0000',
        '30.0000',
        now,
        now,
      ],
    );

    final QueryRow row = await database.customSelect('''
          SELECT subtotal, total, tendered_amount, change_amount
          FROM local_sales
          WHERE local_id = 'sale-local-1'
          ''').getSingle();

    expect(row.read<String>('subtotal'), '123.4567');
    expect(row.read<String>('total'), '120.0000');
    expect(row.read<String>('tendered_amount'), '150.0000');
    expect(row.read<String>('change_amount'), '30.0000');
  });

  test('outbox idempotency keys cannot be duplicated', () async {
    final int now = DateTime.now().millisecondsSinceEpoch;
    const String insert = '''
      INSERT INTO sync_outbox_entries (
        idempotency_key, aggregate_type, aggregate_id, event_type,
        payload_json, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ''';

    final List<Object?> values = <Object?>[
      'sale:local-1:completed',
      'sale',
      'local-1',
      'completed',
      '{"sale":"local-1"}',
      now,
      now,
    ];

    await database.customStatement(insert, values);

    expect(() => database.customStatement(insert, values), throwsA(anything));
  });
}
