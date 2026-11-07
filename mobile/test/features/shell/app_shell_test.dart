import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/network/connectivity_monitor.dart';
import 'package:businessos_pharmacy/features/shell/presentation/app_shell.dart';
import 'package:businessos_pharmacy/features/sync/presentation/sync_status_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class _OfflineMonitor implements ConnectivityMonitor {
  @override
  Future<NetworkLinkState> current() async => NetworkLinkState.offline;

  @override
  Stream<NetworkLinkState> watch() {
    return Stream<NetworkLinkState>.value(NetworkLinkState.offline);
  }
}

void main() {
  testWidgets('foundation shell renders without network access', (
    WidgetTester tester,
  ) async {
    final ValueNotifier<AppLocale> localeNotifier = ValueNotifier<AppLocale>(
      AppLocale.english,
    );
    addTearDown(localeNotifier.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          connectivityMonitorProvider.overrideWithValue(_OfflineMonitor()),
          mobileRegistrationProvider.overrideWith((Ref ref) async => null),
        ],
        child: MaterialApp(
          locale: AppLocale.english.locale,
          supportedLocales: AppLocale.values
              .map((AppLocale locale) => locale.locale)
              .toList(growable: false),
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          home: AppShell(localeNotifier: localeNotifier),
        ),
      ),
    );
    await tester.pump();
    expect(find.byKey(const ValueKey('dashboard-refresh')), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('dashboard-refresh')));
    await tester.pump();

    await tester.enterText(
      find.byKey(const ValueKey('dashboard-module-search')),
      'Purchases',
    );
    await tester.pump();
    expect(find.text('Purchases'), findsNWidgets(2));
    expect(find.text('POS (Sales)'), findsNothing);
    await tester.tap(find.byKey(const ValueKey('dashboard-clear-search')));
    await tester.pump();
    expect(find.text('POS (Sales)'), findsOneWidget);
    await tester.enterText(
      find.byKey(const ValueKey('dashboard-module-search')),
      '',
    );
    await tester.pump();

    await tester.tap(find.text('Medicines').last);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(
      find.text(
        'Device registration is required before transactional POS is enabled.',
      ),
      findsOneWidget,
    );
    expect(find.textContaining('No network link'), findsOneWidget);
    expect(find.text('POS'), findsWidgets);
    // Sync moved into the More tab in the five-destination shell.
    await tester.tap(find.text('More').last);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.tap(find.text('Sync').last);
    await tester.pump();
    expect(find.byType(SyncStatusCard), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 100));
  });
}
