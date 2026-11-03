import 'offline_medicine_text_parser.dart';

/// Contract for platform-provided OCR that runs entirely on the device.
///
/// Implementations must work without connectivity or remote model downloads.
/// No cloud fallback is allowed; unsupported platforms must report unavailable.
abstract interface class NativeMedicineOcr {
  Future<bool> get isAvailable;

  /// Reads text from an image file stored locally on the device.
  Future<List<String>> recognizeLocalImage(String imagePath);
}

/// Optional AI enrichment is deliberately disabled in the initial release.
/// An implementation must never receive images or text without user opt-in.
abstract interface class MedicineAiEnrichment {
  Future<MedicineScanDraft> suggest(MedicineScanDraft draft);
}

class MedicineScanCoordinator {
  const MedicineScanCoordinator({
    required this.ocr,
    this.parser = const OfflineMedicineTextParser(),
  });

  final NativeMedicineOcr ocr;
  final OfflineMedicineTextParser parser;

  Future<MedicineScanDraft> scanLocalImage(
    String imagePath, {
    String? barcode,
  }) async {
    if (imagePath.trim().isEmpty) {
      throw ArgumentError.value(imagePath, 'imagePath', 'Image path required');
    }
    if (!await ocr.isAvailable) {
      throw StateError('Native offline OCR is unavailable on this device');
    }
    final lines = await ocr.recognizeLocalImage(imagePath);
    return parser.parse(lines, barcode: barcode);
  }
}
