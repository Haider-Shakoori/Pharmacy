import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_duplicate_preflight.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  final environment = AppEnvironment.fromValues(
    apiBaseUrl: 'https://example.test/',
  );
  final registration = MobileRegistration(
    tenantId: 'tenant',
    tenantName: 'Test',
    activationId: 'activation',
    accessToken: 'token',
    accessExpiresAt: DateTime.now().add(const Duration(hours: 1)),
    leaseToken: 'lease',
    leaseExpiresAt: DateTime.now().add(const Duration(hours: 1)),
    userId: 'user',
    userName: 'Pharmacist',
    userEmail: 'test@example.test',
    permissions: const ['medicines.manage'],
  );
  final handoff = MedicineRegistrationHandoff.prepare(
    reviewed: const ReviewedMedicineScan(
      brandName: 'Example',
      genericName: 'Ingredient',
      strength: '500 mg',
      dosageForm: 'Tablet',
      barcode: '12345678',
    ),
    duplicateCheckCompleted: true,
    possibleDuplicates: const [],
  );

  test(
    'allows registration preflight only when server confirms no matches',
    () async {
      final client = MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.url.path, '/api/v1/mobile/medicines/duplicate-check');
        expect(request.headers['Authorization'], 'Bearer token');
        expect(jsonDecode(request.body), {
          'brand_name': 'Example',
          'barcode': '12345678',
        });
        return http.Response(
          jsonEncode({
            'data': {'registration_allowed': false, 'possible_duplicates': []},
          }),
          200,
        );
      });
      addTearDown(client.close);
      expect(
        await MedicineDuplicatePreflight(
          environment: environment,
          client: client,
        ).check(registration: registration, handoff: handoff),
        isEmpty,
      );
    },
  );

  test('returns server duplicate matches', () async {
    final client = MockClient(
      (_) async => http.Response(
        jsonEncode({
          'data': {
            'registration_allowed': false,
            'possible_duplicates': [
              {'brand_name': 'Existing medicine'},
            ],
          },
        }),
        200,
      ),
    );
    addTearDown(client.close);
    expect(
      await MedicineDuplicatePreflight(
        environment: environment,
        client: client,
      ).check(registration: registration, handoff: handoff),
      ['Existing medicine'],
    );
  });

  test('fails closed on server errors', () async {
    final client = MockClient((_) async => http.Response('Unavailable', 503));
    addTearDown(client.close);
    await expectLater(
      MedicineDuplicatePreflight(
        environment: environment,
        client: client,
      ).check(registration: registration, handoff: handoff),
      throwsStateError,
    );
  });

  test('fails closed when the connection fails', () async {
    final client = MockClient(
      (_) async => throw http.ClientException('offline'),
    );
    addTearDown(client.close);
    await expectLater(
      MedicineDuplicatePreflight(
        environment: environment,
        client: client,
      ).check(registration: registration, handoff: handoff),
      throwsStateError,
    );
  });

  test('fails closed on a malformed server response', () async {
    final client = MockClient((_) async => http.Response('{"data":{}}', 200));
    addTearDown(client.close);
    await expectLater(
      MedicineDuplicatePreflight(
        environment: environment,
        client: client,
      ).check(registration: registration, handoff: handoff),
      throwsFormatException,
    );
  });

  test(
    'does not contact server without medicine management permission',
    () async {
      final unauthorized = MobileRegistration(
        tenantId: 'tenant',
        tenantName: 'Test',
        activationId: 'activation',
        accessToken: 'token',
        accessExpiresAt: DateTime.now().add(const Duration(hours: 1)),
        leaseToken: 'lease',
        leaseExpiresAt: DateTime.now().add(const Duration(hours: 1)),
        userId: 'user',
        userName: 'Pharmacist',
        userEmail: 'test@example.test',
        permissions: const [],
      );
      var requests = 0;
      final client = MockClient((_) async {
        requests++;
        return http.Response('{}', 200);
      });
      addTearDown(client.close);
      await expectLater(
        MedicineDuplicatePreflight(
          environment: environment,
          client: client,
        ).check(registration: unauthorized, handoff: handoff),
        throwsStateError,
      );
      expect(requests, 0);
    },
  );
}
