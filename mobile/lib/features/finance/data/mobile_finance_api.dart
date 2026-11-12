import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:http/http.dart' as http;

class FinanceEntry {
  const FinanceEntry({
    required this.title,
    required this.amount,
    required this.currency,
    required this.detail,
  });

  final String title;
  final String amount;
  final String currency;
  final String detail;

  factory FinanceEntry.fromJson(Object? raw, String type) {
    if (raw is! Map) {
      throw const FormatException('Invalid finance record');
    }
    final amount = raw['amount'];
    final currency = raw['currency'];
    if (amount is! String && amount is! num) {
      throw const FormatException('Invalid finance amount');
    }
    if (type != 'cash' && currency is! String) {
      throw const FormatException('Invalid finance currency');
    }
    final number = raw['expense_number'] ?? raw['payment_number'];
    return FinanceEntry(
      title: type == 'cash' ? '${raw['movement_type'] ?? 'Cash'}' : '${number ?? type}',
      amount: '$amount',
      currency: type == 'cash' ? '' : currency as String,
      detail: type == 'expense'
          ? '${raw['payee'] ?? ''} · ${raw['status'] ?? ''}'
          : type == 'payment'
              ? 'Supplier payment · ${raw['method'] ?? ''}'
              : '${raw['direction'] ?? ''} · ${raw['business_date'] ?? ''}',
    );
  }
}

class MobileFinanceSnapshot {
  const MobileFinanceSnapshot({
    required this.expenses,
    required this.supplierPayments,
    required this.cashMovements,
  });

  final List<FinanceEntry> expenses;
  final List<FinanceEntry> supplierPayments;
  final List<FinanceEntry> cashMovements;

  factory MobileFinanceSnapshot.fromJson(Object? raw) {
    if (raw is! Map ||
        raw['expenses'] is! List ||
        raw['supplier_payments'] is! List ||
        raw['cash_movements'] is! List) {
      throw const FormatException('Invalid finance response');
    }
    return MobileFinanceSnapshot(
      expenses: (raw['expenses'] as List)
          .map((entry) => FinanceEntry.fromJson(entry, 'expense'))
          .toList(growable: false),
      supplierPayments: (raw['supplier_payments'] as List)
          .map((entry) => FinanceEntry.fromJson(entry, 'payment'))
          .toList(growable: false),
      cashMovements: (raw['cash_movements'] as List)
          .map((entry) => FinanceEntry.fromJson(entry, 'cash'))
          .toList(growable: false),
    );
  }
}

class MobileFinanceApi {
  const MobileFinanceApi({required this.environment, required this.client});

  final AppEnvironment environment;
  final http.Client client;

  Future<MobileFinanceSnapshot> load(MobileRegistration registration) async {
    if (!registration.permissions.contains('accounting.manage')) {
      throw StateError('Accounting permission is required');
    }
    final base = environment.apiBaseUri;
    if (base == null || base.scheme != 'https' || registration.accessExpired) {
      throw StateError('An active secure connection is required');
    }
    final http.Response response;
    try {
      response = await client
          .get(
            base.resolve('api/v1/mobile/finance'),
            headers: <String, String>{
              'Authorization': 'Bearer ${registration.accessToken}',
              'Accept': 'application/json',
            },
          )
          .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw StateError('Finance request timed out');
    } on http.ClientException {
      throw StateError('Unable to reach finance server');
    }
    if (response.statusCode != 200) {
      throw StateError('Finance request failed (${response.statusCode})');
    }
    final payload = jsonDecode(response.body);
    if (payload is! Map) {
      throw const FormatException('Invalid finance response');
    }
    return MobileFinanceSnapshot.fromJson(payload['data']);
  }
}
