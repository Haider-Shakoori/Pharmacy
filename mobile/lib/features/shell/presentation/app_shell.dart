import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/alerts/presentation/operational_alerts_card.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/offline_catalog_panels.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_duplicate_preflight.dart';
import 'package:businessos_pharmacy/features/medicines/data/medicine_registration_catalog_refresh.dart';
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
import 'package:businessos_pharmacy/features/reports/data/offline_sales_summary.dart';
import 'package:businessos_pharmacy/features/settings/presentation/connection_mode_card.dart';
import 'package:businessos_pharmacy/features/status/presentation/connectivity_banner.dart';
import 'package:businessos_pharmacy/features/sync/presentation/sync_status_card.dart';
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
  String _moreSection = 'Customers';
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

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
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
            Text('Darmaltoon', style: TextStyle(fontWeight: FontWeight.w800)),
          ],
        ),
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
              _HomePanel(
                onNavigate: (int index, String? section) => setState(() {
                  _selectedIndex = index;
                  if (section != null) _moreSection = section;
                }),
              )
            else if (_selectedIndex == 1)
              const OfflinePosPanel()
            else if (_selectedIndex == 2)
              const OfflineStockPanel()
            else if (_selectedIndex == 3)
              const _ReportsPanel(),
            if (_selectedIndex == 2) ...<Widget>[
              const SizedBox(height: 16),
              if (canScanMedicines)
                ExpansionTile(
                  title: const Text('Scan medicine label'),
                  subtitle: const Text(
                    'Offline draft — pharmacist review required',
                  ),
                  children: [
                    MedicineScanFlow(
                      images: MedicineImageAcquisition(
                        PickerMedicineImageSource(),
                      ),
                      scanner: const MedicineScanCoordinator(
                        ocr: PlatformMedicineOcr(),
                      ),
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
                              snapshot.connectionState ==
                                  ConnectionState.done &&
                              !snapshot.hasError &&
                              snapshot.hasData &&
                              snapshot.data!.isEmpty &&
                              _registrationApproval &&
                              canScanMedicines;
                          return ListTile(
                            title: const Text('Register reviewed medicine'),
                            subtitle: const Text(
                              'Online registration requires pharmacist approval.',
                            ),
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
                                      await refreshRegisteredMedicineCatalog(
                                        ref,
                                      );
                                      if (!context.mounted) return;
                                      ScaffoldMessenger.of(context)
                                          .showSnackBar(
                                            const SnackBar(
                                              content: Text(
                                                'Medicine registered and catalog synchronized.',
                                              ),
                                            ),
                                          );
                                    } catch (error) {
                                      if (!context.mounted) return;
                                      ScaffoldMessenger.of(
                                        context,
                                      ).showSnackBar(
                                        SnackBar(
                                          content: Text(
                                            'Medicine saved online, but catalog sync failed: $error. Use Sync Now to retry.',
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
                          if (snapshot.connectionState !=
                              ConnectionState.done) {
                            return const ListTile(
                              title: Text(
                                'Checking local catalog for duplicates...',
                              ),
                            );
                          }
                          if (snapshot.hasError) {
                            return const ListTile(
                              title: Text('Duplicate check unavailable'),
                              subtitle: Text(
                                'Do not register until catalog duplicates are checked.',
                              ),
                            );
                          }
                          final matches = snapshot.data ?? <String>[];
                          return ListTile(
                            title: Text(
                              matches.isEmpty
                                  ? 'No matches in locally synced catalog'
                                  : 'Possible duplicates: ${matches.length}',
                            ),
                            subtitle: Text(
                              matches.isEmpty
                                  ? 'Server-side verification is still required.'
                                  : matches.join(', '),
                            ),
                          );
                        },
                      ),
                    if (_reviewedScan != null)
                      CheckboxListTile(
                        title: const Text('Approve registration handoff'),
                        subtitle: const Text(
                          'I have checked the reviewed details. This does not authorize saving to the server.',
                        ),
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
                              snapshot.connectionState ==
                                  ConnectionState.done &&
                              !snapshot.hasError &&
                              snapshot.hasData &&
                              snapshot.data!.isEmpty &&
                              _registrationApproval;
                          return ListTile(
                            title: const Text('Prepare registration handoff'),
                            subtitle: const Text(
                              'Copies verified draft fields only. No server submission.',
                            ),
                            trailing: IconButton(
                              tooltip: 'Copy registration handoff',
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
                                              environment:
                                                  AppEnvironment.current,
                                              client: client,
                                            ).check(
                                              registration: registration,
                                              handoff: handoff,
                                            );
                                        if (matches.isNotEmpty) {
                                          throw StateError(
                                            'Possible server duplicates: ${matches.join(', ')}',
                                          );
                                        }
                                      } catch (error) {
                                        if (!context.mounted) return;
                                        ScaffoldMessenger.of(context)
                                            .showSnackBar(
                                              SnackBar(
                                                content: Text(
                                                  'Handoff blocked: $error',
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
                                      ScaffoldMessenger.of(context)
                                          .showSnackBar(
                                            const SnackBar(
                                              content: Text(
                                                'Handoff copied. Server validation and pharmacist registration are still required.',
                                              ),
                                            ),
                                          );
                                    },
                            ),
                          );
                        },
                      ),
                    if (_reviewedScan != null)
                      ListTile(
                        title: const Text(
                          'Medicine registration is not enabled',
                        ),
                        subtitle: const Text(
                          'A pharmacist must verify the current server catalog '
                          'and resolve possible duplicates before saving. '
                          'This scan remains a draft and does not change stock.',
                        ),
                        trailing: IconButton(
                          tooltip: 'Recheck local catalog',
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
                            'Reviewed draft only. No medicine or stock saved.',
                          ].join('\n'),
                        ),
                        trailing: Wrap(
                          spacing: 0,
                          children: [
                            IconButton(
                              tooltip: 'Copy reviewed medicine details',
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
                                  const SnackBar(
                                    content: Text(
                                      'Reviewed details copied. Register medicine manually; nothing was saved.',
                                    ),
                                  ),
                                );
                              },
                            ),
                            IconButton(
                              tooltip: 'Clear reviewed draft',
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
              Text('More', style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children:
                    <String>[
                          'Customers',
                          'Purchases',
                          'Alerts',
                          'Sync',
                          'Settings',
                        ]
                        .map(
                          (section) => ChoiceChip(
                            label: Text(section),
                            selected: _moreSection == section,
                            onSelected: (_) =>
                                setState(() => _moreSection = section),
                          ),
                        )
                        .toList(),
              ),
              const SizedBox(height: 16),
              if (_moreSection == 'Customers') const OfflineCustomersPanel(),
              if (_moreSection == 'Purchases') const OfflinePurchasingPanel(),
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

class _HomePanel extends ConsumerStatefulWidget {
  const _HomePanel({required this.onNavigate});
  final void Function(int, String?) onNavigate;

  @override
  ConsumerState<_HomePanel> createState() => _HomePanelState();
}

class _HomePanelState extends ConsumerState<_HomePanel> {
  String _moduleQuery = '';
  final TextEditingController _moduleSearchController = TextEditingController();
  int _summaryRefreshVersion = 0;

  @override
  void dispose() {
    _moduleSearchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final strings = AppStrings.of(context);
    final registration = ref.watch(mobileRegistrationProvider);
    final database = registration.asData?.value == null
        ? null
        : ref.watch(pharmacyDatabaseProvider);
    final now = DateTime.now();
    final date =
        '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    final entries =
        <({IconData icon, String title, String detail, int tab, Color color})>[
          (
            icon: Icons.point_of_sale_rounded,
            title: 'POS (Sales)',
            detail: 'Offline checkout',
            tab: 1,
            color: const Color(0xFFDAF5E6),
          ),
          (
            icon: Icons.medication_rounded,
            title: 'Medicines',
            detail: 'Catalog & stock',
            tab: 2,
            color: const Color(0xFFDDEAFF),
          ),
          (
            icon: Icons.inventory_2_rounded,
            title: 'Inventory',
            detail: 'Batches & expiry',
            tab: 2,
            color: const Color(0xFFE8E1FF),
          ),
          (
            icon: Icons.shopping_bag_outlined,
            title: 'Purchases',
            detail: 'Orders & receiving',
            tab: 4,
            color: const Color(0xFFE9EDF4),
          ),
          (
            icon: Icons.people_alt_rounded,
            title: 'Customers',
            detail: 'Accounts',
            tab: 4,
            color: const Color(0xFFDDEAFF),
          ),
          (
            icon: Icons.bar_chart_rounded,
            title: 'Reports',
            detail: 'Business insights',
            tab: 3,
            color: const Color(0xFFE8E1FF),
          ),
          (
            icon: Icons.notifications_active_outlined,
            title: 'Alerts',
            detail: 'Stock & expiry',
            tab: 4,
            color: const Color(0xFFFFE9D6),
          ),
          (
            icon: Icons.sync_rounded,
            title: 'Sync',
            detail: 'Offline-first',
            tab: 4,
            color: const Color(0xFFDAF5E6),
          ),
          (
            icon: Icons.settings_outlined,
            title: 'Settings',
            detail: 'Device & access',
            tab: 4,
            color: const Color(0xFFE9EDF4),
          ),
        ];
    final visibleEntries = entries
        .where((entry) {
          final query = _moduleQuery.trim().toLowerCase();
          return query.isEmpty ||
              entry.title.toLowerCase().contains(query) ||
              entry.detail.toLowerCase().contains(query) ||
              strings
                  .dashboardModuleTitle(entry.title)
                  .toLowerCase()
                  .contains(query) ||
              strings
                  .dashboardModuleDetail(entry.detail)
                  .toLowerCase()
                  .contains(query);
        })
        .toList(growable: false);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          strings.dashboardTitle,
          style: text.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 5),
        Text(
          strings.dashboardSubtitle,
          style: text.bodyMedium?.copyWith(color: Colors.blueGrey),
        ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: <Color>[Color(0xFF0C3274), Color(0xFF1768D1)],
            ),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            children: <Widget>[
              const Icon(
                Icons.storefront_rounded,
                size: 36,
                color: Colors.white,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      strings.dashboardWorkspace,
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 18,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      strings.dashboardWorkspaceSubtitle,
                      style: TextStyle(color: Colors.white70),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                strings.dashboardLocalOverview,
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
              ),
            ),
            IconButton(
              key: const ValueKey('dashboard-refresh'),
              tooltip: strings.dashboardRefresh,
              onPressed: () => setState(() => _summaryRefreshVersion++),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        if (database != null)
          database.when(
            loading: () => const LinearProgressIndicator(),
            error: (Object error, StackTrace trace) =>
                const Text('Local sales summary unavailable'),
            data: (db) => FutureBuilder<OfflineSalesSummary>(
              key: ValueKey('${db.hashCode}-$date-$_summaryRefreshVersion'),
              future: OfflineSalesSummaryRepository(db).forBusinessDate(date),
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return const Text('Unable to load today\'s local sales');
                }
                if (!snapshot.hasData) {
                  return const LinearProgressIndicator();
                }
                final summary = snapshot.data!;
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          strings.dashboardToday,
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 18,
                          runSpacing: 12,
                          children: <Widget>[
                            _HomeStat(
                              label: strings.dashboardSales,
                              value: '${summary.salesCount}',
                            ),
                            _HomeStat(
                              label: strings.dashboardRevenue,
                              value: '${summary.total.compact} AFN',
                            ),
                            _HomeStat(
                              label: strings.dashboardPendingSync,
                              value: '${summary.pendingSync}',
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        const SizedBox(height: 12),
        if (database != null)
          database.when(
            loading: () => const LinearProgressIndicator(),
            error: (Object error, StackTrace trace) =>
                const Text('Inventory alerts unavailable'),
            data: (db) => FutureBuilder<InventoryOverview>(
              key: ValueKey(
                'inventory-alerts-${db.hashCode}-$date-$_summaryRefreshVersion',
              ),
              future: OfflineCatalogBrowser(db).inventoryOverview(now: now),
              builder:
                  (
                    BuildContext context,
                    AsyncSnapshot<InventoryOverview> snapshot,
                  ) {
                    if (snapshot.hasError) {
                      return const Text('Unable to load inventory alerts');
                    }
                    if (!snapshot.hasData) {
                      return const LinearProgressIndicator();
                    }
                    final InventoryOverview overview = snapshot.data!;
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Row(
                              children: <Widget>[
                                Expanded(
                                  child: Text(
                                    strings.dashboardInventoryAlerts,
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                                TextButton(
                                  onPressed: () => widget.onNavigate(2, null),
                                  child: Text(strings.dashboardViewStock),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 18,
                              runSpacing: 12,
                              children: <Widget>[
                                _HomeStat(
                                  label: strings.dashboardOutOfStock,
                                  value: '${overview.outOfStock}',
                                ),
                                _HomeStat(
                                  label: strings.dashboardExpiredBatches,
                                  value: '${overview.expiredBatches}',
                                ),
                                _HomeStat(
                                  label: strings.dashboardExpiringSoon,
                                  value: '${overview.expiringBatches}',
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              strings.dashboardMedicineCount(
                                overview.medicines,
                              ),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                    );
                  },
            ),
          ),
        const SizedBox(height: 20),
        Text(
          strings.dashboardQuickAccess,
          style: text.titleLarge?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 12),
        TextField(
          key: const ValueKey('dashboard-module-search'),
          decoration: InputDecoration(
            prefixIcon: const Icon(Icons.search),
            hintText: strings.dashboardSearchHint,
            border: const OutlineInputBorder(),
            suffixIcon: _moduleQuery.isEmpty
                ? null
                : IconButton(
                    key: const ValueKey('dashboard-clear-search'),
                    tooltip: strings.dashboardClearSearch,
                    onPressed: () {
                      FocusScope.of(context).unfocus();
                      _moduleSearchController.clear();
                      setState(() => _moduleQuery = '');
                    },
                    icon: const Icon(Icons.close_rounded),
                  ),
          ),
          controller: _moduleSearchController,
          onChanged: (value) => setState(() => _moduleQuery = value),
        ),
        const SizedBox(height: 12),
        Semantics(
          liveRegion: true,
          child: Text(
            strings.dashboardResultCount(
              visibleEntries.length,
              filtered: _moduleQuery.trim().isNotEmpty,
            ),
            key: const ValueKey('dashboard-search-result-count'),
            style: text.bodySmall?.copyWith(color: Colors.blueGrey),
          ),
        ),
        const SizedBox(height: 8),
        if (visibleEntries.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 20),
            child: Text(strings.dashboardNoMatches),
          ),
        LayoutBuilder(
          builder: (BuildContext context, BoxConstraints constraints) {
            final columns = constraints.maxWidth >= 720
                ? 4
                : constraints.maxWidth >= 440
                ? 3
                : 2;
            return GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: visibleEntries.length,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: columns,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.48,
              ),
              itemBuilder: (BuildContext context, int index) {
                final entry = visibleEntries[index];
                return Material(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () => widget.onNavigate(
                      entry.tab,
                      entry.tab == 4
                          ? (entry.title == 'Purchases'
                                ? 'Purchases'
                                : entry.title == 'Alerts'
                                ? 'Alerts'
                                : entry.title == 'Sync'
                                ? 'Sync'
                                : entry.title == 'Settings'
                                ? 'Settings'
                                : 'Customers')
                          : null,
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: <Widget>[
                          Container(
                            padding: const EdgeInsets.all(9),
                            decoration: BoxDecoration(
                              color: entry.color,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Icon(
                              entry.icon,
                              color: const Color(0xFF1557C0),
                              size: 23,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            strings.dashboardModuleTitle(entry.title),
                            maxLines: 1,
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          Text(
                            strings.dashboardModuleDetail(entry.detail),
                            maxLines: 1,
                            style: const TextStyle(
                              fontSize: 11,
                              color: Colors.blueGrey,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            );
          },
        ),
      ],
    );
  }
}

class _ReportsPanel extends ConsumerStatefulWidget {
  const _ReportsPanel();

  @override
  ConsumerState<_ReportsPanel> createState() => _ReportsPanelState();
}

class _ReportsPanelState extends ConsumerState<_ReportsPanel> {
  int _days = 1;
  int _refreshVersion = 0;

  @override
  Widget build(BuildContext context) {
    final registration = ref.watch(mobileRegistrationProvider);
    final database = registration.value == null
        ? null
        : ref.watch(pharmacyDatabaseProvider);
    final now = DateTime.now();
    String formatDate(DateTime value) =>
        '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
    final date = formatDate(now);
    final startDate = formatDate(
      DateTime(now.year, now.month, now.day - _days + 1),
    );
    final reports = <({IconData icon, String title, String detail})>[
      (
        icon: Icons.show_chart,
        title: 'Sales Report',
        detail: 'Daily, weekly and monthly sales',
      ),
      (
        icon: Icons.trending_up,
        title: 'Profit & Loss',
        detail: 'Revenue, cost and expenses',
      ),
      (
        icon: Icons.inventory_2_outlined,
        title: 'Inventory Report',
        detail: 'Stock levels and valuation',
      ),
      (
        icon: Icons.alarm_rounded,
        title: 'Expiry Report',
        detail: 'Batches nearing expiry',
      ),
      (
        icon: Icons.local_shipping_outlined,
        title: 'Purchase Report',
        detail: 'Supplier purchase history',
      ),
      (
        icon: Icons.people_outline,
        title: 'Customer Report',
        detail: 'Balances and sales history',
      ),
      (
        icon: Icons.account_balance_wallet_outlined,
        title: 'Financial Report',
        detail: 'Receivables and payables',
      ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Reports',
          style: Theme.of(context).textTheme.headlineMedium
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        const Text(
          'Local device totals are separate from company-wide reporting.',
        ),
        const SizedBox(height: 12),
        Row(
          children: <Widget>[
            const Expanded(child: Text('Sales period')),
            IconButton(
              tooltip: 'Refresh sales totals',
              onPressed: () => setState(() => _refreshVersion++),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        SegmentedButton<int>(
          segments: const <ButtonSegment<int>>[
            ButtonSegment<int>(value: 1, label: Text('Today')),
            ButtonSegment<int>(value: 7, label: Text('7 days')),
            ButtonSegment<int>(value: 30, label: Text('30 days')),
          ],
          selected: <int>{_days},
          onSelectionChanged: (Set<int> selection) =>
              setState(() => _days = selection.first),
        ),
        const SizedBox(height: 12),
        if (database != null)
          database.when(
            loading: () => const LinearProgressIndicator(),
            error: (Object error, StackTrace trace) =>
                const Text('Local reports unavailable'),
            data: (db) => FutureBuilder<OfflineSalesSummary>(
              key: ValueKey('${db.hashCode}-$startDate-$date-$_refreshVersion'),
              future: OfflineSalesSummaryRepository(db)
                  .forBusinessDateRange(startDate, date),
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return const Text('Unable to read local sales');
                }
                if (!snapshot.hasData) return const LinearProgressIndicator();
                final summary = snapshot.data!;
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Last $_days day(s) on this device',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 18,
                          runSpacing: 12,
                          children: <Widget>[
                            _HomeStat(
                              label: 'Sales',
                              value: '${summary.salesCount}',
                            ),
                            _HomeStat(
                              label: 'Revenue',
                              value: '${summary.total.compact} AFN',
                            ),
                            _HomeStat(
                              label: 'Pending sync',
                              value: '${summary.pendingSync}',
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        const SizedBox(height: 14),
        if (database != null && _days > 1)
          database.when(
            loading: () => const LinearProgressIndicator(),
            error: (Object error, StackTrace trace) =>
                const Text('Sales trend unavailable'),
            data: (db) => FutureBuilder<List<OfflineDailySales>>(
              key: ValueKey(
                'trend-${db.hashCode}-$startDate-$date-$_refreshVersion',
              ),
              future: OfflineSalesSummaryRepository(db)
                  .dailyTrend(startDate, date),
              builder:
                  (
                    BuildContext context,
                    AsyncSnapshot<List<OfflineDailySales>> snapshot,
                  ) {
                    if (snapshot.hasError) {
                      return const Text('Unable to load daily sales trend');
                    }
                    if (!snapshot.hasData) {
                      return const LinearProgressIndicator();
                    }
                    final List<OfflineDailySales> days = snapshot.data!;
                    final int peak = days.fold<int>(
                      0,
                      (int max, OfflineDailySales day) =>
                          day.salesCount > max ? day.salesCount : max,
                    );
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            const Text(
                              'Daily sales count — this device',
                              style: TextStyle(fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 12),
                            ...days.map(
                              (OfflineDailySales day) => Padding(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 3,
                                ),
                                child: Row(
                                  children: <Widget>[
                                    SizedBox(
                                      width: 48,
                                      child: Text(
                                        day.businessDate.substring(5),
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall,
                                      ),
                                    ),
                                    Expanded(
                                      child: LinearProgressIndicator(
                                        value: peak == 0
                                            ? 0
                                            : day.salesCount / peak,
                                        minHeight: 9,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                    ),
                                    SizedBox(
                                      width: 32,
                                      child: Text(
                                        '${day.salesCount}',
                                        textAlign: TextAlign.end,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
            ),
          ),
        const SizedBox(height: 14),
        ...reports.map(
          (report) => Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Card(
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: const Color(0xFFDDEAFF),
                  child: Icon(report.icon, color: const Color(0xFF1557C0)),
                ),
                title: Text(
                  report.title,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                subtitle: Text(report.detail),
                trailing: const Icon(Icons.lock_clock_outlined, size: 19),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _HomeStat extends StatelessWidget {
  const _HomeStat({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Text(
        value,
        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
      ),
      Text(label, style: Theme.of(context).textTheme.bodySmall),
    ],
  );
}
