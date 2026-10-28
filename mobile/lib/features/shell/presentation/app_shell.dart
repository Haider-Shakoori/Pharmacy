import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/alerts/presentation/operational_alerts_card.dart';
import 'package:businessos_pharmacy/features/shell/presentation/pharmacy_dashboard.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/offline_catalog_panels.dart';
import 'package:businessos_pharmacy/features/pos/presentation/offline_pos_panel.dart';
import 'package:businessos_pharmacy/features/registration/presentation/mobile_registration_card.dart';
import 'package:businessos_pharmacy/features/settings/presentation/connection_mode_card.dart';
import 'package:businessos_pharmacy/features/status/presentation/connectivity_banner.dart';
import 'package:businessos_pharmacy/features/sync/presentation/sync_status_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class AppShell extends ConsumerStatefulWidget {
  const AppShell({required this.localeNotifier, super.key});

  final ValueNotifier<AppLocale> localeNotifier;

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell> {
  int _selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);

    final List<_ShellDestination> destinations = <_ShellDestination>[
      _ShellDestination(
        icon: Icons.home_rounded,
        label:
            AppLocale.fromCode(Localizations.localeOf(context).languageCode) ==
                AppLocale.english
            ? 'Home'
            : AppLocale.fromCode(
                    Localizations.localeOf(context).languageCode,
                  ) ==
                  AppLocale.dari
            ? 'خانه'
            : 'کور',
      ),
      _ShellDestination(icon: Icons.point_of_sale_rounded, label: strings.pos),
      _ShellDestination(icon: Icons.inventory_2_outlined, label: strings.stock),
      _ShellDestination(
        icon: Icons.people_alt_outlined,
        label: strings.customers,
      ),
      _ShellDestination(icon: Icons.sync_rounded, label: strings.sync),
    ];

    return Scaffold(
      appBar: AppBar(
        title: const Text('Darmaltoon  درملتون'),
        actions: <Widget>[
          PopupMenuButton<AppLocale>(
            tooltip: strings.language,
            initialValue: AppLocale.fromCode(
              Localizations.localeOf(context).languageCode,
            ),
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
          padding: const EdgeInsets.all(16),
          children: <Widget>[
            if (_selectedIndex == 0)
              PharmacyDashboard(
                onOpenSection: (int index) =>
                    setState(() => _selectedIndex = index),
              )
            else ...<Widget>[
              const ConnectivityBanner(),
              const SizedBox(height: 16),
              if (_selectedIndex == 1)
                const OfflinePosPanel()
              else if (_selectedIndex == 2)
                const OfflineStockPanel()
              else if (_selectedIndex == 3)
                const OfflineCustomersPanel(),
            ],
            if (_selectedIndex == 2) ...<Widget>[
              const SizedBox(height: 16),
              const OperationalAlertsCard(),
            ],
            if (_selectedIndex == 4) ...<Widget>[
              const SizedBox(height: 16),
              const SyncStatusCard(),
              const SizedBox(height: 16),
              const MobileRegistrationCard(),
              const SizedBox(height: 16),
              ConnectionModeCard(
                defaultCloudUri: AppEnvironment.current.apiBaseUri,
              ),
            ],
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (int index) {
          setState(() {
            _selectedIndex = index;
          });
        },
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
