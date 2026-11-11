/// Deterministic offline parsing of recognized medicine-label text.
/// All extracted fields are unverified until pharmacist review.
class MedicineScanDraft {
  const MedicineScanDraft({
    this.brandName,
    this.genericName,
    this.strength,
    this.dosageForm,
    this.packQuantity,
    this.batchNumber,
    this.expiresAt,
    this.barcode,
    required this.sourceText,
  });

  final String? brandName;
  final String? genericName;
  final String? strength;
  final String? dosageForm;
  final String? packQuantity;
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
    final dosageMatches = RegExp(
      r'(?:\b(?:tablets|tablet|capsules|capsule|syrup|suspension|injection|cream|ointment|drops)\b|قرص|تابلیت|کپسول|شربت|تزریق|پیچکاری|کریم|قطره|څاڅکي)',
      caseSensitive: false,
    ).allMatches(text).map((match) => match.group(0)!).toList();
    // Pack count is separate from the dosage form and is review-only.
    final packQuantity = RegExp(
      r'\b\d+\s*(?:capsules?|tablets?|vials?|ampoules?)\b',
      caseSensitive: false,
    ).firstMatch(normalized)?.group(0);
    final dosage = cleaned.any((line) => line.toLowerCase() == 'eye drops')
        ? 'Eye Drops'
        : dosageMatches
                  .where(
                    (value) =>
                        value.toLowerCase() == 'tablets' ||
                        value.toLowerCase() == 'capsules',
                  )
                  .firstOrNull ??
              (dosageMatches.isEmpty ? null : dosageMatches.first);

    // Prefer a visible product name over OCR fragments or legal text.
    // Keep the original order as a tie-breaker.
    final brandCandidates = cleaned.where((line) {
      if (!RegExp(r'[\p{L}]', unicode: true).hasMatch(line)) return false;
      if (line.trim().length < 4) return false;
      if (line.toLowerCase() == 'eye drops' ||
          line.toLowerCase() == 'ophthalmic' ||
          line.toLowerCase() == 'solution' ||
          line.toLowerCase() == 'hydrochloride') {
        return false;
      }
      if (line.toLowerCase().endsWith('prazole') ||
          line.toLowerCase().endsWith('prazol')) {
        return false;
      }
      if (RegExp(
        r'^(?:each|contains|paracetamol|diclofenac|magnesium|colour|dosage|store|prescription|manufactured|manufacturer|mfg|batch|lot|exp|expiry|generic|active|ingredients?|tablets?|capsules?|rx\b|ip\b|mg\b|شماره|انقضا|تاریخ|تولید|ترکیبات|نام جنریک|نام علمی|ماده|فعال ماده)',
        caseSensitive: false,
      ).hasMatch(line)) {
        return false;
      }
      return true;
    }).toList();
    int brandScore(String line) {
      final words = RegExp(r'[\p{L}]+', unicode: true).allMatches(line).length;
      var score = words >= 2 && words <= 4 ? 4 : 0;
      if (RegExp(
        r'\b(?:plus|forte|extra)\b',
        caseSensitive: false,
      ).hasMatch(line)) {
        score += 5;
      }
      if (RegExp(r'\d').hasMatch(line)) score -= 7;
      if (RegExp(
        r'\b(?:mg|mcg|tablets?|capsules?|ml)\b',
        caseSensitive: false,
      ).hasMatch(line)) {
        score -= 8;
      }
      if (words == 1 && !RegExp(r'\d').hasMatch(line)) score += 2;
      if (line.length > 42) score -= 5;
      if (RegExp(
        r'\b(?:pvt|ltd|limited|company|global|certified)\b',
        caseSensitive: false,
      ).hasMatch(line)) {
        score -= 8;
      }
      return score;
    }

    // Repeated near-matching OCR strings are more credible than a one-off
    // manufacturer logo. Do not silently correct their spelling.
    int similarity(String left, String right) {
      final a = left.toLowerCase();
      final b = right.toLowerCase();
      if (a == b) return 3;
      if ((a.length - b.length).abs() > 2) return 0;
      var differences = (a.length - b.length).abs();
      final limit = a.length < b.length ? a.length : b.length;
      for (var i = 0; i < limit; i++) {
        if (a[i] != b[i]) differences++;
      }
      return differences <= 2 ? 2 : 0;
    }

    int repeatScore(String candidate) {
      return cleaned.where((line) => similarity(candidate, line) > 0).length;
    }

