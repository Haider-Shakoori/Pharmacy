import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;
  late OfflineCatalogBrowser browser;

  setUp(() async {
    database = PharmacyDatabase(NativeDatabase.memory());
    browser = OfflineCatalogBrowser(database);
    await database.customStatement('''
      INSERT INTO local_medicines
      (id, medicine_code, brand_name, generic_name, sale_unit, is_active, is_deleted)
      VALUES ('med-1', 'PARA500', 'Paracetamol', 'Acetaminophen', 'tablet', 1, 0),
             ('med-2', 'HIDDEN', 'Hidden Medicine', '', 'box', 1, 1)
    ''');
    await database.customStatement('''
      INSERT INTO local_customers (id, name, phone, balance, is_deleted)
      VALUES ('cus-1', 'Ahmad Wali', '0700000000', '125.2500', 0),
             ('cus-2', 'Deleted Customer', '0711111111', '20', 1)
    ''');
    final int expiresLater =
        DateTime(2027, 1, 1).millisecondsSinceEpoch ~/ 1000;
    final int expired = DateTime(2026, 1, 1).millisecondsSinceEpoch ~/ 1000;
    await database.customStatement(
      '''
      INSERT INTO local_inventory_batches
      (id, medicine_id, stock_location_id, batch_number, expires_at,
       available_quantity, sale_price, purchase_cost, status, is_deleted)
      VALUES ('lot-1', 'med-1', 'main', 'LOT-1', ?, '1.2500', '12.0000', '8.0000', 'active', 0),
             ('lot-2', 'med-1', 'main', 'LOT-2', ?, '100.0000', '12.0000', '8.0000', 'active', 0),
             ('lot-3', 'med-1', 'secondary', 'LOT-3', NULL, '2.0000', '15.0000', '9.0000', 'active', 0),
             ('lot-4', 'med-1', 'main', 'LOT-4', NULL, '99.0000', '12.0000', '8.0000', 'quarantined', 0)
      ''',
      <Object?>[expiresLater, expired],
    );
  });

  tearDown(() async => database.close());

  test('offline inventory sums only sellable nonexpired stock', () async {
    final List<StockMedicineSnapshot> list = await browser.medicines(
      search: 'paraceta',
      now: DateTime(2026, 10, 17),
    );

    expect(list, hasLength(1));
    expect(list.single.name, 'Paracetamol');
    expect(list.single.available.compact, '3.25');
    expect(list.single.batches, hasLength(4));
    expect(list.single.batches.where((batch) => batch.expired), hasLength(1));
    expect(await browser.stockLocations(), <String>['main', 'secondary']);
  });

  test('location filter excludes other location and never mutates stock', () async {
    final List<StockMedicineSnapshot> list = await browser.medicines(
      locationId: 'main',
      now: DateTime(2026, 10, 17),
    );
    expect(list, hasLength(1));
    expect(list.single.available.compact, '1.25');
    expect(list.single.batches, hasLength(3));

    final List<StockMedicineSnapshot> all = await browser.medicines(
      search: 'hidden',
    );
    expect(all, isEmpty);
  });

  test('customer search respects deletion flags and exact balance text', () async {
    final List<CustomerSnapshot> found = await browser.customers(
      search: '0700',
    );
    expect(found, hasLength(1));
    expect(found.single.name, 'Ahmad Wali');
    expect(found.single.balance, '125.2500');
    expect(await browser.customers(search: 'Deleted'), isEmpty);
  });
}
