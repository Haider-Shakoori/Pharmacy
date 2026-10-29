import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/alerts/presentation/operational_alerts_card.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/offline_catalog_panels.dart';
import 'package:businessos_pharmacy/features/pos/presentation/offline_pos_panel.dart';
import 'package:businessos_pharmacy/features/registration/presentation/mobile_registration_card.dart';
import 'package:businessos_pharmacy/features/settings/presentation/connection_mode_card.dart';
import 'package:businessos_pharmacy/features/shell/presentation/pharmacy_dashboard.dart';
import 'package:businessos_pharmacy/features/status/presentation/connectivity_banner.dart';
import 'package:businessos_pharmacy/features/sync/presentation/sync_status_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
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
    final AsyncValue<MobileRegistration?> deviceRegistration = ref.watch(
      mobileRegistrationProvider,
    );

    // A new installation must configure its server and register before
    // accessing any tenant-scoped pharmacy features.
    if (deviceRegistration.isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (deviceRegistration.hasError) {
      return Scaffold(
        appBar: AppBar(title: const Text('Darmaltoon')),
        body: Center(child: Text(deviceRegistration.error.toString())),
      );
    }
    if (deviceRegistration.value == null) {
      final AppLocale locale = AppLocale.fromCode(
        Localizations.localeOf(context).languageCode,
      );
      final bool dari = locale == AppLocale.dari;
      final bool pashto = locale == AppLocale.pashto;
      String tr(String en, String fa, String ps) =>
          pashto ? ps : (dari ? fa : en);

      return Scaffold(
        appBar: AppBar(
          title: const Text('Darmaltoon'),
          actions: <Widget>[
            PopupMenuButton<AppLocale>(
              tooltip: strings.language,
              onSelected: (AppLocale value) {
                widget.localeNotifier.value = value;
              },
              itemBuilder: (BuildContext context) => AppLocale.values
                  .map(
                    (AppLocale value) => PopupMenuItem<AppLocale>(
                      value: value,
                      child: Text(value.label),
                    ),
                  )
                  .toList(),
              icon: const Icon(Icons.language_rounded),
            ),
          ],
        ),
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
            children: <Widget>[
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: <Color>[Color(0xFF075AA7), Color(0xFF0A91BC)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(26),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Container(
                      width: 68,
                      height: 68,
                      padding: const EdgeInsets.all(9),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: SvgPicture.asset(
                        'assets/brand/darmaltoon-mark.svg',
                      ),
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'Darmaltoon  •  درملتون',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 25,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      tr(
                        'Connect your pharmacy to get started',
                        'برای شروع دواخانه خود را وصل کنید',
                        'د پیل لپاره خپل درملتون وصل کړئ',
                      ),
                      style: const TextStyle(color: Colors.white, fontSize: 15),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              _SetupStep(
                number: '01',
                title: tr(
                  'Pharmacy server link',
                  'لینک سرور دواخانه',
                  'د درملتون سرور لینک',
                ),
                subtitle: tr(
                  'Enter the web or LAN address supplied by your pharmacy administrator, then save the connection.',
                  'آدرس ویب یا شبکه داخلی را وارد کرده و اتصال را ذخیره کنید.',
                  'د ویب یا محلي شبکې پته ولیکئ او اړیکه خوندي کړئ.',
                ),
              ),
              const SizedBox(height: 12),
              ConnectionModeCard(
                defaultCloudUri: AppEnvironment.current.apiBaseUri,
              ),
              const SizedBox(height: 24),
              _SetupStep(
                number: '02',
                title: tr(
                  'Register this device',
                  'ثبت این دستگاه',
                  'دا وسیله ثبت کړئ',
                ),
                subtitle: tr(
                  'Use the activation details provided for your pharmacy.',
                  'مشخصات فعال‌سازی دواخانه خود را وارد کنید.',
                  'د خپل درملتون د فعالولو معلومات دننه کړئ.',
                ),
              ),
              const SizedBox(height: 12),
              const MobileRegistrationCard(),
            ],
          ),
        ),
      );
    }

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

class _SetupStep extends StatelessWidget {
  const _SetupStep({
    required this.number,
    required this.title,
    required this.subtitle,
  });

  final String number;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Container(
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(
          color: const Color(0xFFE1EFFC),
          borderRadius: BorderRadius.circular(13),
        ),
        child: Text(
          number,
          style: const TextStyle(
            color: Color(0xFF075AA7),
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              title,
              style: Theme.of(context).textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    ],
  );
}
