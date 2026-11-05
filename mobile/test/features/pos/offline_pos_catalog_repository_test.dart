import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/pos/data/offline_pos_catalog_repository.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;
  late OfflinePosCatalogRepository catalog;

  setUp(() {
    database = PharmacyDatabase(NativeDatabase.memory());
    catalog = OfflinePosCatalogRepository(database);
  });

  tearDown(() async {
    await database.close();
  });

  test(
    'limit applies to sellable medicines, not unavailable matches',
    () async {
      for (final item in <List<String>>[
        <String>['med-empty', 'A Unavailable'],
        <String>['med-stock', 'B Available'],
      ]) {
        await database.customStatement(
          '''
        INSERT INTO local_medicines (
          id, medicine_code, brand_name, generic_name, sale_unit,
          is_active, is_deleted
        ) VALUES (?, ?, ?, 'Generic', 'box', 1, 0)
        ''',
          <Object?>[item[0], item[0], item[1]],
        );
      }
      await database.customStatement('''
      INSERT INTO local_inventory_batches (
        id, medicine_id, stock_location_id, batch_number,
        available_quantity, sale_price, purchase_cost, status, is_deleted
      ) VALUES ('batch-1', 'med-stock', 'main', 'LOT-1',
        '3.0000', '12.5000', '8.0000', 'active', 0)
      ''');
      final items = await catalog.search(stockLocationId: 'main', limit: 1);
      expect(items, hasLength(1));
      expect(items.single.medicineId, 'med-stock');
    },
  );

  test('search treats percent and underscore as literal characters', () async {
    for (final item in <List<String>>[
      <String>['med-percent', 'Vitamin 5%'],
      <String>['med-underscore', 'Vitamin_5'],
      <String>['med-plain', 'Vitamin 50'],
    ]) {
      await database.customStatement(
        '''
        INSERT INTO local_medicines (
          id, medicine_code, brand_name, generic_name, sale_unit,
          is_active, is_deleted
        ) VALUES (?, ?, ?, 'Generic', 'box', 1, 0)
        ''',
        <Object?>[item[0], item[0], item[1]],
      );
      await database.customStatement(
        '''
        INSERT INTO local_inventory_batches (
          id, medicine_id, stock_location_id, batch_number,
          available_quantity, sale_price, purchase_cost, status, is_deleted
        ) VALUES (?, ?, 'main', 'LOT', '2.0000', '10.0000', '5.0000', 'active', 0)
        ''',
        <Object?>['batch-${item[0]}', item[0]],
      );
    }
    final percent = await catalog.search(stockLocationId: ' main ', query: '%');
    expect(percent.map((item) => item.medicineId), <String>['med-percent']);
    final underscore = await catalog.search(
      stockLocationId: 'main',
      query: '_',
    );
    expect(underscore.map((item) => item.medicineId), <String>[
      'med-underscore',
    ]);
  });

  test(
    'catalog rejects invalid result limits and blank stock location',
    () async {
      await expectLater(
        catalog.search(stockLocationId: 'main', limit: 0),
        throwsRangeError,
      );
      await expectLater(
        catalog.search(stockLocationId: 'main', limit: 501),
        throwsRangeError,
      );
      await expectLater(
        catalog.search(stockLocationId: ' '),
        throwsFormatException,
      );
    },
  );
}
