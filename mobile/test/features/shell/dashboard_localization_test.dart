import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('dashboard localization', () {
    const moduleNames = <String>[
      'POS (Sales)',
      'Medicines',
      'Inventory',
      'Purchases',
      'Customers',
      'Reports',
      'Alerts',
      'Sync',
      'Settings',
    ];
    const moduleDetails = <String>[
      'Offline checkout',
      'Catalog & stock',
      'Batches & expiry',
      'Orders & receiving',
      'Accounts',
      'Business insights',
      'Stock & expiry',
      'Offline-first',
      'Device & access',
    ];

    for (final locale in AppLocale.values) {
      test('${locale.code} has complete dashboard labels', () {
        final strings = AppStrings.forLocale(locale);
        expect(strings.dashboardTitle, isNotEmpty);
        expect(strings.dashboardWorkspace, isNotEmpty);
        expect(strings.dashboardLocalOverview, isNotEmpty);
        expect(strings.dashboardSearchHint, isNotEmpty);
        expect(strings.dashboardResultCount(9, filtered: false), contains('9'));
        expect(strings.dashboardResultCount(1, filtered: true), contains('1'));
        expect(strings.dashboardMedicineCount(12), contains('12'));

        for (final name in moduleNames) {
          expect(strings.dashboardModuleTitle(name), isNotEmpty);
        }
        for (final detail in moduleDetails) {
          expect(strings.dashboardModuleDetail(detail), isNotEmpty);
        }
      });
    }

    test('Dari and Pashto translate every dashboard shortcut', () {
      for (final locale in <AppLocale>[AppLocale.dari, AppLocale.pashto]) {
        final strings = AppStrings.forLocale(locale);
        for (final name in moduleNames) {
          expect(strings.dashboardModuleTitle(name), isNot(name));
        }
        for (final detail in moduleDetails) {
          expect(strings.dashboardModuleDetail(detail), isNot(detail));
        }
      }
    });

    test('unknown shortcut values remain readable', () {
      final strings = AppStrings.forLocale(AppLocale.dari);
      expect(strings.dashboardModuleTitle('Unknown'), 'Unknown');
      expect(strings.dashboardModuleDetail('Unknown'), 'Unknown');
    });
  });
}
