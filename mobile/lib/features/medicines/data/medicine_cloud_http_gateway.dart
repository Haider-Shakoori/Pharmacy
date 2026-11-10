import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../domain/medicine_cloud_fallback.dart';
import '../domain/offline_medicine_text_parser.dart';

/// Optional, explicitly invoked gateway. This class does not store credentials
/// or perform any request unless MedicineCloudFallback has approved consent.
class MedicineCloudHttpGateway implements MedicineCloudVisionGateway {
  MedicineCloudHttpGateway({
    required this.endpoint,
    required this.accessToken,
    http.Client? client,
  }) : _client = client ?? http.Client();

  final Uri endpoint;
  final String accessToken;
  final http.Client _client;

  @override
  Future<MedicineScanDraft> suggestFromImage({
    required String localImagePath,
    required MedicineScanDraft offlineDraft,
  }) async {
    if (endpoint.scheme != 'https' ||
        endpoint.host.isEmpty ||
        endpoint.userInfo.isNotEmpty ||
        endpoint.hasFragment ||
        endpoint.hasQuery ||
        endpoint.port != 443) {
      throw StateError('Cloud OCR requires a secure HTTPS endpoint');
    }
    if (accessToken.trim().isEmpty) {
      throw StateError('Authenticated session required for cloud OCR');
    }
    final imageUri = Uri.tryParse(localImagePath);
    if (imageUri == null ||
        (imageUri.hasScheme && imageUri.scheme != 'file') ||
        (imageUri.scheme == 'file' && imageUri.host.isNotEmpty) ||
        (imageUri.scheme.isEmpty && !localImagePath.startsWith('/'))) {
      throw ArgumentError.value(
        localImagePath,
        'localImagePath',
        'Local image required',
      );
    }
    final file = File.fromUri(
      imageUri.scheme == 'file' ? imageUri : Uri.file(localImagePath),
    );
    if (!await file.exists()) throw StateError('Image not found');
    final length = await file.length();
    if (length == 0 || length > 8 * 1024 * 1024) {
      throw StateError('Image must be between 1 byte and 8 MB');
    }
    final bytes = await file.readAsBytes();
    final response = await _client
        .post(
          endpoint,
          headers: {
        'Authorization': 'Bearer $accessToken',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
          },
          body: jsonEncode({
        'consent': true,
        'image_base64': base64Encode(bytes),
        'offline_text': offlineDraft.sourceText,
          }),
        )
        .timeout(const Duration(seconds: 25));
    if (response.statusCode != 200) {
      throw StateError('Cloud OCR unavailable (${response.statusCode})');
    }
    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic> ||
        decoded['suggestions'] is! Map<String, dynamic>) {
      throw const FormatException('Invalid cloud OCR response');
    }
    final suggestions = decoded['suggestions'] as Map<String, dynamic>;
    String? field(String name) {
      final value = suggestions[name];
      if (value is! String) return null;
      final trimmed = value.trim();
      return trimmed.isEmpty ? null : trimmed;
    }

    // Suggestions are not persisted. The pharmacist must review the draft.
    return MedicineScanDraft(
      brandName: field('brand_name') ?? offlineDraft.brandName,
      genericName: field('generic_name') ?? offlineDraft.genericName,
      strength: field('strength') ?? offlineDraft.strength,
      dosageForm: field('dosage_form') ?? offlineDraft.dosageForm,
      batchNumber: field('batch_number') ?? offlineDraft.batchNumber,
      expiresAt: field('expires_at') ?? offlineDraft.expiresAt,
      barcode: offlineDraft.barcode,
      sourceText: offlineDraft.sourceText,
    );
  }
}
