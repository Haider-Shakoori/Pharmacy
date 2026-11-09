import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:http/http.dart' as http;

/// Online-only, tenant-authenticated storage metadata operations.
///
/// This service never changes stock quantities or queues offline mutations.
class MobileStockLocationApi {
  const MobileStockLocationApi({
    required this.environment,
    required this.client,
  });

  final AppEnvironment environment;
  final http.Client client;

  Uri _endpoint(MobileRegistration registration) {
    final Uri? base = environment.apiBaseUri;
    if (base == null || registration.accessExpired) {
      throw StateError('A valid online mobile session is required');
    }
    return base.resolve('api/v1/mobile/stock-locations');
  }

  Map<String, String> _headers(MobileRegistration registration) =>
      <String, String>{
        'Authorization': 'Bearer ${registration.accessToken}',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      };

  Future<List<MobileStockLocation>> list(MobileRegistration registration) async {
    if (!registration.permissions.contains('inventory.manage') &&
        !registration.permissions.contains('pos.sell')) {
      throw StateError('Inventory viewing permission is required');
    }
    final http.Response response;
    try {
      response = await client
          .get(_endpoint(registration), headers: _headers(registration))
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Loading stock locations timed out');
    } on http.ClientException {
      throw StateError('Unable to connect to the stock location server');
    }
    if (response.statusCode != 200) {
      throw StateError('Stock location request failed (${response.statusCode})');
    }
    final dynamic payload = jsonDecode(response.body);
    if (payload is! Map || payload['data'] is! List) {
      throw const FormatException('Invalid stock locations response');
    }
    return (payload['data'] as List<dynamic>)
        .map((dynamic item) => MobileStockLocation.fromJson(item))
        .toList(growable: false);
  }

  Future<MobileStockLocation> create({
    required MobileRegistration registration,
    required String branchId,
    required String code,
    required String name,
    required String kind,
  }) async {
    if (!registration.permissions.contains('inventory.manage')) {
      throw StateError('Inventory management permission is required');
    }
    final String cleanCode = code.trim();
    final String cleanName = name.trim();
    if (branchId.trim().isEmpty || cleanCode.isEmpty || cleanName.isEmpty) {
      throw ArgumentError('Branch, location code and name are required');
    }
    if (!const <String>{
      'store',
      'warehouse',
      'shelf',
      'quarantine',
      'other',
    }.contains(kind)) {
      throw ArgumentError.value(kind, 'kind', 'Invalid location type');
    }
    final http.Response response;
    try {
      response = await client
          .post(
            _endpoint(registration),
            headers: _headers(registration),
            body: jsonEncode(<String, String>{
              'branch_id': branchId.trim(),
              'code': cleanCode,
              'name': cleanName,
              'kind': kind,
            }),
          )
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Location creation timed out; refresh before retrying');
    } on http.ClientException {
      throw StateError('Connection failed; refresh before retrying creation');
    }
    if (response.statusCode != 201) {
      throw StateError('Location creation failed (${response.statusCode})');
    }
    final dynamic payload = jsonDecode(response.body);
    if (payload is! Map) {
      throw const FormatException('Invalid location creation response');
    }
    return MobileStockLocation.fromJson(payload['data']);
  }
}

class MobileStockLocation {
  const MobileStockLocation({
    required this.id,
    required this.branchId,
    required this.code,
    required this.name,
    required this.kind,
  });

  final String id;
  final String branchId;
  final String code;
  final String name;
  final String kind;

  factory MobileStockLocation.fromJson(dynamic value) {
    if (value is! Map) {
      throw const FormatException('Invalid stock location entry');
    }
    String requiredField(String key) {
      final dynamic item = value[key];
      if (item is! String || item.trim().isEmpty) {
        throw FormatException('Missing stock location field: $key');
      }
      return item;
    }

    return MobileStockLocation(
      id: requiredField('id'),
      branchId: requiredField('branch_id'),
      code: requiredField('code'),
      name: requiredField('name'),
      kind: requiredField('kind'),
    );
  }
}
