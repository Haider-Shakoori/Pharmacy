import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
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

void main() {
  test(
    'registration persists tenant binding without license or password',
    () async {
      final _MemorySecureStore store = _MemorySecureStore();
      final MobileRegistrationRepository repository =
          MobileRegistrationRepository(store);

      final MobileRegistration registration = MobileRegistration(
        tenantId: 'tenant-01',
        tenantName: 'Kabul Pharmacy',
        activationId: 'activation-01',
        accessToken: 'signed-access',
        accessExpiresAt: DateTime.utc(2026, 10, 1),
        leaseToken: 'signed-lease',
        leaseExpiresAt: DateTime.utc(2026, 10, 1),
        userId: '1',
        userName: 'Pharmacist',
        userEmail: 'pharmacist@example.test',
        permissions: const <String>['pos.sell'],
        cloudBaseUrl: 'https://kabul.pharmacy.businessos.af',
      );

      await repository.save(registration);
      final MobileRegistration? restored = await repository.load();

      expect(restored?.tenantId, 'tenant-01');
      expect(restored?.userEmail, 'pharmacist@example.test');
    expect(restored?.deviceId, 'device-01');
    expect(restored?.leasePublicKey, 'public-key');

      final String raw = store.values.values.single;
      final Map<String, dynamic> json = jsonDecode(raw) as Map<String, dynamic>;
      expect(json.containsKey('license_key'), isFalse);
      expect(json.containsKey('password'), isFalse);
    },
  );
}
