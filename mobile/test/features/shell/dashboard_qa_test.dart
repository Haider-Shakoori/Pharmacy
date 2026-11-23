import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
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
  Stream<NetworkLinkState> watch() =>
      Stream<NetworkLinkState>.value(NetworkLinkState.offline);
}

Future<void> _pumpDashboard(
  WidgetTester tester, {
  required AppLocale locale,
  required Size size,
  double textScale = 1,
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final ValueNotifier<AppLocale> notifier = ValueNotifier<AppLocale>(locale);
  addTearDown(notifier.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        connectivityMonitorProvider.overrideWithValue(_OfflineMonitor()),
        mobileRegistrationProvider.overrideWith((Ref ref) async => null),
      ],
      child: MaterialApp(
        locale: locale.locale,
        supportedLocales: AppLocale.values
            .map((AppLocale value) => value.locale)
            .toList(growable: false),
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        builder: (BuildContext context, Widget? child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        ),
        home: AppShell(localeNotifier: notifier),
      ),
    ),
  );
  await tester.pump();
}

void main() {
  for (final ({Size size, AppLocale locale, int columns}) config
      in <({Size size, AppLocale locale, int columns})>[
        (size: const Size(320, 720), locale: AppLocale.english, columns: 2),
        (size: const Size(390, 844), locale: AppLocale.dari, columns: 3),
        (size: const Size(900, 1100), locale: AppLocale.english, columns: 4),
      ]) {
    testWidgets('More grid responds to ${config.size.width}px '
        '${config.locale.code}', (WidgetTester tester) async {
      await _pumpDashboard(tester, locale: config.locale, size: config.size);
      final Finder destinations = find.descendant(
        of: find.byType(NavigationBar),
        matching: find.byType(NavigationDestination),
      );
      await tester.tap(destinations.last);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      final GridView grid = tester.widget<GridView>(find.byType(GridView));
      final SliverGridDelegateWithFixedCrossAxisCount delegate =
          grid.gridDelegate as SliverGridDelegateWithFixedCrossAxisCount;
      expect(delegate.crossAxisCount, config.columns);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }

  for (final ({Size size, AppLocale locale, double scale}) config
      in <({Size size, AppLocale locale, double scale})>[
        (size: const Size(320, 720), locale: AppLocale.english, scale: 1),
        (size: const Size(390, 844), locale: AppLocale.dari, scale: 1),
        (size: const Size(900, 1100), locale: AppLocale.english, scale: 1),
        (size: const Size(320, 720), locale: AppLocale.dari, scale: 1.7),
      ]) {
    testWidgets('dashboard fits ${config.size.width}px ${config.locale.code} '
        'scale ${config.scale}', (WidgetTester tester) async {
      await _pumpDashboard(
        tester,
        locale: config.locale,
        size: config.size,
        textScale: config.scale,
      );
      expect(find.byType(GridView), findsWidgets);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
}
