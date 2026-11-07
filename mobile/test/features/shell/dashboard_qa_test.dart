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
  final notifier = ValueNotifier<AppLocale>(locale);
  addTearDown(notifier.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        connectivityMonitorProvider.overrideWithValue(_OfflineMonitor()),
        mobileRegistrationProvider.overrideWith((Ref ref) async => null),
      ],
      child: MaterialApp(
        locale: locale.locale,
        supportedLocales: AppLocale.values.map((e) => e.locale).toList(),
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        builder: (context, child) => MediaQuery(
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
  for (final config
      in <({Size size, AppLocale locale, double scale, int columns})>[
        (
          size: const Size(320, 720),
          locale: AppLocale.english,
          scale: 1,
          columns: 2,
        ),
        (
          size: const Size(390, 844),
          locale: AppLocale.dari,
          scale: 1,
          columns: 2,
        ),
        (
          size: const Size(600, 900),
          locale: AppLocale.dari,
          scale: 1,
          columns: 3,
        ),
        (
          size: const Size(900, 1100),
          locale: AppLocale.english,
          scale: 1,
          columns: 4,
        ),
        (
          size: const Size(320, 720),
          locale: AppLocale.dari,
          scale: 1.7,
          columns: 2,
        ),
      ]) {
    testWidgets(
      'dashboard grid ${config.size.width}px ${config.locale.code} scale ${config.scale}',
      (tester) async {
        await _pumpDashboard(
          tester,
          locale: config.locale,
          size: config.size,
          textScale: config.scale,
        );
        final shortcut = find.byKey(
          const ValueKey('dashboard-shortcut-1-POS (Sales)'),
        );
        await tester.ensureVisible(shortcut);
        await tester.pump();
        final grid = tester.widget<GridView>(find.byType(GridView).first);
        final delegate =
            grid.gridDelegate as SliverGridDelegateWithFixedCrossAxisCount;
        expect(delegate.crossAxisCount, config.columns);
        expect(shortcut, findsOneWidget);
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox.shrink());
      },
    );
  }

  testWidgets(
    'dashboard search is labelled and has a live result announcement',
    (tester) async {
      final semantics = tester.ensureSemantics();

      await _pumpDashboard(
        tester,
        locale: AppLocale.english,
        size: const Size(390, 844),
      );
      final shortcut = find.byKey(
        const ValueKey('dashboard-shortcut-1-POS (Sales)'),
      );
      await tester.ensureVisible(shortcut);
      await tester.pump();
      expect(
        find.byWidgetPredicate(
          (widget) =>
              widget is Semantics &&
              widget.properties.button == true &&
              (widget.properties.label ?? '').contains('Offline checkout'),
        ),
        findsOneWidget,
      );
      final counter = find.byKey(
        const ValueKey('dashboard-search-result-count'),
      );
      expect(
        find.ancestor(of: counter, matching: find.byType(Semantics)),
        findsWidgets,
      );
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
      semantics.dispose();
    },
  );
}
