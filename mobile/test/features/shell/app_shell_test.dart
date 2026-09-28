import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/network/connectivity_monitor.dart';
import 'package:businessos_pharmacy/features/shell/presentation/app_shell.dart';
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
    final ValueNotifier<AppLocale> localeNotifier =
        ValueNotifier<AppLocale>(AppLocale.english);
    addTearDown(localeNotifier.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          connectivityMonitorProvider.overrideWithValue(_OfflineMonitor()),
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

    expect(find.text('Offline-ready mobile foundation'), findsOneWidget);
    expect(find.textContaining('No network link'), findsOneWidget);
    expect(find.text('POS'), findsWidgets);
    expect(find.text('Sync'), findsWidgets);
  });
}
