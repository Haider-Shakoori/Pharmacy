import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('registration snapshot carries inventory alert policy', () {
    final MobileRegistration registration = MobileRegistration.fromApi(
      <String, dynamic>{
        'tenant': <String, dynamic>{
          'id': 'tenant-1',
          'name': 'Demo Pharmacy',
          'cloud_base_url': 'https://demo.example.test',
        },
        'activation_id': 'activation-1',
        'device_id': 'device-1',
        'access_token': 'access',
        'access_expires_at': '2026-10-01T00:00:00Z',
        'offline_lease': <String, dynamic>{
          'token': 'lease',
          'expires_at': '2026-10-01T00:00:00Z',
          'public_key': 'key',
        },
        'inventory_policy': <String, dynamic>{
          'low_stock_threshold': 15,
          'near_expiry_days': 45,
        },
        'user': <String, dynamic>{
          'id': 7,
          'name': 'Cashier',
          'email': 'cashier@example.test',
          'permissions': <String>['pos.sell'],
        },
      },
    );

    expect(registration.lowStockThreshold, 15);
    expect(registration.nearExpiryDays, 45);
    expect(registration.toJson()['low_stock_threshold'], 15);
    expect(registration.toJson()['near_expiry_days'], 45);
  });
  test('rejects absent tenant and user identifiers', () {
    final Map<String, dynamic> valid = <String, dynamic>{
      'tenant': <String, dynamic>{'id': 'tenant-1', 'name': 'Pharmacy'},
      'activation_id': 'activation-1',
      'access_token': 'access-token',
      'access_expires_at': '2026-12-01T00:00:00Z',
      'offline_lease': <String, dynamic>{
        'token': 'lease-token',
        'expires_at': '2026-12-01T00:00:00Z',
      },
      'user': <String, dynamic>{
        'id': 7,
        'name': 'Cashier',
        'email': 'cashier@example.test',
      },
    };

    final Map<String, dynamic> missingTenant = <String, dynamic>{
      ...valid,
      'tenant': <String, dynamic>{'name': 'Pharmacy'},
    };
    expect(
      () => MobileRegistration.fromApi(missingTenant),
      throwsFormatException,
    );

    final Map<String, dynamic> missingUser = <String, dynamic>{
      ...valid,
      'user': <String, dynamic>{'id': 7, 'name': 'Cashier'},
    };
    expect(
      () => MobileRegistration.fromApi(missingUser),
      throwsFormatException,
    );
  });

  test('rejects corrupt stored registration identity', () {
    final MobileRegistration registration = MobileRegistration(
      tenantId: 'tenant-1',
      tenantName: 'Pharmacy',
      activationId: 'activation-1',
      accessToken: 'access-token',
      accessExpiresAt: DateTime.utc(2026, 12),
      leaseToken: 'lease-token',
      leaseExpiresAt: DateTime.utc(2026, 12),
      userId: '7',
      userName: 'Cashier',
      userEmail: 'cashier@example.test',
      permissions: const <String>[],
    );
    final Map<String, Object?> json = registration.toJson();
    json.remove('tenant_id');
    expect(() => MobileRegistration.fromJson(json), throwsFormatException);
  });

  test('session refresh rejects missing user identity', () {
    final MobileRegistration existing = MobileRegistration(
      tenantId: 'tenant-1',
      tenantName: 'Pharmacy',
      activationId: 'activation-1',
      accessToken: 'old-access',
      accessExpiresAt: DateTime.utc(2026, 11),
      leaseToken: 'old-lease',
      leaseExpiresAt: DateTime.utc(2026, 11),
      userId: '7',
      userName: 'Cashier',
      userEmail: 'cashier@example.test',
      permissions: const <String>['pos.sell'],
    );

    expect(
      () => existing.withRefreshedSession(<String, dynamic>{
        'access_token': 'new-access',
        'access_expires_at': '2026-12-01T00:00:00Z',
        'offline_lease': <String, dynamic>{
          'token': 'new-lease',
          'expires_at': '2026-12-01T00:00:00Z',
        },
        'user': <String, dynamic>{'id': 7, 'name': 'Cashier'},
      }),
      throwsFormatException,
    );
  });

  test('rejects malformed permissions on restored session', () {
    final MobileRegistration registration = MobileRegistration(
      tenantId: 'tenant-1',
      tenantName: 'Pharmacy',
      activationId: 'activation-1',
      accessToken: 'access',
      accessExpiresAt: DateTime.utc(2026, 12),
      leaseToken: 'lease',
      leaseExpiresAt: DateTime.utc(2026, 12),
      userId: '7',
      userName: 'Cashier',
      userEmail: 'cashier@example.test',
      permissions: const <String>[],
    );
    final Map<String, Object?> snapshot = registration.toJson();
    snapshot['permissions'] = <Object>['pos.sell', 123];
    expect(
      () => MobileRegistration.fromJson(snapshot),
      throwsFormatException,
    );
  });

  test('refresh rejects malformed offline lease and user objects', () {
    final MobileRegistration registration = MobileRegistration(
      tenantId: 'tenant-1',
      tenantName: 'Pharmacy',
      activationId: 'activation-1',
      accessToken: 'old-access',
      accessExpiresAt: DateTime.utc(2026, 11),
      leaseToken: 'old-lease',
      leaseExpiresAt: DateTime.utc(2026, 11),
      userId: '7',
      userName: 'Cashier',
      userEmail: 'cashier@example.test',
      permissions: const <String>[],
    );
    final Map<String, dynamic> response = <String, dynamic>{
      'access_token': 'new-access',
      'access_expires_at': '2026-12-01T00:00:00Z',
      'offline_lease': <String, dynamic>{
        'token': 'new-lease',
        'expires_at': '2026-12-01T00:00:00Z',
      },
    };
    expect(
      () => registration.withRefreshedSession(<String, dynamic>{
        ...response,
        'offline_lease': 'invalid',
      }),
      throwsFormatException,
    );
    expect(
      () => registration.withRefreshedSession(<String, dynamic>{
        ...response,
        'user': 'invalid',
      }),
      throwsFormatException,
    );
  });


  test('session refresh cannot switch user or activation', () {
    final registration = MobileRegistration(
      tenantId: 'tenant-1', tenantName: 'Pharmacy',
      activationId: 'activation-1', deviceId: 'device-1', accessToken: 'old',
      accessExpiresAt: DateTime.utc(2026, 12), leaseToken: 'lease',
      leaseExpiresAt: DateTime.utc(2026, 12), userId: '7',
      userName: 'Cashier', userEmail: 'cashier@example.test',
      permissions: const <String>[],
    );
    final response = <String, dynamic>{
      'access_token': 'new',
      'access_expires_at': '2026-12-01T00:00:00Z',
      'offline_lease': <String, dynamic>{
        'token': 'new-lease', 'expires_at': '2026-12-01T00:00:00Z',
      },
    };
    expect(
      () => registration.withRefreshedSession(<String, dynamic>{
        ...response,
        'user': <String, dynamic>{
          'id': '8', 'name': 'Other', 'email': 'other@example.test',
        },
      }),
      throwsFormatException,
    );
    expect(
      () => registration.withRefreshedSession(<String, dynamic>{
        ...response, 'activation_id': 'activation-2',
      }),
      throwsFormatException,
    );
    expect(
      () => registration.withRefreshedSession(<String, dynamic>{
        ...response, 'device_id': 'device-2',
      }),
      throwsFormatException,
    );
    expect(registration.withRefreshedSession(response).userId, '7');
  });

}
