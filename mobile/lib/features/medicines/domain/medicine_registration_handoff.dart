import 'medicine_scan_review.dart';

/// An explicit, non-persisted handoff from OCR review to medicine registration.
/// This is not an API request and must never be queued as an inventory write.
class MedicineRegistrationHandoff {
  const MedicineRegistrationHandoff._({
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

  /// Refuse to prepare a registration while duplicate checking is unresolved.
  /// The receiving registration service must still validate against the server.
  static MedicineRegistrationHandoff prepare({
    required ReviewedMedicineScan reviewed,
    required bool duplicateCheckCompleted,
    required List<String> possibleDuplicates,
  }) {
    if (!duplicateCheckCompleted) {
      throw StateError('Local duplicate check has not completed');
    }
    if (possibleDuplicates.isNotEmpty) {
      throw StateError('Potential duplicate medicines require resolution');
    }
    final brand = reviewed.brandName.trim();
    if (brand.isEmpty) {
      throw ArgumentError.value(brand, 'brandName', 'Brand name required');
    }
    return MedicineRegistrationHandoff._(
      brandName: brand,
      genericName: reviewed.genericName,
      strength: reviewed.strength,
      dosageForm: reviewed.dosageForm,
      barcode: reviewed.barcode,
    );
  }
}
