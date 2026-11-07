/// A local-only image acquisition contract for medicine label scanning.
///
/// Implementations must return an existing local file path. No images are
/// uploaded, and no capture SDK or platform permission is requested here.
abstract interface class LocalMedicineImageSource {
  Future<String?> captureImage();
  Future<String?> chooseLocalImage();
}

enum MedicineImageOrigin { camera, gallery }

class MedicineImageSelection {
  const MedicineImageSelection({required this.localPath, required this.origin});

  final String localPath;
  final MedicineImageOrigin origin;
}

class MedicineImageAcquisition {
  const MedicineImageAcquisition(this.source);

  final LocalMedicineImageSource source;

  Future<MedicineImageSelection?> acquire(MedicineImageOrigin origin) async {
    final path = switch (origin) {
      MedicineImageOrigin.camera => await source.captureImage(),
      MedicineImageOrigin.gallery => await source.chooseLocalImage(),
    };
    if (path == null) return null;
    final cleaned = path.trim();
    if (cleaned.isEmpty) {
      throw StateError('Image source returned an empty local path');
    }
    final uri = Uri.tryParse(cleaned);
    if (uri == null ||
        (uri.hasScheme && uri.scheme != 'file') ||
        (uri.scheme == 'file' && uri.host.isNotEmpty) ||
        (uri.scheme.isEmpty && !cleaned.startsWith('/')) ||
        (uri.scheme == 'file' && !uri.path.startsWith('/'))) {
      throw StateError('Only local image paths are permitted');
    }
    return MedicineImageSelection(localPath: cleaned, origin: origin);
  }
}
