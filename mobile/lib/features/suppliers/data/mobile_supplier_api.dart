import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:http/http.dart' as http;

class MobileSupplier {
  const MobileSupplier({
    required this.id,
    required this.name,
    this.code,
    this.contactPerson,
    this.phone,
    this.city,
    this.province,
    required this.isActive,
  });

  factory MobileSupplier.fromJson(Object? value) {
    if (value is! Map ||
        value['id'] is! String ||
        value['name'] is! String ||
        value['is_active'] is! bool) {
      throw const FormatException('Invalid supplier data');
    }
    String? optional(String key) =>
        value[key] is String ? value[key] as String : null;
    return MobileSupplier(
      id: value['id'] as String,
      name: value['name'] as String,
      code: optional('code'),
      contactPerson: optional('contact_person'),
      phone: optional('phone'),
      city: optional('city'),
      province: optional('province'),
      isActive: value['is_active'] as bool,
    );
  }

  final String id;
  final String name;
  final String? code;
  final String? contactPerson;
  final String? phone;
  final String? city;
  final String? province;
  final bool isActive;
}

class MobileSupplierApi {
  const MobileSupplierApi({required this.environment, required this.client});

  final AppEnvironment environment;
  final http.Client client;

  Future<List<MobileSupplier>> list({
    required MobileRegistration registration,
    String search = '',
  }) async {
    if (!registration.permissions.contains('inventory.manage')) {
      throw StateError('Inventory management permission is required');
    }
    final base = environment.apiBaseUri;
    if (base == null || base.scheme != 'https' || registration.accessExpired) {
      throw StateError('An active secure mobile connection is required');
    }
    final endpoint = base
        .resolve('api/v1/mobile/suppliers')
        .replace(
          queryParameters: <String, String>{
            if (search.trim().isNotEmpty) 'search': search.trim(),
          },
        );
    final http.Response response;
    try {
      response = await client
          .get(
            endpoint,
            headers: <String, String>{
              'Authorization': 'Bearer ${registration.accessToken}',
              'Accept': 'application/json',
            },
          )
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Supplier request timed out');
    } on http.ClientException {
      throw StateError('Unable to reach supplier server');
    }
    if (response.statusCode != 200) {
      throw StateError('Supplier request failed (${response.statusCode})');
    }
    final payload = jsonDecode(response.body);
    if (payload is! Map || payload['data'] is! List) {
      throw const FormatException('Invalid supplier response');
    }
    return (payload['data'] as List)
        .map((item) => MobileSupplier.fromJson(item))
        .toList(growable: false);
  }
}
