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

  test('extracts Oxypar Plus composition from one package image', () {
    final draft = parser.parse([
      'AR',
      'Each uncoated tablet contains:',
      'Paracetamol IP 325 mg',
      'Diclofenac Sodium IP 50 mg',
      'Magnesium Trisilicate IP 100 mg',
      'COMBITIC GLOBAL CAPLET PVT. LTD.',
      'OXY PAR PLUS',
      'Tablets',
    ]);
    expect(draft.brandName, 'OXY PAR PLUS');
    expect(
      draft.genericName,
      'Paracetamol, Diclofenac Sodium, Magnesium Trisilicate',
    );
    expect(draft.strength, '325 mg + 50 mg + 100 mg');
    expect(draft.dosageForm?.toLowerCase(), 'tablets');
  });

  test('repeated OCR readings beat manufacturer logo on rotated pack', () {
    final draft = parser.parse([
      'APHL',
      'Esomeprazol',
      'Assoger',
      '14 Capsules',
      'Esomeprazole',
      'Essoger',
      '20mg',
    ]);
    expect(draft.brandName, 'Assoger');
    expect(draft.genericName, 'Esomeprazole');
    expect(draft.strength, '20mg');
    expect(draft.dosageForm, 'Capsules');
    expect(draft.sourceText, contains('Essoger'));
  });

  test('empty recognition yields empty draft', () {
    final draft = parser.parse([]);
    expect(draft.brandName, isNull);
    expect(draft.sourceText, isEmpty);
  });
}
