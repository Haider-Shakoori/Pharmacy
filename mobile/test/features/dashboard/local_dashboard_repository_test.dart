import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/dashboard/data/local_dashboard_repository.dart';
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

  test(
    'dashboard counts only completed local sales on selected date',
    () async {
      await database.customStatement('''
      INSERT INTO local_medicines (
        id, medicine_code, brand_name, sale_unit, is_active, is_deleted
      ) VALUES ('medicine-1', '001', 'Paracetamol', 'tablet', 1, 0)
      ''');
      await database.customStatement('''
      INSERT INTO local_customers (id, name, balance, is_deleted)
      VALUES ('customer-1', 'Test Customer', '0', 0)
      ''');

      const String insertSale = '''
      INSERT INTO local_sales (
        local_id, stock_location_id, business_date, status, subtotal, total,
        created_at, updated_at
      ) VALUES (?, 'main', ?, ?, ?, ?, ?, ?)
      ''';
      final int now = DateTime(2026, 10, 28, 12).millisecondsSinceEpoch;
      await database.customStatement(insertSale, <Object?>[
        'sale-1',
        '2026-10-28',
        'completed',
        '12.1234',
        '12.1234',
        now,
        now,
      ]);
      await database.customStatement(insertSale, <Object?>[
        'sale-2',
        '2026-10-28',
        'completed',
        '3.0006',
        '3.0006',
        now,
        now,
      ]);
      await database.customStatement(insertSale, <Object?>[
        'draft-1',
        '2026-10-28',
        'draft',
        '9.0000',
        '9.0000',
        now,
        now,
      ]);
      await database.customStatement(insertSale, <Object?>[
        'yesterday-1',
        '2026-10-27',
        'completed',
        '8.0000',
        '8.0000',
        now,
        now,
      ]);
      await database.customStatement(
        '''
      INSERT INTO sync_outbox_entries (
        idempotency_key, aggregate_type, aggregate_id,
        event_type, payload_json, status, created_at, updated_at
      ) VALUES ('sale:1', 'sale', 'sale-1', 'sale.completed',
                '{}', 'pending', ?, ?)
      ''',
        <Object?>[now, now],
      );

      final LocalDashboardSnapshot result = await LocalDashboardRepository(
        database,
        lowStockThreshold: 10,
        nearExpiryDays: 30,
      ).snapshot(now: DateTime(2026, 10, 28, 18));

      expect(result.salesToday, 2);
      expect(result.salesTodayAmount.compact, '15.124');
      expect(result.cachedMedicines, 1);
      expect(result.cachedCustomers, 1);
      expect(result.pendingSync, 1);
      expect(result.rejectedSync, 0);
      expect(result.alerts.lowStockCount, 1);
    },
  );

  test('new or empty device dashboard never invents sales', () async {
    final LocalDashboardSnapshot result = await LocalDashboardRepository(
      database,
      lowStockThreshold: 10,
      nearExpiryDays: 30,
    ).snapshot(now: DateTime(2026, 10, 28));
    expect(result.salesToday, 0);
    expect(result.salesTodayAmount.compact, '0');
    expect(result.cachedMedicines, 0);
    expect(result.cachedCustomers, 0);
    expect(result.pendingSync, 0);
  });
}
