import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/core/localization/module_strings.dart';
import 'package:businessos_pharmacy/features/alerts/presentation/operational_alerts_card.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/offline_catalog_panels.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/stock_workspace.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_cloud_http_gateway.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_duplicate_preflight.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_registration_catalog_refresh.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_cloud_fallback.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_image_acquisition.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_coordinator.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/domain/picker_medicine_image_source.dart';
import 'package:businessos_pharmacy/features/medicines/domain/platform_medicine_ocr.dart';
import 'package:businessos_pharmacy/features/medicines/presentation/medicine_registration_dialog.dart';
import 'package:businessos_pharmacy/features/medicines/presentation/medicine_scan_flow.dart';
import 'package:businessos_pharmacy/features/pos/presentation/offline_pos_panel.dart';
import 'package:businessos_pharmacy/features/purchasing/presentation/offline_purchasing_panel.dart';
import 'package:businessos_pharmacy/features/registration/presentation/mobile_registration_card.dart';
import 'package:businessos_pharmacy/features/reports/presentation/reports_workspace.dart';
import 'package:businessos_pharmacy/features/settings/presentation/connection_mode_card.dart';
import 'package:businessos_pharmacy/features/shell/presentation/home_dashboard.dart';
import 'package:businessos_pharmacy/features/status/presentation/connectivity_banner.dart';
import 'package:businessos_pharmacy/features/sync/presentation/sync_status_card.dart';
import 'package:businessos_pharmacy/features/suppliers/presentation/suppliers_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;

class AppShell extends ConsumerStatefulWidget {
  const AppShell({required this.localeNotifier, super.key});

