import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Dari and Pashto are RTL locales', () {
    expect(AppLocale.english.isRtl, isFalse);
    expect(AppLocale.dari.isRtl, isTrue);
    expect(AppLocale.pashto.isRtl, isTrue);
  });

  test('all supported locales expose core navigation strings', () {
    for (final AppLocale locale in AppLocale.values) {
      final AppStrings strings = AppStrings.forLocale(locale);

      expect(strings.appName, isNotEmpty);
      expect(strings.pos, isNotEmpty);
      expect(strings.stock, isNotEmpty);
      expect(strings.customers, isNotEmpty);
      expect(strings.sync, isNotEmpty);
    }
  });
}
