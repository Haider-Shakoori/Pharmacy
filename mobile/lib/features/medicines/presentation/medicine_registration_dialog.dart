import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_registration_api.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_registration_submission.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_fields.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_form_state.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

class MedicineRegistrationDialog extends StatefulWidget {
  const MedicineRegistrationDialog({
    required this.handoff,
    required this.registration,
    super.key,
  });

  final MedicineRegistrationHandoff handoff;
  final MobileRegistration registration;

  @override
  State<MedicineRegistrationDialog> createState() =>
      _MedicineRegistrationDialogState();
}

class _MedicineRegistrationDialogState
    extends State<MedicineRegistrationDialog> {
  final _key = GlobalKey<FormState>();
  final _code = TextEditingController();
  final _purchase = TextEditingController(text: 'pack');
  final _sale = TextEditingController(text: 'unit');
  final _units = TextEditingController(text: '1');
  final _reorder = TextEditingController(text: '0');
  bool _approved = false;
  bool _prescription = false;
  bool _batch = true;
  bool _expiry = true;
  bool _submitting = false;

  @override
  void dispose() {
    for (final controller in [_code, _purchase, _sale, _units, _reorder]) {
      controller.dispose();
    }
    super.dispose();
  }

  Widget _field(String label, TextEditingController controller) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        decoration: InputDecoration(labelText: label),
        validator: (value) {
          if (value == null || value.trim().isEmpty) return 'Required';
          if (controller == _units || controller == _reorder) {
            final number = double.tryParse(value.trim());
            if (number == null ||
                !number.isFinite ||
                (controller == _units && (number <= 0 || number > 99999999)) ||
                (controller == _reorder &&
                    (number < 0 || number > 999999999))) {
              return 'Enter a valid number';
            }
          }
          return null;
        },
      ),
    );
  }

  Future<void> _submit() async {
    if (_submitting || !_key.currentState!.validate() || !_approved) return;
    final client = http.Client();
    setState(() => _submitting = true);
    try {
      final fields = MedicineRegistrationFields(
        medicineCode: _code.text,
        purchaseUnit: _purchase.text,
        saleUnit: _sale.text,
        unitsPerPurchaseUnit: double.parse(_units.text),
        reorderLevel: double.parse(_reorder.text),
        prescriptionRequired: _prescription,
        batchTrackingRequired: _batch,
        expiryTrackingRequired: _expiry,
      );
      final form = MedicineRegistrationFormState(
        handoff: widget.handoff,
        fields: fields,
        pharmacistApproved: _approved,
      );
      final id = await MedicineRegistrationSubmission(
        MedicineRegistrationApi(
          environment: AppEnvironment.current,
          client: client,
        ),
      ).submit(registration: widget.registration, form: form);
      if (mounted) Navigator.of(context).pop(id);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Registration failed: $error')));
      }
    } finally {
      client.close();
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Register medicine'),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Form(
            key: _key,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Reviewed: ${widget.handoff.brandName}'),
                _field('Medicine code', _code),
                _field('Purchase unit', _purchase),
                _field('Sale unit', _sale),
                _field('Units per purchase unit', _units),
                _field('Reorder level', _reorder),
                SwitchListTile(
                  title: const Text('Prescription required'),
                  value: _prescription,
                  onChanged: (value) => setState(() => _prescription = value),
                ),
                SwitchListTile(
                  title: const Text('Batch tracking'),
                  value: _batch,
                  onChanged: (value) => setState(() => _batch = value),
                ),
                SwitchListTile(
                  title: const Text('Expiry tracking'),
                  value: _expiry,
                  onChanged: (value) => setState(() => _expiry = value),
                ),
                CheckboxListTile(
                  title: const Text('I approve this medicine registration'),
                  value: _approved,
                  onChanged: (value) =>
                      setState(() => _approved = value ?? false),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _submitting ? null : () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _submitting || !_approved ? null : _submit,
          child: Text(_submitting ? 'Registering…' : 'Register'),
        ),
      ],
    );
  }
}
