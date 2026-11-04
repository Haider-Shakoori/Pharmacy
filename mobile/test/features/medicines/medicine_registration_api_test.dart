import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_registration_api.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_fields.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_form_state.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
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
    permissions: const <String>['medicines.manage'],
  );
  final handoff = MedicineRegistrationHandoff.prepare(
    reviewed: const ReviewedMedicineScan(
      brandName: 'Example',
      genericName: null,
      strength: null,
      dosageForm: null,
      barcode: null,
    ),
    duplicateCheckCompleted: true,
    possibleDuplicates: const <String>[],
  );
  final form = MedicineRegistrationFormState(
    handoff: handoff,
    fields: const MedicineRegistrationFields(
      medicineCode: 'TEST-001',
      purchaseUnit: 'pack',
      saleUnit: 'unit',
      unitsPerPurchaseUnit: 10,
      reorderLevel: 0,
      prescriptionRequired: false,
      batchTrackingRequired: true,
      expiryTrackingRequired: true,
    ),
    pharmacistApproved: true,
  );
  final environment = AppEnvironment.fromValues(
    apiBaseUrl: 'https://example.test/',
  );

  test('creates only after successful duplicate preflight', () async {
    final requests = <String>[];
    final client = MockClient((request) async {
      requests.add(request.url.path);
      if (request.url.path.endsWith('/duplicate-check')) {
        return http.Response(
          jsonEncode(<String, Object>{
            'data': <String, Object>{
              'registration_allowed': false,
              'possible_duplicates': <Object>[],
            },
          }),
          200,
        );
      }
      expect(request.method, 'POST');
      final payload = jsonDecode(request.body) as Map<String, dynamic>;
      expect(payload['pharmacist_approved'], true);
      return http.Response('{"data":{"id":"medicine-1"}}', 201);
    });
    addTearDown(client.close);
    final id = await MedicineRegistrationApi(
      environment: environment,
      client: client,
    ).create(registration: registration, form: form);
    expect(id, 'medicine-1');
    expect(requests, <String>[
      '/api/v1/mobile/medicines/duplicate-check',
      '/api/v1/mobile/medicines',
    ]);
  });

  test('does not create when duplicate preflight fails', () async {
    var createRequests = 0;
    final client = MockClient((request) async {
      if (!request.url.path.endsWith('/duplicate-check')) {
        createRequests++;
      }
      return http.Response('Unavailable', 503);
    });
    addTearDown(client.close);
    await expectLater(
      MedicineRegistrationApi(
        environment: environment,
        client: client,
      ).create(registration: registration, form: form),
      throwsStateError,
    );
    expect(createRequests, 0);
  });

  test('does not create when server reports duplicates', () async {
    var createRequests = 0;
    final client = MockClient((request) async {
      if (!request.url.path.endsWith('/duplicate-check')) {
        createRequests++;
      }
      return http.Response(
        '{"data":{"registration_allowed":false,"possible_duplicates":[{"brand_name":"Existing"}]}}',
        200,
      );
    });
    addTearDown(client.close);
    await expectLater(
      MedicineRegistrationApi(
        environment: environment,
        client: client,
      ).create(registration: registration, form: form),
      throwsStateError,
    );
    expect(createRequests, 0);
  });

  test('does not create when pharmacist approval is missing', () async {
    var requests = 0;
    final client = MockClient((_) async {
      requests++;
      return http.Response('{}', 200);
    });
    addTearDown(client.close);
    await expectLater(
      MedicineRegistrationApi(environment: environment, client: client).create(
        registration: registration,
        form: MedicineRegistrationFormState(
          handoff: handoff,
          fields: form.fields,
          pharmacistApproved: false,
        ),
      ),
      throwsStateError,
    );
    expect(requests, 0);
  });

  test('does not create when duplicate preflight loses connection', () async {
    var createRequests = 0;
    final client = MockClient((request) async {
      if (!request.url.path.endsWith('/duplicate-check')) {
        createRequests++;
      }
      throw http.ClientException('offline');
    });
    addTearDown(client.close);
    await expectLater(
      MedicineRegistrationApi(
        environment: environment,
        client: client,
      ).create(registration: registration, form: form),
      throwsStateError,
    );
    expect(createRequests, 0);
  });

  test('rejects unauthorized creation after successful preflight', () async {
    var creationAttempts = 0;
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/duplicate-check')) {
        return http.Response(
          '{"data":{"registration_allowed":false,"possible_duplicates":[]}}',
          200,
        );
      }
      creationAttempts++;
      return http.Response('Unauthorized', 401);
    });
    addTearDown(client.close);
    await expectLater(
      MedicineRegistrationApi(environment: environment, client: client)
          .create(registration: registration, form: form),
      throwsA(isA<StateError>().having(
        (error) => error.message,
        'message',
        contains('Session expired'),
      )),
    );
    expect(creationAttempts, 1);
  });

  test('surfaces server field validation errors', () async {
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/duplicate-check')) {
        return http.Response(
          '{"data":{"registration_allowed":false,"possible_duplicates":[]}}',
          200,
        );
      }
      return http.Response(
        '{"errors":{"medicine_code":["Medicine code already exists"]}}',
        422,
      );
    });
    addTearDown(client.close);
    await expectLater(
      MedicineRegistrationApi(environment: environment, client: client)
          .create(registration: registration, form: form),
      throwsA(isA<StateError>().having(
        (error) => error.message,
        'message',
        contains('Medicine code already exists'),
      )),
    );
  });

  test('rejects malformed successful creation response', () async {
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/duplicate-check')) {
        return http.Response(
          '{"data":{"registration_allowed":false,"possible_duplicates":[]}}',
          200,
        );
      }
      return http.Response('{"data":{"id":123}}', 201);
    });
    addTearDown(client.close);
    await expectLater(
      MedicineRegistrationApi(environment: environment, client: client)
          .create(registration: registration, form: form),
      throwsFormatException,
    );
  });
}
