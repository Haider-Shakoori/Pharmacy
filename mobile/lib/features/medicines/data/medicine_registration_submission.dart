import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_registration_api.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_form_state.dart';

/// Guards against repeated taps while a registration request is pending.
class MedicineRegistrationSubmission {
  MedicineRegistrationSubmission(this.api);

  final MedicineRegistrationApi api;
  bool _submitting = false;

  bool get isSubmitting => _submitting;

  Future<String> submit({
    required MobileRegistration registration,
    required MedicineRegistrationFormState form,
  }) async {
    if (_submitting) {
      throw StateError('A medicine registration is already in progress');
    }
    _submitting = true;
    try {
      return await api.create(registration: registration, form: form);
    } finally {
      _submitting = false;
    }
  }
}
