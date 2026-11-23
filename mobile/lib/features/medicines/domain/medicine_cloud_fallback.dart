import 'offline_medicine_text_parser.dart';

/// A separately configured, authenticated backend is responsible for any
/// network request. Never embed an AI provider key in the mobile application.
abstract interface class MedicineCloudVisionGateway {
  Future<MedicineScanDraft> suggestFromImage({
    required String localImagePath,
    required MedicineScanDraft offlineDraft,
  });
}

/// Explicit consent gate for optional remote medicine-label analysis.
/// Calling the gateway is forbidden unless the pharmacist approves this scan.
class MedicineCloudFallback {
  const MedicineCloudFallback(this.gateway);

  final MedicineCloudVisionGateway gateway;

  Future<MedicineScanDraft> suggest({
    required String localImagePath,
    required MedicineScanDraft offlineDraft,
    required bool userConsented,
  }) async {
    if (!userConsented) {
      throw StateError('Explicit consent is required before cloud analysis');
    }
    final uri = Uri.tryParse(localImagePath.trim());
    if (uri == null ||
        (uri.hasScheme && uri.scheme != 'file') ||
        (uri.scheme == 'file' && uri.host.isNotEmpty) ||
        (uri.scheme.isEmpty && !localImagePath.trim().startsWith('/')) ||
        (uri.scheme == 'file' && !uri.path.startsWith('/')) ||
        localImagePath.trim().isEmpty) {
      throw ArgumentError.value(
        localImagePath,
        'localImagePath',
        'Only local images can be submitted for consented analysis',
      );
    }
    return gateway.suggestFromImage(
      localImagePath: localImagePath.trim(),
      offlineDraft: offlineDraft,
    );
  }
}
