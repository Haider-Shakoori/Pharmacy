import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppEnvironment', () {
    test('allows an unconfigured tenant endpoint', () {
      final AppEnvironment environment = AppEnvironment.fromValues();

      expect(environment.apiBaseUri, isNull);
      expect(environment.isApiConfigured, isFalse);
    });

    test('requires https for non-local endpoints', () {
      expect(
        () => AppEnvironment.fromValues(
          apiBaseUrl: 'http://demo.pharmacy.businessos.af',
        ),
        throwsFormatException,
      );
    });

    test('allows Android emulator localhost during development', () {
      final AppEnvironment environment = AppEnvironment.fromValues(
        flavor: 'development',
        apiBaseUrl: 'http://10.0.2.2:8000',
      );

      expect(environment.apiBaseUri?.host, '10.0.2.2');
      expect(environment.isApiConfigured, isTrue);
    });
  });
}