    brandCandidates.sort((a, b) {
      final scoreA = brandScore(a) + repeatScore(a) * 4;
      final scoreB = brandScore(b) + repeatScore(b) * 4;
      return scoreB.compareTo(scoreA);
    });

    // Identify composition by nearby ingredient/strength pairs, not by
    // a fixed medicine-name dictionary or a particular package position.
    final amountPattern = RegExp(
      r'\b\d+(?:[.,]\d+)?\s?(?:mg|mcg|µg|g|ml|iu|%)\b',
      caseSensitive: false,
    );
    final composition = <String>[];
    final ingredients = <String>[];
    final genericMatch = RegExp(
      r'^(?:generic(?: name)?|active ingredients?|ingredients?|composition|نام جنریک|نام علمی|ماده موثره|ماده مؤثره|فعال ماده|جنریک نوم|فعاله ماده)\s*[:：-]\s*(.+)$',
      caseSensitive: false,
      multiLine: true,
    ).firstMatch(text);
    final labelledGeneric = genericMatch?.group(1)?.trim();
    final compositionHeader = RegExp(
      r'(?:each\s+(?:uncoated\s+)?(?:tablet|capsule)\s+contains|composition|active ingredients?|ترکیبات|اجزا)',
      caseSensitive: false,
    );
    final stopComposition = RegExp(
      r'^(?:colour|color|dosage|store|storage|warning|prescription|manufactured|mfg|batch|lot|exp|expiry|شماره|انقضا|تولید)',
      caseSensitive: false,
    );
    var inComposition = false;
    for (var i = 0; i < cleaned.length; i++) {
      final line = cleaned[i];
      if (compositionHeader.hasMatch(line)) {
        inComposition = true;
        continue;
      }
      if (stopComposition.hasMatch(line)) inComposition = false;
      if (!inComposition) continue;
      final amounts = amountPattern.allMatches(line).toList();
      if (amounts.isEmpty) continue;
      for (final amount in amounts) {
        final before = line
            .substring(0, amount.start)
            .replaceAll(
              RegExp(r'\b(?:IP|BP|USP|EP)\b', caseSensitive: false),
              '',
            )
            .trim();
        final ingredient = before
            .replaceAll(RegExp(r'[^\p{L}\s-]', unicode: true), '')
            .trim();
        if (ingredient.length < 3) continue;
        if (!ingredients.any(
          (value) => value.toLowerCase() == ingredient.toLowerCase(),
        )) {
          ingredients.add(ingredient);
          composition.add(amount.group(0)!);
        }
      }
    }
    // Repeated generic readings may differ by a single OCR character.
    final repeatedGenericCandidates = cleaned.where((line) {
      if (!RegExp(r'^[A-Za-z]{6,}$').hasMatch(line)) return false;
      if (brandCandidates.contains(line)) return false;
      return cleaned.any(
        (other) => other != line && similarity(line, other) > 0,
      );
    }).toList();
    repeatedGenericCandidates.sort((a, b) => b.length.compareTo(a.length));
    String? splitSaltGeneric;
    for (var i = 1; i < cleaned.length; i++) {
      if (cleaned[i].toLowerCase() == 'hydrochloride' &&
          cleaned[i - 1].length >= 6 &&
          cleaned[i - 1].split(' ').length == 1) {
        splitSaltGeneric = '${cleaned[i - 1]} ${cleaned[i]}';
        break;
      }
    }
    final generic = ingredients.isNotEmpty
        ? ingredients.join(', ')
        : (splitSaltGeneric ??
              labelledGeneric ??
              (repeatedGenericCandidates.isEmpty
                  ? null
                  : repeatedGenericCandidates.first));
    // An ingredient identified from a split salt name is not a brand.
    // Remove it before ranking the visible trade name.
    if (generic != null && generic.isNotEmpty) {
      final genericWords = generic.toLowerCase().split(RegExp(r'\\s+'));
      brandCandidates.removeWhere(
        (candidate) => genericWords.contains(candidate.toLowerCase()),
      );
    }
    final combinedStrength = composition.length > 1
        ? composition.join(' + ')
        : strength;

    return MedicineScanDraft(
      brandName: brandCandidates.isNotEmpty
          ? brandCandidates.first
          : (cleaned.length == 1 && cleaned.first.length >= 4
                ? cleaned.first
                : null),
      genericName: generic == null || generic.isEmpty ? null : generic,
      strength: combinedStrength,
      dosageForm: dosage,
      packQuantity: packQuantity,
      batchNumber: batch,
      expiresAt: expiryMatch?.group(1),
      barcode: barcode,
      sourceText: text,
    );
  }
}
