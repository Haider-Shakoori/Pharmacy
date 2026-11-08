import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:flutter/material.dart';

/// Review-only UI. It does not create medicines or change inventory.
class MedicineScanReviewPanel extends StatefulWidget {
  const MedicineScanReviewPanel({
    required this.draft,
    required this.onConfirmed,
    super.key,
  });

  final MedicineScanDraft draft;
  final ValueChanged<ReviewedMedicineScan> onConfirmed;

  @override
  State<MedicineScanReviewPanel> createState() =>
      _MedicineScanReviewPanelState();
}

class _MedicineScanReviewPanelState extends State<MedicineScanReviewPanel> {
  late final TextEditingController _brand;
  late final TextEditingController _generic;
  late final TextEditingController _strength;
  late final TextEditingController _dosage;
  late final TextEditingController _barcode;
  bool _confirmed = false;

  @override
  void initState() {
    super.initState();
    _brand = TextEditingController(text: widget.draft.brandName);
    _generic = TextEditingController(text: widget.draft.genericName);
    _strength = TextEditingController(text: widget.draft.strength);
    _dosage = TextEditingController(text: widget.draft.dosageForm);
    _barcode = TextEditingController(text: widget.draft.barcode);
    _brand.addListener(_refreshBrandValidation);
  }

  void _refreshBrandValidation() {
    setState(() {});
  }

  @override
  void dispose() {
    _brand.removeListener(_refreshBrandValidation);
    for (final controller in [_brand, _generic, _strength, _dosage, _barcode]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Review scanned medicine details'),
        const SizedBox(height: 8),
        TextField(
          controller: _brand,
          decoration: const InputDecoration(labelText: 'Brand name *'),
        ),
        TextField(
          controller: _generic,
          decoration: const InputDecoration(labelText: 'Generic name'),
        ),
        TextField(
          controller: _strength,
          decoration: const InputDecoration(labelText: 'Strength'),
        ),
        TextField(
          controller: _dosage,
          decoration: const InputDecoration(labelText: 'Dosage form'),
        ),
        TextField(
          controller: _barcode,
          decoration: const InputDecoration(labelText: 'Barcode'),
        ),
        if (widget.draft.batchNumber != null || widget.draft.expiresAt != null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Text(
              'Batch and expiry belong to stock receiving, not medicine registration.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        ExpansionTile(
          title: const Text('Recognized source text'),
          children: [SelectableText(widget.draft.sourceText)],
        ),
        CheckboxListTile(
          value: _confirmed,
          onChanged: (value) => setState(() => _confirmed = value ?? false),
          title: const Text('I have checked the medicine label and details'),
        ),
        FilledButton(
          onPressed: !_confirmed || _brand.text.trim().isEmpty
              ? null
              : () {
                  final reviewed = const MedicineScanReview().confirm(
                    draft: widget.draft,
                    pharmacistConfirmed: _confirmed,
                    brandName: _brand.text,
                    genericName: _generic.text,
                    strength: _strength.text,
                    dosageForm: _dosage.text,
                    barcode: _barcode.text,
                  );
                  widget.onConfirmed(reviewed);
                },
          child: const Text('Use reviewed details'),
        ),
      ],
    );
  }
}
