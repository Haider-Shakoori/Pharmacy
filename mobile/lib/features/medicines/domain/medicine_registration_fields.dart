import 'medicine_registration_handoff.dart';

/// Required catalog fields are entered by a pharmacist, never guessed by OCR.
class MedicineRegistrationFields {
  const MedicineRegistrationFields({
    required this.medicineCode,
    required this.purchaseUnit,
    required this.saleUnit,
    required this.unitsPerPurchaseUnit,
    required this.reorderLevel,
    required this.prescriptionRequired,
    required this.batchTrackingRequired,
    required this.expiryTrackingRequired,
  });

  final String medicineCode;
  final String purchaseUnit;
  final String saleUnit;
  final double unitsPerPurchaseUnit;
  final double reorderLevel;
  final bool prescriptionRequired;
  final bool batchTrackingRequired;
  final bool expiryTrackingRequired;

  Map<String, Object?> validatedPayload(MedicineRegistrationHandoff handoff) {
    final code = medicineCode.trim();
    final purchase = purchaseUnit.trim();
    final sale = saleUnit.trim();
    if (code.isEmpty || code.length > 80) {
      throw ArgumentError('A medicine code (max 80 characters) is required');
    }
    if (purchase.isEmpty || purchase.length > 50 ||
        sale.isEmpty || sale.length > 50) {
      throw ArgumentError('Valid purchase and sale units are required');
    }
    if (!unitsPerPurchaseUnit.isFinite ||
        unitsPerPurchaseUnit <= 0 ||
        unitsPerPurchaseUnit > 99999999) {
      throw ArgumentError('Units per purchase unit must be positive');
    }
    if (!reorderLevel.isFinite || reorderLevel < 0 || reorderLevel > 999999999) {
      throw ArgumentError('Reorder level must be nonnegative');
    }
    return <String, Object?>{
      'medicine_code': code,
      'brand_name': handoff.brandName,
      'generic_name': handoff.genericName,
      'strength': handoff.strength,
      'dosage_form': handoff.dosageForm,
      'barcode': handoff.barcode,
      'purchase_unit': purchase,
      'sale_unit': sale,
      'units_per_purchase_unit': unitsPerPurchaseUnit,
      'reorder_level': reorderLevel,
      'prescription_required': prescriptionRequired,
      'batch_tracking_required': batchTrackingRequired,
      'expiry_tracking_required': expiryTrackingRequired,
      'is_active': true,
    };
  }
}
