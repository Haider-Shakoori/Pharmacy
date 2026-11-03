import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_duplicate_detector.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const detector = OfflineMedicineDuplicateDetector();
  const catalog = [
    LocalMedicineIdentity(
      id: '1',
      brandName: 'Example Med',
      strength: '500 mg',
      barcode: '0123456789012',
    ),
    LocalMedicineIdentity(
      id: '2',
      brandName: 'Other Med',
      strength: '250 mg',
    ),
  ];

  test('matches existing barcode without creating a record', () {
    const reviewed = ReviewedMedicineScan(
      brandName: 'Different label',
      genericName: null,
      strength: null,
      dosageForm: null,
      barcode: '0123456789012',
    );
    final matches = detector.findMatches(reviewed, catalog);
    expect(matches, hasLength(1));
    expect(matches.single.medicine.id, '1');
    expect(matches.single.reason, MedicineDuplicateReason.barcode);
    expect(catalog, hasLength(2));
  });

  test('matches normalized brand and strength without barcode', () {
    const reviewed = ReviewedMedicineScan(
      brandName: ' examplemed ',
      genericName: null,
      strength: '500mg',
      dosageForm: null,
      barcode: null,
    );
    final matches = detector.findMatches(reviewed, catalog);
    expect(matches.single.reason, MedicineDuplicateReason.brandAndStrength);
  });

  test('does not match on brand alone', () {
    const reviewed = ReviewedMedicineScan(
      brandName: 'Example Med',
      genericName: null,
      strength: null,
      dosageForm: null,
      barcode: null,
    );
    expect(detector.findMatches(reviewed, catalog), isEmpty);
  });
}
