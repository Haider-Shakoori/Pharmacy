import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_fields.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_form_state.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('registration refuses missing pharmacist approval', () {
    final handoff = MedicineRegistrationHandoff.prepare(
      reviewed: const ReviewedMedicineScan(
        brandName: 'Test medicine',
        genericName: null,
        strength: null,
        dosageForm: null,
        barcode: null,
      ),
      duplicateCheckCompleted: true,
      possibleDuplicates: const [],
    );
    final form = MedicineRegistrationFormState(
      handoff: handoff,
      fields: const MedicineRegistrationFields(
        medicineCode: 'TEST-001',
        purchaseUnit: 'pack',
        saleUnit: 'unit',
        unitsPerPurchaseUnit: 10,
        reorderLevel: 0,
        prescriptionRequired: false,
        batchTrackingRequired: true,
        expiryTrackingRequired: true,
      ),
      pharmacistApproved: false,
    );
    expect(form.validatedSubmission, throwsStateError);
  });
}
