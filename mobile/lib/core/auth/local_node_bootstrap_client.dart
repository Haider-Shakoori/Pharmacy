import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:http/http.dart' as http;

class LocalNodeBootstrapException implements Exception {
  const LocalNodeBootstrapException(this.message);

  final String message;

  @override
  String toString() => message;
}

class LocalNodeBootstrapClient {
  LocalNodeBootstrapClient({
    http.Client? client,
    Uri? endpoint,
    this.timeout = const Duration(seconds: 90),
  }) : _client = client ?? http.Client(),
       endpoint = endpoint ?? Uri.parse('http://127.0.0.1:8787');

  final http.Client _client;
  final Uri endpoint;
  final Duration timeout;

  Future<Uri> bootstrap({
    required MobileRegistration registration,
    required String password,
  }) async {
    final Uri uri = endpoint.resolve('/api/v1/local-node/bootstrap');

    try {
      final http.Response response = await _client
          .post(
            uri,
            headers: const <String, String>{
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode(<String, Object?>{
              'registration': registration.toJson(),
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
        if (data is Map && data['local_url'] != null) {
          final Uri? local = Uri.tryParse(data['local_url'].toString());
          if (local != null && local.hasScheme && local.hasAuthority) {
            return local;
          }
        }

        throw const LocalNodeBootstrapException(
          'The local Pharmacy server returned an invalid activation response.',
        );
      }

      final Object? errors = payload['errors'];
      if (errors is Map) {
        for (final Object? value in errors.values) {
          if (value is List && value.isNotEmpty) {
            throw LocalNodeBootstrapException(value.first.toString());
          }
        }
      }

      throw LocalNodeBootstrapException(
        payload['message']?.toString() ??
            'Local Pharmacy server activation failed.',
      );
    } on LocalNodeBootstrapException {
      rethrow;
    } on TimeoutException {
      throw const LocalNodeBootstrapException(
        'The bundled local Pharmacy server did not become ready in time.',
      );
    } on Object {
      throw const LocalNodeBootstrapException(
        'The bundled local Pharmacy server could not be reached.',
      );
    }
  }
}