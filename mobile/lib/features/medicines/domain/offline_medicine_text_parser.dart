/// Deterministic, offline-only interpretation of OCR text.
///
/// This does not perform OCR. Native platform OCR adapters will supply the
/// recognized lines. All values are suggestions requiring pharmacist review.
class MedicineScanDraft {
  const MedicineScanDraft({
    this.brandName,
    this.genericName,
    this.strength,
    this.dosageForm,
    this.batchNumber,
    this.expiresAt,
    this.barcode,
    required this.sourceText,
  });

  final String? brandName;
  final String? genericName;
  final String? strength;
  final String? dosageForm;
  final String? batchNumber;
  final String? expiresAt;
  final String? barcode;
  final String sourceText;
}

class OfflineMedicineTextParser {
  const OfflineMedicineTextParser();

  MedicineScanDraft parse(List<String> lines, {String? barcode}) {
    final cleaned = lines.map((line) => line.trim()).where((line) => line.isNotEmpty).toList();
    final text = cleaned.join('\n');
    final strength = RegExp(
      r'\b\d+(?:[.,]\d+)?\s?(?:mg|mcg|µg|g|ml|iu|%)\b',
      caseSensitive: false,
    ).firstMatch(text)?.group(0);
    final batch = RegExp(
      r'\b(?:batch|lot|b\.?no\.?)\s*[:#-]?\s*([A-Z0-9][A-Z0-9/-]{2,})\b',
      caseSensitive: false,
    ).firstMatch(text)?.group(1);
    final expiryMatch = RegExp(
      r'\b(?:exp|expiry|expires)\s*[:.-]?\s*(\d{1,2}[/.-]\d{4}|\d{4}[/.-]\d{1,2})\b',
      caseSensitive: false,
    ).firstMatch(text);
    final dosage = RegExp(
      r'\b(tablets?|capsules?|syrup|suspension|injection|cream|ointment|drops)\b',
      caseSensitive: false,
    ).firstMatch(text)?.group(0);
    // The first non-metadata line is a candidate, never a verified brand.
    final brandCandidates = cleaned.where((line) =>
      !RegExp(r'^(?:batch|lot|exp|expiry|mfg|manufactured|ingredients?)\b', caseSensitive: false).hasMatch(line)
      && RegExp(r'[\p{L}]', unicode: true).hasMatch(line));
    return MedicineScanDraft(
      brandName: brandCandidates.isEmpty ? null : brandCandidates.first,
      strength: strength,
      dosageForm: dosage,
      batchNumber: batch,
      expiresAt: expiryMatch?.group(1),
      barcode: barcode,
      sourceText: text,
    );
  }
}