  final ValueNotifier<AppLocale> localeNotifier;

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell> {
  int _selectedIndex = 0;
  String? _moreSection;
  ReviewedMedicineScan? _reviewedScan;
  Future<List<String>>? _duplicateLookup;
  bool _registrationApproval = false;

  Future<List<String>> _checkDuplicates(ReviewedMedicineScan draft) async {
    final database = await ref.read(pharmacyDatabaseProvider.future);
    return OfflineCatalogBrowser(database).possibleDuplicateMedicineNames(
      brandName: draft.brandName,
      genericName: draft.genericName,
      barcode: draft.barcode,
    );
  }

  void _openAlerts() {
    setState(() {
      _selectedIndex = 4;
      _moreSection = 'Alerts';
    });
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    final ModuleStrings s = ModuleStrings.of(context);
    final bool canScanMedicines =
        ref
            .watch(mobileRegistrationProvider)
            .asData
            ?.value
            ?.permissions
            .contains('medicines.manage') ==
        true;
    final List<_ShellDestination> destinations = <_ShellDestination>[
      _ShellDestination(icon: Icons.dashboard_outlined, label: strings.home),
      _ShellDestination(icon: Icons.point_of_sale_rounded, label: strings.pos),
      _ShellDestination(
        icon: Icons.medication_outlined,
        label: strings.medicines,
      ),
      _ShellDestination(icon: Icons.bar_chart_rounded, label: strings.reports),
      _ShellDestination(icon: Icons.menu_rounded, label: strings.more),
    ];

    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: <Widget>[
            Icon(Icons.medication_rounded, color: Color(0xFF1557C0)),
            SizedBox(width: 9),
            Flexible(
              child: Text(
                'Darmaltoon',
                maxLines: 2,
                textAlign: TextAlign.center,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
        actions: <Widget>[
          IconButton(
            tooltip: s.moduleAlerts,
            onPressed: _openAlerts,
            icon: const Icon(Icons.notifications_none_rounded),
          ),
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
              HomeDashboard(
                onNavigate: (int index, String? section) => setState(() {
                  _selectedIndex = index;
                  if (section != null) _moreSection = section;
                }),
              )
            else if (_selectedIndex == 1)
              const OfflinePosPanel()
            else if (_selectedIndex == 2)
              const StockWorkspace()
            else if (_selectedIndex == 3)
              const ReportsWorkspace(),
            if (_selectedIndex == 2) ...<Widget>[
              const SizedBox(height: 16),
              ExpansionTile(
                key: const ValueKey<String>('medicine-ocr-entry'),
                initiallyExpanded: true,
                leading: const Icon(Icons.document_scanner_outlined),
                title: Text(s.scanLabel),
                subtitle: Text(s.scanDraftNote),
                children: [
                  MedicineScanFlow(
                    images: MedicineImageAcquisition(
                      PickerMedicineImageSource(),
                    ),
                    scanner: const MedicineScanCoordinator(
                      ocr: PlatformMedicineOcr(),
                    ),
                    cloudFallback: (() {
                      final registration = ref
                          .watch(mobileRegistrationProvider)
                          .asData
                          ?.value;
                      final base = AppEnvironment.current.apiBaseUri;
                      if (registration == null ||
                          registration.accessExpired ||
                          !canScanMedicines ||
                          base == null ||
                          base.scheme != 'https') {
                        return null;
                      }
                      return MedicineCloudFallback(
                        MedicineCloudHttpGateway(
                          endpoint: base.resolve(
                            'api/v1/mobile/medicines/cloud-ocr',
                          ),
                          accessToken: registration.accessToken,
                        ),
                      );
                    })(),
                    onConfirmed: (ReviewedMedicineScan reviewed) {
                      setState(() {
                        _reviewedScan = reviewed;
                        _registrationApproval = false;
                        _duplicateLookup = _checkDuplicates(reviewed);
                      });
                    },
                  ),
                  if (_reviewedScan != null && _duplicateLookup != null)
                    FutureBuilder<List<String>>(
                      future: _duplicateLookup,
                      builder: (context, snapshot) {
                        final ready =
                            snapshot.connectionState == ConnectionState.done &&
                            !snapshot.hasError &&
                            snapshot.hasData &&
                            snapshot.data!.isEmpty &&
                            _registrationApproval &&
                            canScanMedicines;
                        return ListTile(
                          title: Text(s.registerReviewed),
                          subtitle: Text(s.registerNeedsApproval),
                          trailing: const Icon(Icons.add_circle_outline),
                          enabled: ready,
                          onTap: !ready
                              ? null
                              : () async {
                                  final draft = _reviewedScan;
                                  final registration = ref
                                      .read(mobileRegistrationProvider)
                                      .asData
                                      ?.value;
                                  if (draft == null || registration == null) {
                                    return;
                                  }
                                  final handoff =
                                      MedicineRegistrationHandoff.prepare(
                                        reviewed: draft,
                                        duplicateCheckCompleted: true,
                                        possibleDuplicates: snapshot.data!,
                                      );
                                  final createdId = await showDialog<String>(
                                    context: context,
                                    builder: (context) =>
                                        MedicineRegistrationDialog(
                                          handoff: handoff,
                                          registration: registration,
                                        ),
                                  );
                                  if (!context.mounted || createdId == null) {
                                    return;
                                  }
                                  setState(() {
                                    _reviewedScan = null;
                                    _duplicateLookup = null;
                                    _registrationApproval = false;
                                  });
                                  try {
                                    await refreshRegisteredMedicineCatalog(ref);
                                    if (!context.mounted) return;
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text(s.registeredSynced),
                                      ),
                                    );
                                  } catch (error) {
                                    if (!context.mounted) return;
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text(
                                          s.catalogSyncFailed('$error'),
                                        ),
                                      ),
                                    );
                                  }
                                },
                        );
                      },
                    ),
                  if (_reviewedScan != null && _duplicateLookup != null)
                    FutureBuilder<List<String>>(
                      future: _duplicateLookup,
                      builder: (context, snapshot) {
                        if (snapshot.connectionState != ConnectionState.done) {
                          return ListTile(title: Text(s.checkingDuplicates));
                        }
                        if (snapshot.hasError) {
                          return ListTile(
                            title: Text(s.duplicateUnavailable),
                            subtitle: Text(s.doNotRegister),
                          );
                        }
                        final matches = snapshot.data ?? <String>[];
                        return ListTile(
                          title: Text(
                            matches.isEmpty
                                ? s.noLocalMatches
                                : s.possibleDuplicatesCount(matches.length),
                          ),
                          subtitle: Text(
                            matches.isEmpty
                                ? s.serverVerifyNeeded
                                : matches.join(', '),
                          ),
                        );
                      },
                    ),
                  if (_reviewedScan != null)
                    CheckboxListTile(
                      title: Text(s.approveHandoff),
                      subtitle: Text(s.approveHandoffBody),
                      value: _registrationApproval,
                      onChanged: (value) {
                        setState(() {
                          _registrationApproval = value ?? false;
                        });
                      },
                    ),
                  if (_reviewedScan != null && _duplicateLookup != null)
                    FutureBuilder<List<String>>(
                      future: _duplicateLookup,
                      builder: (context, snapshot) {
                        final ready =
                            snapshot.connectionState == ConnectionState.done &&
                            !snapshot.hasError &&
                            snapshot.hasData &&
                            snapshot.data!.isEmpty &&
                            _registrationApproval;
                        return ListTile(
                          title: Text(s.prepareHandoff),
                          subtitle: Text(s.prepareHandoffBody),
                          trailing: IconButton(
                            tooltip: s.copyHandoffTooltip,
                            icon: const Icon(Icons.assignment_outlined),
                            onPressed: !ready || !canScanMedicines
                                ? null
                                : () async {
                                    final draft = _reviewedScan;
                                    if (draft == null) return;
                                    final handoff =
                                        MedicineRegistrationHandoff.prepare(
                                          reviewed: draft,
                                          duplicateCheckCompleted: true,
                                          possibleDuplicates: snapshot.data!,
                                        );
                                    final registration = ref
                                        .read(mobileRegistrationProvider)
                                        .asData
                                        ?.value;
                                    if (registration == null) return;
                                    final client = http.Client();
                                    try {
                                      final matches =
                                          await MedicineDuplicatePreflight(
                                            environment: AppEnvironment.current,
                                            client: client,
                                          ).check(
                                            registration: registration,
                                            handoff: handoff,
                                          );
                                      if (matches.isNotEmpty) {
                                        throw StateError(
                                          s.serverDuplicates(
                                            matches.join(', '),
                                          ),
                                        );
                                      }
                                    } catch (error) {
                                      if (!context.mounted) return;
                                      ScaffoldMessenger.of(context)
                                          .showSnackBar(
                                            SnackBar(
                                              content: Text(
                                                s.handoffBlocked('$error'),
                                              ),
                                            ),
                                          );
                                      return;
                                    } finally {
                                      client.close();
                                    }
                                    if (!context.mounted ||
                                        !_registrationApproval) {
                                      return;
                                    }
                                    await Clipboard.setData(
                                      ClipboardData(
                                        text: [
                                          'Brand: ${handoff.brandName}',
                                          if (handoff.genericName != null)
                                            'Generic: ${handoff.genericName}',
                                          if (handoff.strength != null)
                                            'Strength: ${handoff.strength}',
                                          if (handoff.dosageForm != null)
                                            'Form: ${handoff.dosageForm}',
                                          if (handoff.barcode != null)
                                            'Barcode: ${handoff.barcode}',
                                        ].join('\\n'),
                                      ),
                                    );
                                    if (!context.mounted) return;
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(content: Text(s.handoffCopied)),
                                    );
                                  },
                          ),
                        );
                      },
                    ),
                  if (_reviewedScan != null)
                    ListTile(
                      title: Text(s.registrationDisabled),
                      subtitle: Text(s.registrationDisabledBody),
                      trailing: IconButton(
                        tooltip: s.recheckTooltip,
                        icon: const Icon(Icons.refresh),
                        onPressed: () {
                          final draft = _reviewedScan;
                          if (draft == null) return;
                          setState(() {
                            _registrationApproval = false;
                            _duplicateLookup = _checkDuplicates(draft);
                          });
                        },
                      ),
                    ),
                  if (_reviewedScan != null)
                    ListTile(
                      title: Text(_reviewedScan!.brandName),
                      subtitle: Text(
                        [
                          if (_reviewedScan!.genericName != null)
                            'Generic: ${_reviewedScan!.genericName}',
                          if (_reviewedScan!.strength != null)
                            'Strength: ${_reviewedScan!.strength}',
                          if (_reviewedScan!.dosageForm != null)
                            'Form: ${_reviewedScan!.dosageForm}',
                          if (_reviewedScan!.barcode != null)
                            'Barcode: ${_reviewedScan!.barcode}',
                          s.reviewedDraftOnly,
                        ].join('\n'),
                      ),
                      trailing: Wrap(
                        spacing: 0,
                        children: [
                          IconButton(
                            tooltip: s.copyReviewedTooltip,
                            icon: const Icon(Icons.copy_outlined),
                            onPressed: () async {
                              final draft = _reviewedScan;
                              if (draft == null) return;
                              final fields = [
                                'Brand: ${draft.brandName}',
                                if (draft.genericName != null)
                                  'Generic: ${draft.genericName}',
                                if (draft.strength != null)
                                  'Strength: ${draft.strength}',
                                if (draft.dosageForm != null)
                                  'Form: ${draft.dosageForm}',
                                if (draft.barcode != null)
                                  'Barcode: ${draft.barcode}',
                              ];
                              await Clipboard.setData(
                                ClipboardData(text: fields.join('\n')),
                              );
                              if (!context.mounted) return;
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text(s.reviewedCopied)),
                              );
                            },
                          ),
                          IconButton(
                            tooltip: s.clearDraftTooltip,
                            icon: const Icon(Icons.delete_outline),
                            onPressed: () => setState(() {
                              _reviewedScan = null;
                              _registrationApproval = false;
                              _duplicateLookup = null;
                            }),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              const OperationalAlertsCard(),
            ],
            if (_selectedIndex == 4) ...<Widget>[
              Text(
                strings.more,
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 12),
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 3,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 1.05,
                children: _moreModules(s)
                    .map(
                      (_MoreModule module) => _ModuleTile(
                        module: module,
                        selected:
                            module.section != null &&
                            _moreSection == module.section,
                        onTap: !module.available
                            ? null
                            : () {
                                final int? tab = module.tab;
                                setState(() {
                                  if (tab != null) {
                                    _selectedIndex = tab;
                                  } else {
                                    _moreSection = module.section;
                                  }
                                });
                              },
                      ),
                    )
                    .toList(growable: false),
              ),
              const SizedBox(height: 16),
              if (_moreSection == 'Customers') const OfflineCustomersPanel(),
              if (_moreSection == 'Purchases') const OfflinePurchasingPanel(),
              if (_moreSection == 'Suppliers') const SuppliersPanel(),
              if (_moreSection == 'Alerts') const OperationalAlertsCard(),
              if (_moreSection == 'Sync') const SyncStatusCard(),
              if (_moreSection == 'Settings') ...<Widget>[
                const MobileRegistrationCard(),
                const SizedBox(height: 16),
                ConnectionModeCard(
                  defaultCloudUri: AppEnvironment.current.apiBaseUri,
                ),
              ],
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
                selectedIcon: Icon(
                  destination.icon,
                  color: Theme.of(context).colorScheme.primary,
                ),
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

/// The 12 modules from the approved design. Modules without a screen yet are
/// shown as "Soon" and cannot be tapped.
List<_MoreModule> _moreModules(ModuleStrings s) => <_MoreModule>[
  _MoreModule(label: s.moduleSales, icon: Icons.point_of_sale_rounded, tab: 1),
  _MoreModule(label: s.moduleMedicines, icon: Icons.medication_rounded, tab: 2),
  _MoreModule(
    label: s.moduleInventory,
    icon: Icons.inventory_2_rounded,
    tab: 2,
  ),
  _MoreModule(
    label: s.modulePurchases,
    icon: Icons.shopping_bag_outlined,
    section: 'Purchases',
  ),
  _MoreModule(
    label: s.moduleCustomers,
    icon: Icons.people_alt_rounded,
    section: 'Customers',
  ),
  _MoreModule(
    label: s.moduleSuppliers,
    icon: Icons.storefront_outlined,
    section: 'Suppliers',
  ),
  _MoreModule(label: s.moduleReports, icon: Icons.bar_chart_rounded, tab: 3),
  _MoreModule(label: s.moduleFinance, icon: Icons.account_balance_outlined),
  _MoreModule(label: s.moduleTransfers, icon: Icons.swap_horiz_rounded),
  _MoreModule(
    label: s.moduleAlerts,
    icon: Icons.notifications_active_outlined,
    section: 'Alerts',
  ),
  _MoreModule(label: s.moduleSync, icon: Icons.sync_rounded, section: 'Sync'),
  _MoreModule(
    label: s.moduleSettings,
    icon: Icons.settings_outlined,
    section: 'Settings',
  ),
];

class _MoreModule {
  const _MoreModule({
    required this.label,
    required this.icon,
    this.tab,
    this.section,
  });

  final String label;
  final IconData icon;
  final int? tab;
  final String? section;

  bool get available => tab != null || section != null;
}

class _ModuleTile extends StatelessWidget {
  const _ModuleTile({
    required this.module,
    required this.selected,
    required this.onTap,
  });

  final _MoreModule module;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    final ColorScheme colors = Theme.of(context).colorScheme;
    final Color foreground = module.available
        ? const Color(0xFF1557C0)
        : Colors.blueGrey;
    return Material(
      color: selected ? colors.primaryContainer : colors.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              Icon(module.icon, color: foreground, size: 26),
              const SizedBox(height: 6),
              Text(
                module.label,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: module.available ? colors.onSurface : Colors.blueGrey,
                ),
              ),
              if (!module.available)
                Text(
                  s.soon,
                  style: const TextStyle(fontSize: 10, color: Colors.blueGrey),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
