import 'offline_medicine_text_parser.dart';

/// Only explicit pharmacist confirmation can turn scan suggestions into
/// registration fields. OCR output alone must never create a medicine.
class ReviewedMedicineScan {
  const ReviewedMedicineScan({
    required this.brandName,
    required this.genericName,
    required this.strength,
    required this.dosageForm,
    required this.barcode,
  });

  final String brandName;
  final String? genericName;
  final String? strength;
  final String? dosageForm;
  final String? barcode;
}

class MedicineScanReview {
  const MedicineScanReview();

  ReviewedMedicineScan confirm({
    required MedicineScanDraft draft,
    required bool pharmacistConfirmed,
    required String brandName,
    String? genericName,
    String? strength,
    String? dosageForm,
    String? barcode,
  }) {
    if (!pharmacistConfirmed) {
      throw StateError('Pharmacist review is required before registration');
    }
    final brand = brandName.trim();
    if (brand.isEmpty) {
      throw ArgumentError.value(brandName, 'brandName', 'Brand name required');
    }
    String? clean(String? value) {
      final trimmed = value?.trim();
      return trimmed == null || trimmed.isEmpty ? null : trimmed;
    }

    // Batch and expiry intentionally excluded: these belong to stock receipts,
    // not the medicine catalog.
    return ReviewedMedicineScan(
      brandName: brand,
      genericName: clean(genericName),
      strength: clean(strength),
      dosageForm: clean(dosageForm),
      barcode: clean(barcode),
    );
  }
}
