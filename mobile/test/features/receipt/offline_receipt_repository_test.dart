import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/receipt/data/offline_receipt_repository.dart';
import 'package:businessos_pharmacy/features/receipt/domain/offline_receipt.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;
  late OfflineReceiptRepository repository;

  setUp(() async {
    database = PharmacyDatabase(NativeDatabase.memory());
    repository = OfflineReceiptRepository(database);
    final int now =
        DateTime(2026, 9, 28, 12).millisecondsSinceEpoch ~/ 1000;

    await database.customStatement(
      '''
      INSERT INTO local_medicines (
        id, medicine_code, brand_name, sale_unit, is_active, is_deleted
      ) VALUES ('med-1', 'MED-1', 'Paracetamol', 'box', 1, 0)
      ''',
    );
    await database.customStatement(
      '''
      INSERT INTO local_customers (
        id, name, balance, is_deleted
      ) VALUES ('customer-1', 'Walk Customer', '0.0000', 0)
      ''',
    );
    await database.customStatement(
      '''
      INSERT INTO local_sales (
        local_id, customer_id, stock_location_id, business_date,
        status, sync_state, subtotal, discount_amount, total,
        tendered_amount, change_amount, created_at, updated_at, completed_at
      ) VALUES (
        'sale-1', 'customer-1', 'main', '2026-09-28',
        'completed', 'pending', '25.0000', '1.0000', '24.0000',
        '30.0000', '6.0000', ?, ?, ?
      )
      ''',
      <Object?>[now, now, now],
    );
    await database.customStatement(
      '''
      INSERT INTO local_sale_lines (
        local_id, sale_local_id, medicine_id, quantity,
        unit_price, discount_amount, line_total
      ) VALUES (
        'line-1', 'sale-1', 'med-1', '2.0000',
        '12.5000', '1.0000', '24.0000'
      )
      ''',
    );
    await database.customStatement(
      '''
      INSERT INTO local_sale_payments (
        local_id, sale_local_id, method, amount, reference
      ) VALUES ('payment-1', 'sale-1', 'cash', '30.0000', NULL)
      ''',
    );
  });

  tearDown(() async {
    await database.close();
  });

  test(
    'loads a complete receipt locally without server access',
    () async {
      final OfflineReceipt? receipt = await repository.find('sale-1');

      expect(receipt, isNotNull);
      expect(receipt!.customerName, 'Walk Customer');
      expect(receipt.lines.single.medicineName, 'Paracetamol');
      expect(receipt.lines.single.quantity.toString(), '2.0000');
      expect(receipt.payments.single.method, 'cash');
      expect(receipt.total.toString(), '24.0000');
      expect(receipt.changeAmount.toString(), '6.0000');
      expect(receipt.isSynced, isFalse);
    },
  );

  test('recent receipts survive restart for reprinting', () async {
    final List<OfflineReceipt> receipts = await repository.recentCompleted();

    expect(receipts, hasLength(1));
    expect(receipts.single.saleLocalId, 'sale-1');
    expect(receipts.single.displayNumber, startsWith('OFF-'));
  });
}
