import 'medicine_scan_review.dart';

/// Offline duplicate matching for a locally cached medicine catalog.
///
/// A match is advisory only: never update or merge an existing medicine
/// automatically. All strings are normalized locally without network calls.
class LocalMedicineIdentity {
  const LocalMedicineIdentity({
    required this.id,
    required this.brandName,
    this.genericName,
    this.strength,
    this.barcode,
  });

  final String id;
  final String brandName;
  final String? genericName;
  final String? strength;
  final String? barcode;
}

enum MedicineDuplicateReason { barcode, brandAndStrength }

class MedicineDuplicateMatch {
  const MedicineDuplicateMatch(this.medicine, this.reason);

  final LocalMedicineIdentity medicine;
  final MedicineDuplicateReason reason;
}

class OfflineMedicineDuplicateDetector {
  const OfflineMedicineDuplicateDetector();

  List<MedicineDuplicateMatch> findMatches(
    ReviewedMedicineScan reviewed,
    Iterable<LocalMedicineIdentity> catalog,
  ) {
    String normalize(String? value) =>
        (value ?? '').toLowerCase().replaceAll(RegExp(r'\s+'), '').trim();

    final barcode = normalize(reviewed.barcode);
    final brand = normalize(reviewed.brandName);
    final strength = normalize(reviewed.strength);
    final results = <MedicineDuplicateMatch>[];
    for (final medicine in catalog) {
      if (barcode.isNotEmpty && normalize(medicine.barcode) == barcode) {
        results.add(
          MedicineDuplicateMatch(medicine, MedicineDuplicateReason.barcode),
        );
      } else if (brand.isNotEmpty &&
          strength.isNotEmpty &&
          normalize(medicine.brandName) == brand &&
          normalize(medicine.strength) == strength) {
        results.add(
          MedicineDuplicateMatch(
            medicine,
            MedicineDuplicateReason.brandAndStrength,
          ),
        );
      }
    }
    return results;
  }
}
