import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/purchasing/data/goods_receipt_draft_store.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;
  late GoodsReceiptDraftStore store;

  setUp(() {
    database = PharmacyDatabase(NativeDatabase.memory());
    store = GoodsReceiptDraftStore(database);
  });

  tearDown(() async {
    await database.close();
  });

  test('worksheet IDs are normalized on save, load, and delete', () async {
    const lines = <String, Map<String, String>>{
      'line-1': <String, String>{'received_quantity': '2'},
    };
    await store.save(' order-1 ', lines);
    expect(await store.load('order-1'), lines);
    await store.delete(' order-1 ');
    expect(await store.load('order-1'), isEmpty);
  });

  test('blank worksheet IDs are rejected without persistence', () async {
    await expectLater(
      store.save('  ', <String, Map<String, String>>{}),
      throwsFormatException,
    );
    await expectLater(store.load('  '), throwsFormatException);
    await expectLater(store.delete('  '), throwsFormatException);
  });
}
