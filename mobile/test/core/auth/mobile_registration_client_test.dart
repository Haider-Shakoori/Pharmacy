import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration_client.dart';
import 'package:businessos_pharmacy/core/device/device_identity.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  const DeviceIdentity device = DeviceIdentity(
    installationId: 'test-device-123',
    platform: 'android',
    deviceName: 'Android Test Device',
    model: 'emulator',
    osVersion: '13',
    appVersion: '0.1.0',
    buildNumber: '1',
  );
  final ServerEndpoint endpoint = ServerEndpoint.fromInput(
    'https://pharmacy.example.test',
    ServerEndpointKind.cloud,
  );

  test('registration rejects invalid credentials and sends key and password together', () async {
    final List<http.Request> requests = <http.Request>[];
    final MobileRegistrationClient registration = MobileRegistrationClient(
      client: MockClient((http.Request request) async {
        requests.add(request);
        return http.Response(
          '{"message":"Invalid activation key or credentials"}',
          401,
          headers: <String, String>{'content-type': 'application/json'},
        );
      }),
    );
    addTearDown(registration.close);

    await expectLater(
      registration.register(
        endpoint: endpoint,
        device: device,
        licenseKey: '  TEST-KEY  ',
        email: '  pharmacist@example.test  ',
        password: 'incorrect-password',
      ),
      throwsA(
        isA<MobileRegistrationException>()
            .having(
              (MobileRegistrationException e) => e.message,
              'server error',
              'Invalid activation key or credentials',
            )
            .having(
              (MobileRegistrationException e) => e.retryable,
              'retryable',
              false,
            ),
      ),
    );

    expect(requests, hasLength(1));
    expect(requests.single.method, 'POST');
    expect(requests.single.url.path, '/api/v1/mobile/register');
    final Map<String, dynamic> payload = jsonDecode(requests.single.body);
    expect(payload['license_key'], 'TEST-KEY');
    expect(payload['email'], 'pharmacist@example.test');
    expect(payload['password'], 'incorrect-password');
    expect(payload['device_id'], 'test-device-123');
  });
}
