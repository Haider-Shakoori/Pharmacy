import 'package:flutter/services.dart';

import 'medicine_scan_coordinator.dart';

/// Flutter bridge for bundled, on-device OCR implementations.
///
/// Native Android/iOS/Windows handlers are not installed by this class.
/// A missing handler is treated as unavailable; no network or model download
/// fallback is permitted.
class PlatformMedicineOcr implements NativeMedicineOcr {
  const PlatformMedicineOcr({
    MethodChannel channel = const MethodChannel(
      'darmaltoon/offline_medicine_ocr',
    ),
  }) : _channel = channel; // ignore: prefer_initializing_formals

  final MethodChannel _channel;

  @override
  Future<bool> get isAvailable async {
    try {
      return await _channel.invokeMethod<bool>('isAvailable') ?? false;
    } on MissingPluginException {
      return false;
    } on PlatformException {
      return false;
    }
  }

  @override
  Future<List<String>> recognizeLocalImage(String imagePath) async {
    final cleaned = imagePath.trim();
    if (cleaned.isEmpty) {
      throw ArgumentError.value(imagePath, 'imagePath', 'Image path required');
    }
    final uri = Uri.tryParse(cleaned);
    if (uri == null ||
        (uri.hasScheme && uri.scheme != 'file') ||
        (uri.scheme == 'file' && uri.host.isNotEmpty) ||
        (uri.scheme.isEmpty && !cleaned.startsWith('/')) ||
        (uri.scheme == 'file' && !uri.path.startsWith('/'))) {
      throw ArgumentError.value(
        imagePath,
        'imagePath',
        'Only local absolute image paths are allowed',
      );
    }
    try {
      final lines = await _channel.invokeListMethod<String>(
        'recognizeLocalImage',
        <String, String>{'path': imagePath},
      );
      if (lines == null) {
        throw StateError('Offline OCR returned no text');
      }
      return lines;
    } on MissingPluginException {
      throw StateError('Offline OCR is not installed for this platform');
    } on PlatformException catch (error) {
      throw StateError('Local OCR failed: ${error.code}');
    }
  }
}
