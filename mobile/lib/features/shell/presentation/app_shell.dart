import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/pos/presentation/offline_pos_panel.dart';
import 'package:businessos_pharmacy/features/registration/presentation/mobile_registration_card.dart';
import 'package:businessos_pharmacy/features/settings/presentation/connection_mode_card.dart';
import 'package:businessos_pharmacy/features/status/presentation/connectivity_banner.dart';
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
        icon: Icons.point_of_sale_rounded,
        label: strings.pos,
        title: strings.posTitle,
        body: strings.posBody,
      ),
      _ShellDestination(
        icon: Icons.inventory_2_outlined,
        label: strings.stock,
        title: strings.stockTitle,
        body: strings.stockBody,
      ),
      _ShellDestination(
        icon: Icons.people_alt_outlined,
        label: strings.customers,
        title: strings.customersTitle,
        body: strings.customersBody,
      ),
      _ShellDestination(
        icon: Icons.sync_rounded,
        label: strings.sync,
        title: strings.syncTitle,
        body: strings.syncBody,
      ),
    ];

    final _ShellDestination current = destinations[_selectedIndex];

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
            if (_selectedIndex != 0) ...<Widget>[
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        strings.foundation,
                        style: Theme.of(context).textTheme.titleLarge
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 8),
                      Text(strings.foundationBody),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
            ],
            if (_selectedIndex == 0)
              const OfflinePosPanel()
            else
              _FeaturePanel(destination: current),
            if (_selectedIndex == 3) ...<Widget>[
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

class _FeaturePanel extends StatelessWidget {
  const _FeaturePanel({required this.destination});

  final _ShellDestination destination;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: <Widget>[
            Icon(destination.icon, size: 44),
            const SizedBox(height: 12),
            Text(
              destination.title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Text(
              destination.body,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}

class _ShellDestination {
  const _ShellDestination({
    required this.icon,
    required this.label,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String label;
  final String title;
  final String body;
}
