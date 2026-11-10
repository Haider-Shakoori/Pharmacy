import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const parser = OfflineMedicineTextParser();

  test('recognizes Persian expiry, regional digits and dosage', () {
    final draft = parser.parse([
      'داروی آزمایشی',
      'شربت ۱۲۵ mg',
      'تاریخ انقضا: ۰۵/۲۰۲۸',
    ]);
    expect(draft.brandName, 'داروی آزمایشی');
    expect(draft.strength, '125 mg');
    expect(draft.dosageForm, 'شربت');
    expect(draft.expiresAt, '05/2028');
    expect(draft.sourceText, contains('۱۲۵'));
  });

  test('recognizes Pashto dosage and expiry', () {
    final draft = parser.parse([
      'نمونه درمل',
      'پیچکاری ۵ ml',
      'د ختم نېټه: ۱۲/۲۰۲۷',
    ]);
    expect(draft.strength, '5 ml');
    expect(draft.dosageForm, 'پیچکاری');
    expect(draft.expiresAt, '12/2027');
  });

  test('normalizes Persian digits in batch numbers without changing source', () {
    final draft = parser.parse([
      'نمونه درمل',
      'شماره بچ: AB۱۲۳۴',
    ]);
    expect(draft.batchNumber, 'AB1234');
    expect(draft.sourceText, contains('AB۱۲۳۴'));
  });

  test('keeps original text available for manual review', () {
    final draft = parser.parse(['Unknown medicine', 'نوی درمل']);
    expect(draft.sourceText, 'Unknown medicine\nنوی درمل');
  });
}
