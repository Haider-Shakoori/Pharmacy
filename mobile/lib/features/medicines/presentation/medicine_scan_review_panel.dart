import 'package:businessos_pharmacy/core/localization/app_locale.dart';
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

  String _localized(BuildContext context, String en, String fa, String ps) {
    final locale = AppLocale.fromCode(
      Localizations.localeOf(context).languageCode,
    );
    switch (locale) {
      case AppLocale.dari:
        return fa;
      case AppLocale.pashto:
        return ps;
      case AppLocale.english:
        return en;
    }
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
                _localized(context, 'Review scanned medicine details', 'بررسی جزئیات دوای اسکن‌شده', 'د سکین شويو درملو معلومات وڅېړئ'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          _localized(context, 'Check every field against the package before continuing.', 'پیش از ادامه، هر بخش را با بسته دوا مقایسه کنید.', 'له دوام مخکې هره برخه د درملو له بسته سره پرتله کړئ.'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _brand,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: _localized(context, 'Brand name *', 'نام تجارتی *', 'سوداګریز نوم *'),
            prefixIcon: Icon(Icons.medication_outlined),
          ),
        ),
        TextField(
          controller: _generic,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            labelText: _localized(context, 'Generic name', 'نام عمومی', 'عمومي نوم'),
            prefixIcon: Icon(Icons.science_outlined),
          ),
        ),
        TextField(
          controller: _strength,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(labelText: _localized(context, 'Strength', 'قدرت دوا', 'د درملو قوت')),
        ),
        TextField(
          controller: _dosage,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(labelText: _localized(context, 'Dosage form', 'شکل دوا', 'د درملو بڼه')),
        ),
        TextField(
          controller: _barcode,
          textInputAction: TextInputAction.done,
          keyboardType: TextInputType.text,
          decoration: const InputDecoration(
            labelText: _localized(context, 'Barcode', 'بارکود', 'بارکوډ'),
            prefixIcon: Icon(Icons.qr_code_outlined),
          ),
        ),
        const SizedBox(height: 20),
        Text(
          _localized(context, 'Stock receiving details (review only)', 'جزئیات دریافت موجودی (فقط بررسی)', 'د توکو د ترلاسه کولو معلومات (یوازې کتنه)'),
          style: Theme.of(context).textTheme.titleSmall,
        ),
        TextField(
          controller: _batch,
          readOnly: true,
          decoration: const InputDecoration(
            labelText: _localized(context, 'Detected batch number', 'شماره بچ شناسایی‌شده', 'د پېژندل شوې بستې شمېره'),
            prefixIcon: Icon(Icons.inventory_2_outlined),
          ),
        ),
        TextField(
          controller: _expiry,
          readOnly: true,
          decoration: const InputDecoration(
            labelText: _localized(context, 'Detected expiry date', 'تاریخ انقضای شناسایی‌شده', 'د پېژندل شوې پای نېټه'),
            prefixIcon: Icon(Icons.event_outlined),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _localized(
            context,
            'Batch and expiry are not saved to the medicine catalog. Verify them when receiving stock.',
            'شماره بچ و تاریخ انقضا در فهرست دوا ذخیره نمی‌شوند. هنگام دریافت موجودی آن‌ها را بررسی کنید.',
            'د بستې شمېره او د پای نېټه د درملو په لېست کې نه ساتل کېږي. د توکو د ترلاسه کولو پر مهال یې وګورئ.',
          ),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 8),
        ExpansionTile(
          title: Text(_localized(context, 'Recognized source text', 'متن شناسایی‌شده', 'پېژندل شوی متن')),
          children: [SelectableText(widget.draft.sourceText)],
        ),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _confirmed,
          onChanged: (value) => setState(() => _confirmed = value ?? false),
          title: Text(_localized(context, 'I have checked the medicine label and details', 'برچسب و جزئیات دوا را بررسی کرده‌ام', 'ما د درملو لیبل او معلومات کتلي دي')),
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
          child: Text(_localized(context, 'Use reviewed details', 'استفاده از جزئیات بررسی‌شده', 'کتل شوي معلومات وکاروئ')),
        ),
        const SizedBox(height: 12),
      ],
    );
  }
}
