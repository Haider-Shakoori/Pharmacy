import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_duplicate_preflight.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_form_state.dart';
import 'package:http/http.dart' as http;

/// Online-only catalog creation. Never writes to the offline sync queue.
class MedicineRegistrationApi {
  const MedicineRegistrationApi({
    required this.environment,
    required this.client,
  });

  final AppEnvironment environment;
  final http.Client client;

  Future<String> create({
    required MobileRegistration registration,
    required MedicineRegistrationFormState form,
  }) async {
    final base = environment.apiBaseUri;
    if (base == null || registration.accessExpired) {
      throw StateError('An active online session is required');
    }
    if (!registration.permissions.contains('medicines.manage')) {
      throw StateError('Medicine management permission is required');
    }
    final payload = form.validatedSubmission();
    final duplicates = await MedicineDuplicatePreflight(
      environment: environment,
      client: client,
    ).check(registration: registration, handoff: form.handoff);
    if (duplicates.isNotEmpty) {
      throw StateError('Potential duplicate medicines must be resolved');
    }
    final response = await client.post(
      base.resolve('api/v1/mobile/medicines'),
      headers: <String, String>{
        'Authorization': 'Bearer ${registration.accessToken}',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(payload),
    );
    if (response.statusCode != 201) {
      if (response.statusCode == 401 || response.statusCode == 403) {
        throw StateError('Session expired or medicine permission denied');
      }
      if (response.statusCode == 422) {
        final decoded = jsonDecode(response.body);
        if (decoded is Map && decoded['errors'] is Map) {
          final errors = decoded['errors'] as Map;
          final details = errors.values
              .whereType<List>()
              .expand((items) => items)
              .whereType<String>()
              .take(3)
              .join('; ');
          if (details.isNotEmpty) throw StateError(details);
        }
        throw StateError('Medicine details failed server validation');
      }
      throw StateError('Medicine registration failed (${response.statusCode})');
    }
    final result = jsonDecode(response.body);
    if (result is! Map ||
        result['data'] is! Map ||
        (result['data'] as Map)['id'] is! String) {
      throw const FormatException('Invalid medicine registration response');
    }
    return (result['data'] as Map)['id'] as String;
  }
}
