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

  test(
    'normalizes Persian digits in batch numbers without changing source',
    () {
      final draft = parser.parse(['نمونه درمل', 'شماره بچ: AB۱۲۳۴']);
      expect(draft.batchNumber, 'AB1234');
      expect(draft.sourceText, contains('AB۱۲۳۴'));
    },
  );

  test('extracts explicitly labeled Dari generic name', () {
    final draft = parser.parse([
      'برند آزمایشی',
      'نام جنریک: پاراستامول',
      'شربت ۱۲۵ mg',
    ]);
    expect(draft.brandName, 'برند آزمایشی');
    expect(draft.genericName, 'پاراستامول');
    expect(draft.strength, '125 mg');
  });

  test('extracts explicitly labeled Pashto generic name and batch', () {
    final draft = parser.parse([
      'نمونه درمل',
      'جنریک نوم: پاراسټامول',
      'د بچ شمېره: AB۱۲۳۴',
    ]);
    expect(draft.brandName, 'نمونه درمل');
    expect(draft.genericName, 'پاراسټامول');
    expect(draft.batchNumber, 'AB1234');
  });

  test('does not guess generic name without an explicit label', () {
    final draft = parser.parse(['Brand X', 'Unknown text']);
    expect(draft.genericName, isNull);
  });

  test('keeps original text available for manual review', () {
    final draft = parser.parse(['Unknown medicine', 'نوی درمل']);
    expect(draft.sourceText, 'Unknown medicine\nنوی درمل');
  });
}
