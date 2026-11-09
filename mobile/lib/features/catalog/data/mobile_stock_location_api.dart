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

  Future<List<MobileStockLocation>> list(
    MobileRegistration registration,
  ) async {
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
      throw StateError(
        'Stock location request failed (${response.statusCode})',
      );
    }
    final dynamic payload = jsonDecode(response.body);
    if (payload is! Map || payload['data'] is! List) {
      throw const FormatException('Invalid stock locations response');
    }
    return (payload['data'] as List<dynamic>)
        .map((dynamic item) => MobileStockLocation.fromJson(item))
        .toList(growable: false);
  }

  Future<List<MobileStockBranch>> branches(
    MobileRegistration registration,
  ) async {
    if (!registration.permissions.contains('inventory.manage')) {
      throw StateError('Inventory management permission is required');
    }
    _endpoint(registration);
    final Uri endpoint = environment.apiBaseUri!.resolve(
      'api/v1/mobile/stock-location-branches',
    );
    final http.Response response;
    try {
      response = await client
          .get(endpoint, headers: _headers(registration))
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Loading branches timed out');
    } on http.ClientException {
      throw StateError('Unable to connect to the branch server');
    }
    if (response.statusCode != 200) {
      throw StateError('Branch request failed (${response.statusCode})');
    }
    final dynamic payload = jsonDecode(response.body);
    if (payload is! Map || payload['data'] is! List) {
      throw const FormatException('Invalid branch response');
    }
    return (payload['data'] as List<dynamic>)
        .map((dynamic item) => MobileStockBranch.fromJson(item))
        .toList(growable: false);
  }

  Future<MobileStockBatchPage> batches({
    required MobileRegistration registration,
    required String stockLocationId,
    int limit = 100,
    String? afterId,
  }) async {
    if (!registration.permissions.contains('inventory.manage')) {
      throw StateError('Inventory management permission is required');
    }
    if (stockLocationId.trim().isEmpty || limit < 1 || limit > 100) {
      throw ArgumentError('A location and page size of 1 to 100 are required');
    }
    _endpoint(registration);
    final Uri endpoint = environment.apiBaseUri!
        .resolve('api/v1/mobile/stock-location-batches')
        .replace(
          queryParameters: <String, String>{
            'stock_location_id': stockLocationId.trim(),
            'limit': '$limit',
            'after_id': ?afterId,
          },
        );
    final http.Response response;
    try {
      response = await client
          .get(endpoint, headers: _headers(registration))
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Loading inventory batches timed out');
    } on http.ClientException {
      throw StateError('Unable to connect to inventory server');
    }
    if (response.statusCode != 200) {
      throw StateError(
        'Inventory batch request failed (${response.statusCode})',
      );
    }
    final dynamic payload = jsonDecode(response.body);
    if (payload is! Map ||
        payload['data'] is! List ||
        (payload['next_after_id'] != null &&
            payload['next_after_id'] is! String)) {
      throw const FormatException('Invalid inventory batch response');
    }
    return MobileStockBatchPage(
      batches: (payload['data'] as List<dynamic>)
          .map((dynamic item) => MobileStockBatch.fromJson(item))
          .toList(growable: false),
      nextAfterId: payload['next_after_id'] as String?,
    );
  }

  /// Fetch all available pages before offering a transfer batch selection.
  /// A bounded scan prevents unbounded memory/network use on large pharmacies.
  Future<List<MobileStockBatch>> allBatches({
    required MobileRegistration registration,
    required String stockLocationId,
    int maxPages = 50,
  }) async {
    if (maxPages < 1 || maxPages > 100) {
      throw ArgumentError.value(maxPages, 'maxPages');
    }
    final List<MobileStockBatch> result = <MobileStockBatch>[];
    final Set<String> cursors = <String>{};
    String? afterId;
    for (int page = 0; page < maxPages; page++) {
      final MobileStockBatchPage response = await batches(
        registration: registration,
        stockLocationId: stockLocationId,
        afterId: afterId,
      );
      result.addAll(response.batches);
      final String? next = response.nextAfterId;
      if (next == null) return result;
      if (next == afterId || !cursors.add(next)) {
        throw const FormatException('Inventory pagination cursor repeated');
      }
      afterId = next;
    }
    throw StateError(
      'Too many batches to load; narrow the inventory selection',
    );
  }

  Future<MobileStockTransferResult> transfer({
    required MobileRegistration registration,
    required String batchId,
    required String destinationId,
    required String quantity,
    required String requestId,
  }) async {
    if (!registration.permissions.contains('inventory.manage')) {
      throw StateError('Inventory management permission is required');
    }
    if (batchId.isEmpty ||
        destinationId.isEmpty ||
        !RegExp(r'^\d{1,12}(?:\.\d{1,4})?$').hasMatch(quantity) ||
        !RegExp(r'^[0-9a-fA-F-]{36}$').hasMatch(requestId)) {
      throw ArgumentError('Invalid transfer details');
    }
    _endpoint(registration);
    final Uri endpoint = environment.apiBaseUri!.resolve(
      'api/v1/mobile/stock-location-transfers',
    );
    final http.Response response;
    try {
      response = await client
          .post(
            endpoint,
            headers: _headers(registration),
            body: jsonEncode(<String, String>{
              'batch_id': batchId,
              'destination_id': destinationId,
              'quantity': quantity,
              'request_id': requestId,
            }),
          )
          .timeout(const Duration(seconds: 20));
    } on TimeoutException {
      throw StockTransferUncertainException(
        'Transfer timed out. Retry with the same request ID.',
      );
    } on http.ClientException {
      throw StateError(
        'Transfer connection failed. Retry with the same request ID.',
      );
    }
    if (response.statusCode >= 500 || response.statusCode == 408) {
      throw StockTransferUncertainException(
        'Transfer status is uncertain (${response.statusCode}). Retry with the same request ID.',
      );
    }
    if (response.statusCode != 200) {
      throw StateError('Transfer rejected (${response.statusCode})');
    }
    final dynamic payload = jsonDecode(response.body);
    if (payload is! Map || payload['data'] is! Map) {
      throw const FormatException('Invalid transfer response');
    }
    final Map<dynamic, dynamic> result = payload['data'] as Map;
    if (result['source_movement_id'] is! String ||
        result['destination_movement_id'] is! String ||
        result['replayed'] is! bool) {
      throw const FormatException('Invalid transfer movement details');
    }
    return MobileStockTransferResult(
      sourceMovementId: result['source_movement_id'] as String,
      destinationMovementId: result['destination_movement_id'] as String,
      replayed: result['replayed'] as bool,
    );
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
    if (response.statusCode == 422) {
      // Laravel validation errors are untrusted server text: show the
      // field-specific message without treating a rejected write as success.
      try {
        final dynamic body = jsonDecode(response.body);
        if (body is Map && body['errors'] is Map) {
          final Map<dynamic, dynamic> errors = body['errors'] as Map;
          for (final String field in <String>[
            'code',
            'name',
            'branch_id',
            'kind',
          ]) {
            final dynamic messages = errors[field];
            if (messages is List &&
                messages.isNotEmpty &&
                messages.first is String) {
              throw StateError(messages.first as String);
            }
          }
        }
      } on FormatException {
        // Keep the generic validation error for malformed responses.
      }
      throw StateError('Invalid location details (422)');
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

  final String id;
  final String branchId;
  final String code;
  final String name;
  final String kind;
}

class MobileStockBranch {
  const MobileStockBranch({
    required this.id,
    required this.code,
    required this.name,
  });

  factory MobileStockBranch.fromJson(dynamic value) {
    if (value is! Map ||
        value['id'] is! String ||
        (value['id'] as String).trim().isEmpty ||
        value['code'] is! String ||
        value['name'] is! String) {
      throw const FormatException('Invalid stock branch');
    }
    return MobileStockBranch(
      id: value['id'] as String,
      code: value['code'] as String,
      name: value['name'] as String,
    );
  }

  final String id;
  final String code;
  final String name;
}

class MobileStockBatchPage {
  const MobileStockBatchPage({
    required this.batches,
    required this.nextAfterId,
  });
  final List<MobileStockBatch> batches;
  final String? nextAfterId;
}

class MobileStockBatch {
  const MobileStockBatch({
    required this.id,
    required this.medicineId,
    required this.locationId,
    required this.availableQuantity,
    required this.status,
  });

  factory MobileStockBatch.fromJson(dynamic value) {
    if (value is! Map) {
      throw const FormatException('Invalid inventory batch entry');
    }
    String field(String name) {
      final dynamic v = value[name];
      if (v is! String || v.trim().isEmpty) {
        throw FormatException('Missing inventory batch field: $name');
      }
      return v;
    }

    return MobileStockBatch(
      id: field('id'),
      medicineId: field('medicine_id'),
      locationId: field('stock_location_id'),
      availableQuantity: field('available_quantity'),
      status: field('status'),
    );
  }

  final String id;
  final String medicineId;
  final String locationId;
  final String availableQuantity;
  final String status;
}

class MobileStockTransferResult {
  const MobileStockTransferResult({
    required this.sourceMovementId,
    required this.destinationMovementId,
    required this.replayed,
  });

  final String sourceMovementId;
  final String destinationMovementId;
  final bool replayed;
}

class StockTransferUncertainException implements Exception {
  const StockTransferUncertainException(this.message);

  final String message;

  @override
  String toString() => message;
}
