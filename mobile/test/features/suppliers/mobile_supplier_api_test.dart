import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/suppliers/data/mobile_supplier_api.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  final environment = AppEnvironment.fromValues(
    apiBaseUrl: 'https://example.test/',
  );

  MobileRegistration registration(List<String> permissions) =>
      MobileRegistration(
        tenantId: 'tenant',
        tenantName: 'Test',
        activationId: 'activation',
        accessToken: 'token',
        accessExpiresAt: DateTime.now().add(const Duration(hours: 1)),
        leaseToken: 'lease',
        leaseExpiresAt: DateTime.now().add(const Duration(hours: 1)),
        userId: 'user',
        userName: 'Tester',
        userEmail: 'test@example.test',
        permissions: permissions,
      );

  test('lists authorized suppliers with encoded search', () async {
    final client = MockClient((request) async {
      expect(request.url.path, '/api/v1/mobile/suppliers');
      expect(request.url.queryParameters['search'], 'Kabul & Co');
      expect(request.headers['Authorization'], 'Bearer token');
      return http.Response(jsonEncode({
        'data': [
          {
            'id': 'supplier-1',
            'name': 'Kabul Pharma',
            'code': 'KP',
            'is_active': true,
          },
        ],
      }), 200);
    });
    addTearDown(client.close);
    final result = await MobileSupplierApi(
      environment: environment,
      client: client,
    ).list(
      registration: registration(['inventory.manage']),
      search: 'Kabul & Co',
    );
    expect(result.single.name, 'Kabul Pharma');
    expect(result.single.isActive, isTrue);
  });

  test('blocks supplier requests without permission', () async {
    final client = MockClient((_) async {
      fail('Network must not be called without permission');
    });
    addTearDown(client.close);
    await expectLater(
      MobileSupplierApi(environment: environment, client: client).list(
        registration: registration([]),
      ),
      throwsStateError,
    );
  });
}
