import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/device/device_identity.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:http/http.dart' as http;

class MobileRegistrationException implements Exception {
  const MobileRegistrationException(this.message, {this.retryable = false});

  final String message;
  final bool retryable;

  @override
  String toString() => message;
}

class MobileRegistrationClient {
  MobileRegistrationClient({
    http.Client? client,
    this.timeout = const Duration(seconds: 10),
  }) : _client = client ?? http.Client();

  final http.Client _client;
  final Duration timeout;

  Future<MobileRegistration> register({
    required ServerEndpoint endpoint,
    required DeviceIdentity device,
    required String licenseKey,
    required String email,
    required String password,
  }) async {
    final Uri uri = endpoint.uri.resolve('/api/v1/mobile/register');

    try {
      final http.Response response = await _client
          .post(
            uri,
            headers: const <String, String>{
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode(<String, Object?>{
              'license_key': licenseKey.trim(),
              'device_id': device.installationId,
              'platform': device.platform,
              'device_name': device.deviceName,
              'device_model': device.model,
              'os_version': device.osVersion,
              'app_version': device.appVersion,
              'build_number': device.buildNumber,
              'email': email.trim(),
              'password': password,
            }),
          )
          .timeout(timeout);

      final Object? decoded = jsonDecode(response.body);
      final Map<String, dynamic> payload = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{};

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final Object? data = payload['data'];
        if (data is! Map<String, dynamic>) {
          throw const MobileRegistrationException(
            'The server returned an invalid registration response.',
          );
        }

        return MobileRegistration.fromApi(data);
      }

      final String message =
          _errorMessage(payload) ??
          'Registration failed with status ${response.statusCode}.';

      throw MobileRegistrationException(
        message,
        retryable:
            response.statusCode == 404 ||
            response.statusCode == 408 ||
            response.statusCode == 429 ||
            response.statusCode >= 500,
      );
    } on MobileRegistrationException {
      rethrow;
    } on TimeoutException {
      throw const MobileRegistrationException(
        'The pharmacy server did not respond in time.',
        retryable: true,
      );
    } on FormatException {
      throw const MobileRegistrationException(
        'The pharmacy server returned an invalid response.',
        retryable: true,
      );
    } on Object {
      throw const MobileRegistrationException(
        'The pharmacy server could not be reached.',
        retryable: true,
      );
    }
  }

  String? _errorMessage(Map<String, dynamic> payload) {
    final Object? errors = payload['errors'];
    if (errors is Map) {
      for (final Object? value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
      }
    }

    return payload['message']?.toString();
  }
}
