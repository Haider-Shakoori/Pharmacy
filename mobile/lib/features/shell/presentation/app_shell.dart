import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/alerts/presentation/operational_alerts_card.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/offline_catalog_panels.dart';
import 'package:businessos_pharmacy/features/dashboard/presentation/darmaltoon_dashboard.dart';
import 'package:businessos_pharmacy/features/pos/presentation/offline_pos_panel.dart';
import 'package:businessos_pharmacy/features/registration/presentation/mobile_registration_card.dart';
import 'package:businessos_pharmacy/features/settings/presentation/connection_mode_card.dart';
import 'package:businessos_pharmacy/features/status/presentation/connectivity_banner.dart';
import 'package:businessos_pharmacy/features/sync/presentation/sync_status_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';

class AppShell extends ConsumerStatefulWidget {
  const AppShell({required this.localeNotifier, super.key});

  final ValueNotifier<AppLocale> localeNotifier;

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell> {
  int _selectedIndex = 0;

  void _navigateTo(int index) {
    setState(() => _selectedIndex = index);
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    final AppLocale appLocale = AppLocale.fromCode(
      Localizations.localeOf(context).languageCode,
    );
    final String homeLabel = switch (appLocale) {
      AppLocale.dari => 'خانه',
      AppLocale.pashto => 'کور',
      _ => 'Home',
    };
    final String moreLabel = switch (appLocale) {
      AppLocale.dari => 'بیشتر',
      AppLocale.pashto => 'نور',
      _ => 'More',
    };

    final List<_ShellDestination> destinations = <_ShellDestination>[
      _ShellDestination(icon: Icons.grid_view_rounded, label: homeLabel),
      _ShellDestination(icon: Icons.point_of_sale_rounded, label: strings.pos),
      _ShellDestination(icon: Icons.inventory_2_outlined, label: strings.stock),
      _ShellDestination(icon: Icons.people_alt_outlined, label: strings.customers),
      _ShellDestination(icon: Icons.tune_rounded, label: moreLabel),
    ];

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primary,
                borderRadius: BorderRadius.circular(11),
              ),
              child: Padding(
                padding: const EdgeInsets.all(5),
                child: SvgPicture.asset('assets/brand/darmaltoon-mark.svg'),
              ),
            ),
            const SizedBox(width: 10),
            Flexible(
              child: Text(strings.appName,
                  maxLines: 1, overflow: TextOverflow.ellipsis),
            ),
          ],
        ),
        actions: <Widget>[
          PopupMenuButton<AppLocale>(
            tooltip: strings.language,
            initialValue: appLocale,
            onSelected: (AppLocale locale) {
              widget.localeNotifier.value = locale;
            },
            itemBuilder: (BuildContext context) {
              return AppLocale.values
                  .map(
                    (AppLocale locale) => PopupMenuItem<AppLocale>(
                      value: locale,
                      child: Text(locale.label),
                    ),
                  )
                  .toList(growable: false);
            },
            icon: const Icon(Icons.language_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          key: ValueKey<int>(_selectedIndex),
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: <Widget>[
            const ConnectivityBanner(),
            const SizedBox(height: 14),
            switch (_selectedIndex) {
              0 => DarmaltoonDashboard(onNavigate: _navigateTo),
              1 => const OfflinePosPanel(),
              2 => const OfflineStockPanel(),
              3 => const OfflineCustomersPanel(),
              _ => Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  const SyncStatusCard(),
                  const SizedBox(height: 16),
                  const MobileRegistrationCard(),
                  const SizedBox(height: 16),
                  ConnectionModeCard(
                    defaultCloudUri: AppEnvironment.current.apiBaseUri,
                  ),
                ],
              ),
            },
            if (_selectedIndex == 2) ...<Widget>[
              const SizedBox(height: 16),
              const OperationalAlertsCard(),
            ],
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: _navigateTo,
        destinations: destinations
            .map(
              (_ShellDestination destination) => NavigationDestination(
                icon: Icon(destination.icon),
                label: destination.label,
              ),
            )
            .toList(growable: false),
      ),
    );
  }
}

class _ShellDestination {
  const _ShellDestination({required this.icon, required this.label});

  final IconData icon;
  final String label;
}
