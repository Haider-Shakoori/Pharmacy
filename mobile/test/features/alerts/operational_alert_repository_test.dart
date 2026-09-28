import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/alerts/data/operational_alert_repository.dart';
import 'package:businessos_pharmacy/features/alerts/domain/operational_alert.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;

  setUp(() async {
    database = PharmacyDatabase(NativeDatabase.memory());
    await database.customStatement('''
      INSERT INTO local_medicines (
        id, medicine_code, brand_name, sale_unit, is_active, is_deleted
      ) VALUES ('med-1', 'MED-1', 'Paracetamol', 'box', 1, 0)
      ''');
  });

  tearDown(() async {
    await database.close();
  });

  test('derives low stock expiry and sync conflict alerts locally', () async {
    final int near = DateTime(2026, 10, 10).millisecondsSinceEpoch ~/ 1000;
    final int expired = DateTime(2026, 9, 20).millisecondsSinceEpoch ~/ 1000;
    final int now = DateTime(2026, 9, 28).millisecondsSinceEpoch ~/ 1000;

    await database.customStatement(
      '''
      INSERT INTO local_inventory_batches (
        id, medicine_id, stock_location_id, batch_number, expires_at,
        available_quantity, sale_price, purchase_cost, status, is_deleted
      ) VALUES
        ('batch-near', 'med-1', 'main', 'NEAR', ?, '3.0000', '10.0000', '5.0000', 'active', 0),
        ('batch-expired', 'med-1', 'main', 'OLD', ?, '2.0000', '10.0000', '5.0000', 'active', 0)
      ''',
      <Object?>[near, expired],
    );

    await database.customStatement(
      '''
      INSERT INTO sync_outbox_entries (
        idempotency_key, aggregate_type, aggregate_id, event_type,
        payload_json, status, attempt_count, last_error, created_at, updated_at
      ) VALUES (
        'sale:1:completed', 'sale', '1', 'sale.completed', '{}',
        'rejected', 1, 'Insufficient server stock.', ?, ?
      )
      ''',
      <Object?>[now, now],
    );

    final OperationalAlertSnapshot snapshot = await OperationalAlertRepository(
      database,
      lowStockThreshold: 10,
      nearExpiryDays: 30,
      clock: () => DateTime(2026, 9, 28, 12),
    ).snapshot();

    expect(snapshot.lowStockCount, 1);
    expect(snapshot.nearExpiryCount, 1);
    expect(snapshot.expiredCount, 1);
    expect(snapshot.syncRejectedCount, 1);
    expect(snapshot.total, 4);
    expect(
      snapshot.alerts.any(
        (LocalOperationalAlert alert) =>
            alert.kind == OperationalAlertKind.syncRejected,
      ),
      isTrue,
    );
  });

  test('persists notification preference and fingerprint', () async {
    final OperationalAlertRepository repository = OperationalAlertRepository(
      database,
      lowStockThreshold: 10,
      nearExpiryDays: 30,
    );

    expect(await repository.notificationsEnabled(), isFalse);
    await repository.setNotificationsEnabled(true);
    await repository.setLastNotifiedFingerprint('1:2:3:4');

    expect(await repository.notificationsEnabled(), isTrue);
    expect(await repository.lastNotifiedFingerprint(), '1:2:3:4');
  });
}
