import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/catalog/data/mobile_stock_location_api.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  final AppEnvironment environment = AppEnvironment.fromValues(
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

  const Map<String, String> location = <String, String>{
    'id': 'location-id',
    'branch_id': 'branch-id',
    'code': 'MAIN',
    'name': 'Main Store',
    'kind': 'store',
  };

  test('cashier can list active server locations', () async {
    final http.Client client = MockClient((http.Request request) async {
      expect(request.method, 'GET');
      expect(request.url.path, '/api/v1/mobile/stock-locations');
      expect(request.headers['Authorization'], 'Bearer token');
      return http.Response(jsonEncode(<String, Object>{
        'data': <Object>[location],
      }), 200);
    });
    addTearDown(client.close);
    final List<MobileStockLocation> locations = await MobileStockLocationApi(
      environment: environment,
      client: client,
    ).list(registration(<String>['pos.sell']));
    expect(locations.single.code, 'MAIN');
    expect(locations.single.branchId, 'branch-id');
  });

  test('manager creates a location with trimmed fields', () async {
    final http.Client client = MockClient((http.Request request) async {
      expect(request.method, 'POST');
      expect(request.url.path, '/api/v1/mobile/stock-locations');
      expect(request.headers['Authorization'], 'Bearer token');
      expect(jsonDecode(request.body), <String, String>{
        'branch_id': 'branch-id',
        'code': 'MAIN',
        'name': 'Main Store',
        'kind': 'store',
      });
      return http.Response(jsonEncode(<String, Object>{
        'data': location,
      }), 201);
    });
    addTearDown(client.close);
    final MobileStockLocation created = await MobileStockLocationApi(
      environment: environment,
      client: client,
    ).create(
      registration: registration(<String>['inventory.manage']),
      branchId: ' branch-id ',
      code: ' MAIN ',
      name: ' Main Store ',
      kind: 'store',
    );
    expect(created.id, 'location-id');
  });

  test('cashier cannot create locations or send a request', () async {
    var requests = 0;
    final http.Client client = MockClient((_) async {
      requests++;
      return http.Response('{}', 201);
    });
    addTearDown(client.close);
    await expectLater(
      MobileStockLocationApi(environment: environment, client: client).create(
        registration: registration(<String>['pos.sell']),
        branchId: 'branch-id',
        code: 'NEW',
        name: 'New',
        kind: 'warehouse',
      ),
      throwsStateError,
    );
    expect(requests, 0);
  });

  test('invalid response and server errors fail closed', () async {
    final http.Client client = MockClient(
      (_) async => http.Response('{"data":{}}', 200),
    );
    addTearDown(client.close);
    await expectLater(
      MobileStockLocationApi(environment: environment, client: client)
          .list(registration(<String>['inventory.manage'])),
      throwsFormatException,
    );
  });

  test('connection failure never reports a successful creation', () async {
    final http.Client client = MockClient(
      (_) async => throw http.ClientException('offline'),
    );
    addTearDown(client.close);
    await expectLater(
      MobileStockLocationApi(environment: environment, client: client).create(
        registration: registration(<String>['inventory.manage']),
        branchId: 'branch-id',
        code: 'NEW',
        name: 'New',
        kind: 'warehouse',
      ),
      throwsStateError,
    );
  });
}
