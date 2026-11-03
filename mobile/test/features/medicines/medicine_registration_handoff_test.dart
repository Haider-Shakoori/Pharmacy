import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const reviewed = ReviewedMedicineScan(
    brandName: '  Example  ',
    genericName: 'Ingredient',
    strength: '500 mg',
    dosageForm: 'Tablet',
    barcode: '12345678',
  );

  test('refuses handoff before duplicate lookup completes', () {
    expect(
      () => MedicineRegistrationHandoff.prepare(
        reviewed: reviewed,
        duplicateCheckCompleted: false,
        possibleDuplicates: <String>[],
      ),
      throwsStateError,
    );
  });

  test('refuses handoff when potential duplicates exist', () {
    expect(
      () => MedicineRegistrationHandoff.prepare(
        reviewed: reviewed,
        duplicateCheckCompleted: true,
        possibleDuplicates: <String>['Example'],
      ),
      throwsStateError,
    );
  });

  test('prepares reviewed fields without saving or stock data', () {
    final handoff = MedicineRegistrationHandoff.prepare(
      reviewed: reviewed,
      duplicateCheckCompleted: true,
      possibleDuplicates: <String>[],
    );
    expect(handoff.brandName, 'Example');
    expect(handoff.genericName, 'Ingredient');
    expect(handoff.strength, '500 mg');
    expect(handoff.dosageForm, 'Tablet');
    expect(handoff.barcode, '12345678');
  });
}
