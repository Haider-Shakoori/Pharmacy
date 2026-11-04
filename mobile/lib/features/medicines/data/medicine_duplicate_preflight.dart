import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:http/http.dart' as http;

/// Online, read-only server preflight. Never creates or queues a medicine.
class MedicineDuplicatePreflight {
  const MedicineDuplicatePreflight({
    required this.environment,
    required this.client,
  });

  final AppEnvironment environment;
  final http.Client client;

  Future<List<String>> check({
    required MobileRegistration registration,
    required MedicineRegistrationHandoff handoff,
  }) async {
    final base = environment.apiBaseUri;
    if (base == null || registration.accessExpired) {
      throw StateError('A valid online mobile session is required');
    }
    if (!registration.permissions.contains('medicines.manage')) {
      throw StateError('Medicine management permission is required');
    }
    final uri = base.resolve('api/v1/mobile/medicines/duplicate-check');
    final http.Response response;
    try {
      response = await client
          .post(
            uri,
            headers: <String, String>{
              'Authorization': 'Bearer ${registration.accessToken}',
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode(<String, String>{
              'brand_name': handoff.brandName,
              if (handoff.barcode != null) 'barcode': handoff.barcode!,
            }),
          )
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError(
        'Duplicate verification timed out. Registration was not submitted; retry when connected.',
      );
    } on http.ClientException {
      throw StateError(
        'Duplicate verification connection failed. Registration was not submitted; retry when connected.',
      );
    }
    if (response.statusCode != 200) {
      throw StateError(
        'Server duplicate validation failed (${response.statusCode})',
      );
    }
    final decoded = jsonDecode(response.body);
    if (decoded is! Map || decoded['data'] is! Map) {
      throw const FormatException('Invalid duplicate-check response');
    }
    final data = decoded['data'] as Map;
    if (data['registration_allowed'] != false ||
        data['possible_duplicates'] is! List) {
      throw const FormatException('Unexpected server duplicate-check contract');
    }
    return (data['possible_duplicates'] as List)
        .map((dynamic item) {
          if (item is! Map || item['brand_name'] is! String) {
            throw const FormatException('Invalid duplicate match');
          }
          return item['brand_name'] as String;
        })
        .toList(growable: false);
  }
}
