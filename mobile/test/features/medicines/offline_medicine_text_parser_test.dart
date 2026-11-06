import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const parser = OfflineMedicineTextParser();

  test('extracts visible packaging fields without inventing ingredients', () {
    final draft = parser.parse([
      'ExampleMed',
      '500 mg Tablets',
      'LOT: AB1234',
      'EXP: 12/2028',
    ], barcode: '0123456789012');

    expect(draft.brandName, 'ExampleMed');
    expect(draft.strength, '500 mg');
    expect(draft.dosageForm?.toLowerCase(), 'tablets');
    expect(draft.batchNumber, 'AB1234');
    expect(draft.expiresAt, '12/2028');
    expect(draft.barcode, '0123456789012');
    expect(draft.genericName, isNull);
  });

  test('does not guess missing fields', () {
    final draft = parser.parse(['Unclear packaging']);
    expect(draft.strength, isNull);
    expect(draft.batchNumber, isNull);
    expect(draft.expiresAt, isNull);
    expect(draft.genericName, isNull);
  });

  test('empty recognition yields empty draft', () {
    final draft = parser.parse([]);
    expect(draft.brandName, isNull);
    expect(draft.sourceText, isEmpty);
  });
}
