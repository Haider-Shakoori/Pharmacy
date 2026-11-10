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
  late final TextEditingController _batch;
  late final TextEditingController _expiry;
  bool _confirmed = false;

  @override
  void initState() {
    super.initState();
    _brand = TextEditingController(text: widget.draft.brandName);
    _generic = TextEditingController(text: widget.draft.genericName);
    _strength = TextEditingController(text: widget.draft.strength);
    _dosage = TextEditingController(text: widget.draft.dosageForm);
    _barcode = TextEditingController(text: widget.draft.barcode);
    _batch = TextEditingController(text: widget.draft.batchNumber);
    _expiry = TextEditingController(text: widget.draft.expiresAt);
    _brand.addListener(_refreshBrandValidation);
  }

  void _refreshBrandValidation() {
    setState(() {});
  }

  @override
  void dispose() {
    _brand.removeListener(_refreshBrandValidation);
    for (final controller in [
      _brand,
      _generic,
      _strength,
      _dosage,
      _barcode,
      _batch,
      _expiry,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Icon(
              Icons.fact_check_outlined,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'Review scanned medicine details',
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          'Check every field against the package before continuing.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _brand,
          decoration: const InputDecoration(labelText: 'Brand name *', prefixIcon: Icon(Icons.medication_outlined)),
        ),
        TextField(
          controller: _generic,
          decoration: const InputDecoration(labelText: 'Generic name', prefixIcon: Icon(Icons.science_outlined)),
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
          decoration: const InputDecoration(labelText: 'Barcode', prefixIcon: Icon(Icons.qr_code_outlined)),
        ),
        const SizedBox(height: 20),
        Text(
          'Stock receiving details (review only)',
          style: Theme.of(context).textTheme.titleSmall,
        ),
        TextField(
          controller: _batch,
          readOnly: true,
          decoration: const InputDecoration(labelText: 'Detected batch number', prefixIcon: Icon(Icons.inventory_2_outlined)),
        ),
        TextField(
          controller: _expiry,
          readOnly: true,
          decoration: const InputDecoration(labelText: 'Detected expiry date', prefixIcon: Icon(Icons.event_outlined)),
        ),
        const SizedBox(height: 8),
        Text(
          'Batch and expiry are not saved to the medicine catalog. '
          'Verify them when receiving stock.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 8),
        ExpansionTile(
          title: const Text('Recognized source text'),
          children: [SelectableText(widget.draft.sourceText)],
        ),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
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
        const SizedBox(height: 12),
      ],
    );
  }
}
