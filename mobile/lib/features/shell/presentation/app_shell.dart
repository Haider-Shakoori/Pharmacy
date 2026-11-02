import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/alerts/presentation/operational_alerts_card.dart';
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
      const _ShellDestination(icon: Icons.dashboard_outlined, label: 'Home'),
      _ShellDestination(icon: Icons.point_of_sale_rounded, label: strings.pos),
      _ShellDestination(icon: Icons.inventory_2_outlined, label: strings.stock),
      const _ShellDestination(icon: Icons.bar_chart_rounded, label: 'Reports'),
      const _ShellDestination(icon: Icons.menu_rounded, label: 'More'),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(strings.appName),
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
            const ConnectivityBanner(),
            const SizedBox(height: 16),
            if (_selectedIndex == 0)
              _HomePanel(onNavigate: (int index) => setState(() => _selectedIndex = index))
            else if (_selectedIndex == 1)
              const OfflinePosPanel()
            else if (_selectedIndex == 2)
              const OfflineStockPanel()
            else if (_selectedIndex == 3)
              const _ReportsPanel(),
            if (_selectedIndex == 2) ...<Widget>[
              const SizedBox(height: 16),
              const OperationalAlertsCard(),
            ],
            if (_selectedIndex == 4) ...<Widget>[
              const OfflineCustomersPanel(),
              const SizedBox(height: 16),
              const OperationalAlertsCard(),
              const SizedBox(height: 16),
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

class _HomePanel extends StatelessWidget {
  const _HomePanel({required this.onNavigate});

  final ValueChanged<int> onNavigate;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Text('Pharmacy overview', style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 8),
      const Text('Choose a workspace. Live sales and profit analytics will appear when the reporting API is connected.'),
      const SizedBox(height: 16),
      Card(child: Column(children: <Widget>[
        ListTile(leading: const Icon(Icons.point_of_sale), title: const Text('New sale'), subtitle: const Text('Offline-ready checkout'), onTap: () => onNavigate(1)),
        const Divider(height: 1),
        ListTile(leading: const Icon(Icons.inventory_2_outlined), title: const Text('Medicines & stock'), subtitle: const Text('Browse local inventory'), onTap: () => onNavigate(2)),
        const Divider(height: 1),
        ListTile(leading: const Icon(Icons.bar_chart), title: const Text('Reports'), subtitle: const Text('Reporting workspace'), onTap: () => onNavigate(3)),
        const Divider(height: 1),
        ListTile(leading: const Icon(Icons.settings_outlined), title: const Text('More tools'), subtitle: const Text('Customers, alerts, sync and device settings'), onTap: () => onNavigate(4)),
      ])),
    ],
  );
}

class _ReportsPanel extends StatelessWidget {
  const _ReportsPanel();

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Text('Reports', style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 12),
      const Card(child: ListTile(leading: Icon(Icons.info_outline), title: Text('Reporting integration pending'), subtitle: Text('Sales, profit, expiry and stock reports will be connected to the existing Darmaltoon API. No sample figures are displayed as live data.'))),
    ],
  );
}
