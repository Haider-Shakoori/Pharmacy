import 'package:businessos_pharmacy/core/database/local_database_scope.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'tenant identity produces a stable endpoint-independent database name',
    () {
      final LocalDatabaseScope scope = LocalDatabaseScope.forTenant(
        '01M3K2ENMW5HBMVQ5B7QH5A5R8',
      );

      expect(
        scope.databaseName,
        'businessos_pharmacy_01m3k2enmw5hbmvq5b7qh5a5r8',
      );
    },
  );

  test('unsafe filename characters are normalized', () {
    final LocalDatabaseScope scope = LocalDatabaseScope.forTenant(
      ' Tenant / Demo # 01 ',
    );

    expect(scope.databaseName, 'businessos_pharmacy_tenant_demo_01');
  });

  test('empty tenant identities are rejected', () {
    expect(
      () => LocalDatabaseScope.forTenant('---'),
      throwsA(isA<FormatException>()),
    );
  });
}
