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


class SupplierActivity {
  const SupplierActivity({
    required this.title,
    required this.detail,
    required this.total,
  });

  factory SupplierActivity.fromJson(Object? raw, String type) {
    if (raw is! Map) {
      throw const FormatException('Invalid supplier activity');
    }
    final title = raw[type == 'order'
        ? 'number'
        : type == 'invoice'
        ? 'invoice_number'
        : 'payment_number'];
    if (title is! String) {
      throw const FormatException('Missing supplier document number');
    }
    final amount = raw[type == 'invoice' ? 'balance_due' : type == 'order' ? 'grand_total' : 'amount'];
    if (amount is! String && amount is! num) {
      throw const FormatException('Invalid supplier activity amount');
    }
    return SupplierActivity(
      title: title,
      detail: '${raw['status'] ?? raw['method'] ?? type}',
      total: '$amount ${raw['currency'] ?? ''}',
    );
  }

  final String title;
  final String detail;
  final String total;
}

class MobileSupplierDetail {
  const MobileSupplierDetail({
    required this.supplier,
    required this.orders,
    required this.invoices,
    required this.payments,
    required this.outstanding,
  });

  factory MobileSupplierDetail.fromJson(Object? raw) {
    if (raw is! Map || raw['purchase_orders'] is! List) {
      throw const FormatException('Invalid supplier detail response');
    }
    List<SupplierActivity> activities(String key, String type) {
      final data = raw[key];
      if (data == null) return <SupplierActivity>[];
      if (data is! List) {
        throw const FormatException('Invalid supplier activity list');
      }
      return data
          .map((entry) => SupplierActivity.fromJson(entry, type))
          .toList(growable: false);
    }

    final totals = raw['outstanding_by_currency'];
    if (totals != null && totals is! List) {
      throw const FormatException('Invalid supplier balances');
    }
    return MobileSupplierDetail(
      supplier: MobileSupplier.fromJson(raw['supplier']),
      orders: activities('purchase_orders', 'order'),
      invoices: activities('invoices', 'invoice'),
      payments: activities('payments', 'payment'),
      outstanding: (totals as List? ?? <Object>[])
          .map((entry) {
            if (entry is! Map ||
                entry['currency'] is! String ||
                entry['amount'] is! String) {
              throw const FormatException('Invalid supplier balance entry');
            }
            return "${entry['amount']} ${entry['currency']}";
          })
          .toList(growable: false),
    );
  }

  final MobileSupplier supplier;
  final List<SupplierActivity> orders;
  final List<SupplierActivity> invoices;
  final List<SupplierActivity> payments;
  final List<String> outstanding;
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

  Future<MobileSupplierDetail> detail({
    required MobileRegistration registration,
    required String supplierId,
  }) async {
    if (!registration.permissions.contains('inventory.manage')) {
      throw StateError('Inventory management permission is required');
    }
    if (!RegExp(r'^[a-zA-Z0-9_-]+
      throw ArgumentError('Invalid supplier ID');
    }
    final base = environment.apiBaseUri;
    if (base == null || base.scheme != 'https' || registration.accessExpired) {
      throw StateError('An active secure mobile connection is required');
    }
    final http.Response response;
    try {
      response = await client
          .get(
            base.resolve('api/v1/mobile/suppliers/$supplierId'),
            headers: <String, String>{
              'Authorization': 'Bearer ${registration.accessToken}',
              'Accept': 'application/json',
            },
          )
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Supplier detail request timed out');
    } on http.ClientException {
      throw StateError('Unable to reach supplier server');
    }
    if (response.statusCode != 200) {
      throw StateError('Supplier details failed (${response.statusCode})');
    }
    final payload = jsonDecode(response.body);
    if (payload is! Map) {
      throw const FormatException('Invalid supplier detail response');
    }
    return MobileSupplierDetail.fromJson(payload['data']);
  }
}
).hasMatch(supplierId)) {
      throw ArgumentError('Invalid supplier ID');
    }
    final base = environment.apiBaseUri;
    if (base == null || base.scheme != 'https' || registration.accessExpired) {
      throw StateError('An active secure mobile connection is required');
    }
    final http.Response response;
    try {
      response = await client
          .get(
            base.resolve('api/v1/mobile/suppliers/$supplierId'),
            headers: <String, String>{
              'Authorization': 'Bearer ${registration.accessToken}',
              'Accept': 'application/json',
            },
          )
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Supplier detail request timed out');
    } on http.ClientException {
      throw StateError('Unable to reach supplier server');
    }
    if (response.statusCode != 200) {
      throw StateError('Supplier details failed (${response.statusCode})');
    }
    final payload = jsonDecode(response.body);
    if (payload is! Map) {
      throw const FormatException('Invalid supplier detail response');
    }
    return MobileSupplierDetail.fromJson(payload['data']);
  }
}
