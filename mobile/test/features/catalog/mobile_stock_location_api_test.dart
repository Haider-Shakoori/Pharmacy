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

  test('allBatches follows cursor and combines pages', () async {
    final List<String?> cursors = <String?>[];
    final http.Client client = MockClient((http.Request request) async {
      cursors.add(request.url.queryParameters['after_id']);
      final bool second = request.url.queryParameters['after_id'] == 'cursor-1';
      return http.Response(jsonEncode(<String, Object?>{
        'data': <Map<String, String>>[
          <String, String>{
            'id': second ? 'batch-2' : 'batch-1',
            'medicine_id': 'medicine-1',
            'stock_location_id': 'location-1',
            'available_quantity': '5.0000',
            'status': 'active',
          },
        ],
        'next_after_id': second ? null : 'cursor-1',
      }), 200);
    });
    addTearDown(client.close);
    final List<MobileStockBatch> batches = await MobileStockLocationApi(
      environment: environment,
      client: client,
    ).allBatches(
      registration: registration(<String>['inventory.manage']),
      stockLocationId: 'location-1',
    );
    expect(batches.map((MobileStockBatch batch) => batch.id), <String>[
      'batch-1', 'batch-2',
    ]);
    expect(cursors, <String?>[null, 'cursor-1']);
  });

  test('allBatches rejects repeating cursor', () async {
    final http.Client client = MockClient((http.Request request) async =>
      http.Response(jsonEncode(<String, Object?>{
        'data': <Object>[],
        'next_after_id': 'repeat',
      }), 200),
    );
    addTearDown(client.close);
    await expectLater(
      MobileStockLocationApi(environment: environment, client: client)
          .allBatches(
        registration: registration(<String>['inventory.manage']),
        stockLocationId: 'location-1',
      ),
      throwsA(isA<FormatException>()),
    );
  });

  test('transfer posts idempotency key and returns paired movements', () async {
    final http.Client client = MockClient((http.Request request) async {
      expect(request.method, 'POST');
      expect(request.url.path, endsWith('/stock-location-transfers'));
      final Map<String, dynamic> payload =
          jsonDecode(request.body) as Map<String, dynamic>;
      expect(payload['request_id'], '17eb9752-3900-41a1-a182-fdb75c96f7be');
      expect(payload['quantity'], '2.0000');
      return http.Response(
        jsonEncode(<String, Object>{
          'data': <String, Object>{
            'source_movement_id': 'movement-out',
            'destination_movement_id': 'movement-in',
            'replayed': false,
          },
        }),
        200,
      );
    });
    addTearDown(client.close);
    final MobileStockTransferResult result =
        await MobileStockLocationApi(
          environment: environment,
          client: client,
        ).transfer(
          registration: registration(<String>['inventory.manage']),
          batchId: 'batch-1',
          destinationId: 'location-2',
          quantity: '2.0000',
          requestId: '17eb9752-3900-41a1-a182-fdb75c96f7be',
        );
    expect(result.sourceMovementId, 'movement-out');
    expect(result.destinationMovementId, 'movement-in');
    expect(result.replayed, isFalse);
  });

  test('cashier cannot submit stock transfer', () async {
    final http.Client client = MockClient(
      (_) async => throw StateError('Unexpected network request'),
    );
    addTearDown(client.close);
    await expectLater(
      MobileStockLocationApi(environment: environment, client: client).transfer(
        registration: registration(<String>['pos.sell']),
        batchId: 'batch-1',
        destinationId: 'location-2',
        quantity: '1',
        requestId: '17eb9752-3900-41a1-a182-fdb75c96f7be',
      ),
      throwsA(isA<StateError>()),
    );
  });

  test('manager reads paginated inventory without changing stock', () async {
    final http.Client client = MockClient((http.Request request) async {
      expect(request.method, 'GET');
      expect(request.url.path, endsWith('/stock-location-batches'));
      expect(request.url.queryParameters['stock_location_id'], 'location-1');
      expect(request.url.queryParameters['limit'], '1');
      return http.Response(
        jsonEncode(<String, Object?>{
          'data': <Object>[
            <String, String>{
              'id': 'batch-1',
              'medicine_id': 'medicine-1',
              'stock_location_id': 'location-1',
              'available_quantity': '3.0000',
              'status': 'active',
            },
          ],
          'next_after_id': 'batch-1',
        }),
        200,
      );
    });
    addTearDown(client.close);
    final MobileStockBatchPage page =
        await MobileStockLocationApi(
          environment: environment,
          client: client,
        ).batches(
          registration: registration(<String>['inventory.manage']),
          stockLocationId: 'location-1',
          limit: 1,
        );
    expect(page.batches.single.availableQuantity, '3.0000');
    expect(page.nextAfterId, 'batch-1');
  });

  test('cashier cannot request transfer-planning batches', () async {
    final http.Client client = MockClient(
      (_) async => throw StateError('Must not send request'),
    );
    addTearDown(client.close);
    await expectLater(
      MobileStockLocationApi(environment: environment, client: client).batches(
        registration: registration(<String>['pos.sell']),
        stockLocationId: 'location-1',
      ),
      throwsA(isA<StateError>()),
    );
  });

  test('manager can fetch branches before any location exists', () async {
    final http.Client client = MockClient((http.Request request) async {
      expect(
        request.url.path,
        endsWith('/api/v1/mobile/stock-location-branches'),
      );
      return http.Response(
        jsonEncode(<String, Object>{
          'data': <Object>[
            <String, String>{
              'id': 'branch-1',
              'code': 'MAIN',
              'name': 'Main Branch',
            },
          ],
        }),
        200,
      );
    });
    addTearDown(client.close);
    final List<MobileStockBranch> branches = await MobileStockLocationApi(
      environment: environment,
      client: client,
    ).branches(registration(<String>['inventory.manage']));
    expect(branches.single.name, 'Main Branch');
  });

  test('cashier cannot fetch storage creation branch options', () async {
    final http.Client client = MockClient(
      (_) async => throw StateError('Request must not be sent'),
    );
    addTearDown(client.close);
    await expectLater(
      MobileStockLocationApi(
        environment: environment,
        client: client,
      ).branches(registration(<String>['pos.sell'])),
      throwsA(isA<StateError>()),
    );
  });

  test('cashier can list active server locations', () async {
    final http.Client client = MockClient((http.Request request) async {
      expect(request.method, 'GET');
      expect(request.url.path, '/api/v1/mobile/stock-locations');
      expect(request.headers['Authorization'], 'Bearer token');
      return http.Response(
        jsonEncode(<String, Object>{
          'data': <Object>[location],
        }),
        200,
      );
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
      return http.Response(jsonEncode(<String, Object>{'data': location}), 201);
    });
    addTearDown(client.close);
    final MobileStockLocation created =
        await MobileStockLocationApi(
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
      MobileStockLocationApi(
        environment: environment,
        client: client,
      ).list(registration(<String>['inventory.manage'])),
      throwsFormatException,
    );
  });

  test(
    'server duplicate code validation is shown without claiming success',
    () async {
      final http.Client client = MockClient(
        (_) async => http.Response(
          jsonEncode(<String, Object>{
            'message': 'Validation failed',
            'errors': <String, Object>{
              'code': <String>[
                'A stock location with this code already exists.',
              ],
            },
          }),
          422,
        ),
      );
      addTearDown(client.close);
      await expectLater(
        MobileStockLocationApi(environment: environment, client: client).create(
          registration: registration(<String>['inventory.manage']),
          branchId: 'branch-id',
          code: 'MAIN',
          name: 'Duplicate',
          kind: 'store',
        ),
        throwsA(
          isA<StateError>().having(
            (StateError error) => error.message,
            'message',
            contains('already exists'),
          ),
        ),
      );
    },
  );

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
