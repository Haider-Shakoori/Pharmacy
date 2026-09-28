import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_authorizer.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_verifier.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:cryptography/cryptography.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

class _MemorySecureStore implements SecureStore {
  final Map<String, String> values = <String, String>{};

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }
}

Future<MobileRegistration> _registration({
  required DateTime issuedAt,
  required DateTime expiresAt,
  String deviceId = 'device-1',
}) async {
  final Ed25519 algorithm = Ed25519();
  final KeyPair keyPair = await algorithm.newKeyPair();
  final SimplePublicKey publicKey = await keyPair.extractPublicKey();
  final Map<String, Object?> payload = <String, Object?>{
    'v': 1,
    'tenant_id': 'tenant-1',
    'subscription_id': 'subscription-1',
    'license_id': 'license-1',
    'license_version': 1,
    'activation_id': 'activation-1',
    'device_id': 'device-1',
    'plan_code': 'STANDARD',
    'subscription_status': 'active',
    'issued_at': issuedAt.toUtc().millisecondsSinceEpoch ~/ 1000,
    'expires_at': expiresAt.toUtc().millisecondsSinceEpoch ~/ 1000,
  };
  final String payloadText = jsonEncode(payload);
  final List<int> payloadBytes = utf8.encode(payloadText);
  final Signature signature = await algorithm.sign(
    payloadBytes,
    keyPair: keyPair,
  );

  String urlEncode(List<int> bytes) {
    return base64UrlEncode(bytes).replaceAll('=', '');
  }

  return MobileRegistration(
    tenantId: 'tenant-1',
    tenantName: 'Test Pharmacy',
    activationId: 'activation-1',
    deviceId: deviceId,
    accessToken: 'access',
    accessExpiresAt: expiresAt,
    leaseToken:
        'v1.${urlEncode(payloadBytes)}.${urlEncode(signature.bytes)}',
    leaseExpiresAt: expiresAt,
    leasePublicKey: base64Encode(publicKey.bytes),
    userId: '1',
    userName: 'Pharmacist',
    userEmail: 'pharmacist@example.test',
    permissions: const <String>['pos.sell'],
  );
}

void main() {
  test('valid signed lease verifies and is bound to this device', () async {
    final DateTime now = DateTime.utc(2026, 9, 28, 10);
    final MobileRegistration registration = await _registration(
      issuedAt: now.subtract(const Duration(minutes: 1)),
      expiresAt: now.add(const Duration(days: 2)),
    );

    final OfflineLeaseClaims claims = await OfflineLeaseVerifier().verify(
      registration,
    );

    expect(claims.expiresAt, now.add(const Duration(days: 2)));
  });

  test('copied lease fails device binding', () async {
    final DateTime now = DateTime.utc(2026, 9, 28, 10);
    final MobileRegistration registration = await _registration(
      issuedAt: now.subtract(const Duration(minutes: 1)),
      expiresAt: now.add(const Duration(days: 2)),
      deviceId: 'different-device',
    );

    await expectLater(
      OfflineLeaseVerifier().verify(registration),
      throwsA(
        isA<OfflineLeaseException>().having(
          (OfflineLeaseException error) => error.code,
          'code',
          OfflineLeaseFailure.invalid,
        ),
      ),
    );
  });

  test('expired lease blocks transactions without deleting local data', () async {
    final DateTime now = DateTime.utc(2026, 9, 28, 10);
    final MobileRegistration registration = await _registration(
      issuedAt: now.subtract(const Duration(days: 2)),
      expiresAt: now.subtract(const Duration(minutes: 1)),
    );
    final _MemorySecureStore store = _MemorySecureStore();
    final MobileRegistrationRepository repository =
        MobileRegistrationRepository(store);
    final PharmacyDatabase database = PharmacyDatabase(NativeDatabase.memory());
    await repository.save(registration);

    await expectLater(
      OfflineLeaseAuthorizer(
        database: database,
        registrationRepository: repository,
        clock: () => now,
      ).assertCanTransact(),
      throwsA(
        isA<OfflineLeaseException>().having(
          (OfflineLeaseException error) => error.code,
          'code',
          OfflineLeaseFailure.expired,
        ),
      ),
    );

    await database.customStatement(
      '''
      INSERT INTO app_metadata (key, value, updated_at)
      VALUES ('existing-data', 'preserved', ?)
      ''',
      <Object?>[now.millisecondsSinceEpoch ~/ 1000],
    );

    expect(
      await database
          .customSelect(
            "SELECT value FROM app_metadata WHERE key = 'existing-data'",
          )
          .map((row) => row.read<String>('value'))
          .getSingle(),
      'preserved',
    );

    await database.close();
  });

  test('clock rollback after prior use blocks new transactions', () async {
    final DateTime now = DateTime.utc(2026, 9, 28, 10);
    final MobileRegistration registration = await _registration(
      issuedAt: now.subtract(const Duration(hours: 1)),
      expiresAt: now.add(const Duration(days: 2)),
    );
    final _MemorySecureStore store = _MemorySecureStore();
    final MobileRegistrationRepository repository =
        MobileRegistrationRepository(store);
    final PharmacyDatabase database = PharmacyDatabase(NativeDatabase.memory());
    await repository.save(registration);

    final OfflineLeaseAuthorizer first = OfflineLeaseAuthorizer(
      database: database,
      registrationRepository: repository,
      clock: () => now,
    );
    await first.assertCanTransact();

    await expectLater(
      OfflineLeaseAuthorizer(
        database: database,
        registrationRepository: repository,
        clock: () => now.subtract(const Duration(hours: 1)),
      ).assertCanTransact(),
      throwsA(
        isA<OfflineLeaseException>().having(
          (OfflineLeaseException error) => error.code,
          'code',
          OfflineLeaseFailure.clockRollback,
        ),
      ),
    );

    await database.close();
  });
}
