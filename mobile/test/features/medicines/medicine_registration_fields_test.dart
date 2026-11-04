import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_fields.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final handoff = MedicineRegistrationHandoff.prepare(
    reviewed: const ReviewedMedicineScan(
      brandName: 'Example medicine',
      genericName: null,
      strength: null,
      dosageForm: null,
      barcode: null,
    ),
    duplicateCheckCompleted: true,
    possibleDuplicates: const <String>[],
  );

  MedicineRegistrationFields fields({
    double units = 10,
    double reorder = 0,
  }) => MedicineRegistrationFields(
    medicineCode: 'TEST-001',
    purchaseUnit: 'pack',
    saleUnit: 'unit',
    unitsPerPurchaseUnit: units,
    reorderLevel: reorder,
    prescriptionRequired: false,
    batchTrackingRequired: true,
    expiryTrackingRequired: true,
  );

  test('accepts server maximum numeric values', () {
    final payload = fields(
      units: 99999999,
      reorder: 999999999,
    ).validatedPayload(handoff);
    expect(payload['units_per_purchase_unit'], 99999999);
    expect(payload['reorder_level'], 999999999);
  });

  test('rejects nonfinite and out-of-range quantities', () {
    for (final value in <double>[
      double.nan,
      double.infinity,
      -1,
      0,
      100000000,
    ]) {
      expect(
        () => fields(units: value).validatedPayload(handoff),
        throwsArgumentError,
      );
    }
    for (final value in <double>[
      double.nan,
      double.negativeInfinity,
      -1,
      1000000000,
    ]) {
      expect(
        () => fields(reorder: value).validatedPayload(handoff),
        throwsArgumentError,
      );
    }
  });

  test('trims medicine code before submission', () {
    const input = MedicineRegistrationFields(
      medicineCode: '  TEST-001  ',
      purchaseUnit: 'pack',
      saleUnit: 'unit',
      unitsPerPurchaseUnit: 10,
      reorderLevel: 0,
      prescriptionRequired: false,
      batchTrackingRequired: true,
      expiryTrackingRequired: true,
    );
    expect(input.validatedPayload(handoff)['medicine_code'], 'TEST-001');
  });
}
