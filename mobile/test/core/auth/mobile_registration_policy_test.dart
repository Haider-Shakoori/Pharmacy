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
}
