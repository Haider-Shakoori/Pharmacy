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
    // Normalize Persian/Arabic numeric glyphs only for extraction. Keep the\n    // original OCR text intact so the pharmacist can compare the label.\n    final strength = RegExp(
      r'\b\d+(?:[.,]\d+)?\s?(?:mg|mcg|µg|g|ml|iu|%)\b',
      caseSensitive: false,
    ).firstMatch(normalized)?.group(0);
    final batch = RegExp(
      r'(?:batch|lot|b\.?no\.?|بچ|شماره بچ|شماره لات)\s*[:#-]?\s*([A-Z0-9][A-Z0-9/-]{2,})',
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
    // Generic/active ingredient is a candidate only when explicitly labeled.\n    final genericMatch = RegExp(\n      r'^(?:generic(?: name)?|active ingredient|ingredient|نام جنریک|نام علمی|ماده موثره|ماده مؤثره|فعال ماده|جنریک نوم|فعاله ماده)\\s*[:：-]\\s*(.+)\
    final brandCandidates = cleaned.where(
      (line) =>
          !RegExp(
            r'^(?:batch|lot|exp|expiry|mfg|manufactured|ingredients?)\b|^(?:بچ|شماره|انقضا|تاریخ|تولید|ترکیبات|د ختم)',
            caseSensitive: false,
          ).hasMatch(line) &&
          RegExp(r'[\p{L}]', unicode: true).hasMatch(line),
    );
    return MedicineScanDraft(
      brandName: brandCandidates.isEmpty ? null : brandCandidates.first,
      genericName: generic == null || generic.isEmpty ? null : generic,\n      strength: strength,
      dosageForm: dosage,
      batchNumber: batch,
      expiresAt: expiryMatch?.group(1),
      barcode: barcode,
      sourceText: text,
    );
  }
}
,\n      caseSensitive: false,\n      multiLine: true,\n    ).firstMatch(text);\n    final generic = genericMatch?.group(1)?.trim();\n    // The first non-metadata line is a candidate, never a verified brand.
    final brandCandidates = cleaned.where(
      (line) =>
          !RegExp(
            r'^(?:batch|lot|exp|expiry|mfg|manufactured|ingredients?)\b|^(?:بچ|شماره|انقضا|تاریخ|تولید|ترکیبات|د ختم)',
            caseSensitive: false,
          ).hasMatch(line) &&
          RegExp(r'[\p{L}]', unicode: true).hasMatch(line),
    );
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
