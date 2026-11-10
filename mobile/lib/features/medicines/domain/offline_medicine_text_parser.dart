/// Deterministic offline parsing of recognized medicine-label text.
/// All extracted fields are unverified until pharmacist review.
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
    final cleaned = lines
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .toList();
    final text = cleaned.join('\n');
    const regionalDigits = '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹';
    final normalized = text.replaceAllMapped(
      RegExp('[${RegExp.escape(regionalDigits)}]'),
      (match) => '${regionalDigits.indexOf(match.group(0)!) % 10}',
    );

    // Normalize numeric glyphs only for extraction; retain original OCR text.
    final strength = RegExp(
      r'\b\d+(?:[.,]\d+)?\s?(?:mg|mcg|µg|g|ml|iu)\b|\b\d+(?:[.,]\d+)?\s?%',
      caseSensitive: false,
    ).firstMatch(normalized)?.group(0);
    final batch = RegExp(
      r'(?:batch|lot|b\.?no\.?|شماره بچ|شماره لات|بچ|د بچ شمېره)\s*[:#-]?\s*([A-Z0-9][A-Z0-9/-]{2,})',
      caseSensitive: false,
    ).firstMatch(normalized)?.group(1);
    final expiryMatch = RegExp(
      r'(?:exp|expiry|expires|انقضا|انتهاء|تاریخ انقضا|د ختم نېټه)\s*[:.-]?\s*(\d{1,2}[/.-]\d{2,4}|\d{4}[/.-]\d{1,2})',
      caseSensitive: false,
    ).firstMatch(normalized);
    final dosage = RegExp(
      r'(?:\b(?:tablets?|capsules?|syrup|suspension|injection|cream|ointment|drops)\b|قرص|تابلیت|کپسول|شربت|تزریق|پیچکاری|کریم|قطره|څاڅکي)',
      caseSensitive: false,
    ).firstMatch(text)?.group(0);

    // Only infer a generic ingredient when the label explicitly names it.
    final genericMatch = RegExp(
      r'^(?:generic(?: name)?|active ingredient|ingredient|نام جنریک|نام علمی|ماده موثره|ماده مؤثره|فعال ماده|جنریک نوم|فعاله ماده)\s*[:：-]\s*(.+)$',
      caseSensitive: false,
      multiLine: true,
    ).firstMatch(text);
    final generic = genericMatch?.group(1)?.trim();

    final brandCandidates = cleaned.where(
      (line) =>
          !RegExp(
            r'^(?:batch|lot|exp|expiry|mfg|manufactured|ingredients?|generic|active)\b|^(?:بچ|شماره|انقضا|تاریخ|تولید|ترکیبات|نام جنریک|نام علمی|ماده|فعال ماده|جنریک نوم|فعاله ماده|د بچ|د ختم)',
            caseSensitive: false,
          ).hasMatch(line) &&
          RegExp(r'[\p{L}]', unicode: true).hasMatch(line),
    );
    return MedicineScanDraft(
      brandName: brandCandidates.isEmpty ? null : brandCandidates.first,
      genericName: generic == null || generic.isEmpty ? null : generic,
      strength: strength,
      dosageForm: dosage,
      batchNumber: batch,
      expiresAt: expiryMatch?.group(1),
      barcode: barcode,
      sourceText: text,
    );
  }
}
