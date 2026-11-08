import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/alerts/presentation/operational_alerts_card.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/offline_catalog_panels.dart';
import 'package:businessos_pharmacy/features/catalog/presentation/stock_workspace.dart';
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
import 'package:businessos_pharmacy/features/reports/presentation/reports_workspace.dart';
import 'package:businessos_pharmacy/features/settings/presentation/connection_mode_card.dart';
import 'package:businessos_pharmacy/features/shell/presentation/home_dashboard.dart';
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
            Flexible(
              child: Text(
                'Darmaltoon',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
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
