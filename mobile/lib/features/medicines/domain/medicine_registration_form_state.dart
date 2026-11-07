import 'medicine_registration_fields.dart';
import 'medicine_registration_handoff.dart';

/// A pharmacist must supply catalog values; OCR cannot invent these.
class MedicineRegistrationFormState {
  const MedicineRegistrationFormState({
    required this.handoff,
    required this.fields,
    required this.pharmacistApproved,
  });

  final MedicineRegistrationHandoff handoff;
  final MedicineRegistrationFields fields;
  final bool pharmacistApproved;

  Map<String, Object?> validatedSubmission() {
    if (!pharmacistApproved) {
      throw StateError('Pharmacist approval is required');
    }
    return <String, Object?>{
      ...fields.validatedPayload(handoff),
      'pharmacist_approved': true,
    };
  }
}
