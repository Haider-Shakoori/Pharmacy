import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/localization/module_strings.dart';
import 'package:businessos_pharmacy/features/catalog/data/mobile_stock_location_api.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;

/// Compact, read-only stock workspace: Stock, Batches, Expiry and Storage.
///
/// Uses the same tenant-scoped database and permission rules as the previous
/// stock panel. Nothing here writes stock.
class StockWorkspace extends ConsumerWidget {
  const StockWorkspace({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ModuleStrings s = ModuleStrings.of(context);
    final AsyncValue<MobileRegistration?> registration = ref.watch(
      mobileRegistrationProvider,
    );
    return registration.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object error, StackTrace trace) => Text(error.toString()),
      data: (MobileRegistration? user) {
        if (user == null) {
          return _Notice(message: s.registerToViewStock);
        }
        final bool permitted =
            user.permissions.contains('pos.sell') ||
            user.permissions.contains('inventory.manage');
        if (!permitted) {
          return _Notice(message: s.stockNotPermitted);
        }
        return ref
            .watch(pharmacyDatabaseProvider)
            .when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (Object error, StackTrace trace) => Text(error.toString()),
              data: (PharmacyDatabase db) =>
                  _StockBody(database: db, registration: user),
            );
      },
    );
  }
}

class _StockBody extends StatefulWidget {
  const _StockBody({required this.database, required this.registration});

  final PharmacyDatabase database;
  final MobileRegistration registration;

  @override
  State<_StockBody> createState() => _StockBodyState();
}

class _StockBodyState extends State<_StockBody> {
  final TextEditingController _search = TextEditingController();
  List<String> _locations = <String>[];
  List<MobileStockLocation>? _serverLocations;
  String? _serverLocationsError;
  bool _serverLocationsLoading = false;
  bool _creatingLocation = false;
  List<StockMedicineSnapshot> _medicines = <StockMedicineSnapshot>[];
  List<StockMedicineSnapshot> _expiring = <StockMedicineSnapshot>[];
  InventoryOverview? _overview;
  String? _location;
  String? _error;
  bool _loading = true;
  int _page = 0;
  int _tab = 0; // 0 = Stock, 1 = Batches, 2 = Expiry, 3 = Storage
  int _generation = 0;
  int _serverGeneration = 0;

  @override
  void initState() {
    super.initState();
    _load();
    _loadServerLocations();
  }

  Future<void> _loadServerLocations() async {
    final int generation = ++_serverGeneration;
    final MobileRegistration registration = widget.registration;
    setState(() {
      _serverLocationsLoading = true;
      _serverLocationsError = null;
    });
    final http.Client client = http.Client();
    try {
      final List<MobileStockLocation> locations = await MobileStockLocationApi(
        environment: AppEnvironment.current,
        client: client,
      ).list(registration);
      if (!mounted || generation != _serverGeneration) return;
      setState(() => _serverLocations = locations);
    } on Object catch (error) {
      if (!mounted || generation != _serverGeneration) return;
      setState(() => _serverLocationsError = error.toString());
    } finally {
      client.close();
      if (mounted && generation == _serverGeneration) {
        setState(() => _serverLocationsLoading = false);
      }
    }
  }

  Future<void> _createLocation() async {
    if (_creatingLocation ||
        !widget.registration.permissions.contains('inventory.manage') ||
        _serverLocationsLoading) {
      return;
    }
    final MobileRegistration registration = widget.registration;
    final int generation = _serverGeneration;
    final ModuleStrings strings = ModuleStrings.of(context);
    final TextEditingController code = TextEditingController();
    final TextEditingController name = TextEditingController();
    String kind = 'store';
    final http.Client branchClient = http.Client();
    late final List<MobileStockBranch> branches;
    try {
      branches = await MobileStockLocationApi(
        environment: AppEnvironment.current,
        client: branchClient,
      ).branches(registration);
    } on Object catch (error) {
      if (mounted && generation == _serverGeneration) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
      return;
    } finally {
      branchClient.close();
    }
    if (!mounted || generation != _serverGeneration || branches.isEmpty) {
      if (mounted && generation == _serverGeneration) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No active branch is available.')),
        );
      }
      return;
    }
    String branchId = branches.first.id;
    try {
      final bool? confirmed = await showDialog<bool>(
        context: context,
        builder: (BuildContext dialogContext) => StatefulBuilder(
          builder: (BuildContext context, StateSetter updateDialog) =>
              AlertDialog(
                title: Text(strings.addStorageLocation),
                content: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      DropdownButtonFormField<String>(
                        initialValue: branchId,
                        decoration: InputDecoration(
                          labelText: strings.storageBranch,
                        ),
                        items: branches
                            .map(
                              (MobileStockBranch branch) =>
                                  DropdownMenuItem<String>(
                                    value: branch.id,
                                    child: Text(branch.name),
                                  ),
                            )
                            .toList(),
                        onChanged: (String? value) {
                          if (value != null) {
                            updateDialog(() => branchId = value);
                          }
                        },
                      ),
                      TextField(
                        controller: code,
                        maxLength: 60,
                        decoration: InputDecoration(
                          labelText: strings.storageCode,
                        ),
                      ),
                      TextField(
                        controller: name,
                        maxLength: 160,
                        decoration: InputDecoration(
                          labelText: strings.storageName,
                        ),
                      ),
                      DropdownButtonFormField<String>(
                        initialValue: kind,
                        decoration: InputDecoration(
                          labelText: strings.storageType,
                        ),
                        items:
                            const <String>[
                                  'store',
                                  'warehouse',
                                  'shelf',
                                  'quarantine',
                                  'other',
                                ]
                                .map(
                                  (String value) => DropdownMenuItem<String>(
                                    value: value,
                                    child: Text(value),
                                  ),
                                )
                                .toList(),
                        onChanged: (String? value) {
                          if (value != null) updateDialog(() => kind = value);
                        },
                      ),
                    ],
                  ),
                ),
                actions: <Widget>[
                  TextButton(
                    onPressed: () => Navigator.pop(dialogContext, false),
                    child: Text(strings.storageCancel),
                  ),
                  FilledButton(
                    onPressed: () {
                      if (code.text.trim().isNotEmpty &&
                          name.text.trim().isNotEmpty) {
                        Navigator.pop(dialogContext, true);
                      }
                    },
                    child: Text(strings.storageCreate),
                  ),
                ],
              ),
        ),
      );
      if (confirmed != true || !mounted || generation != _serverGeneration) {
        return;
      }
      setState(() => _creatingLocation = true);
      final http.Client client = http.Client();
      try {
        await MobileStockLocationApi(
          environment: AppEnvironment.current,
          client: client,
        ).create(
          registration: registration,
          branchId: branchId,
          code: code.text,
          name: name.text,
          kind: kind,
        );
        if (mounted && generation == _serverGeneration) {
          await _loadServerLocations();
        }
      } on Object catch (error) {
        if (mounted && generation == _serverGeneration) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(error.toString())));
        }
      } finally {
        client.close();
        if (mounted && generation == _serverGeneration) {
          setState(() => _creatingLocation = false);
        }
      }
    } finally {
      code.dispose();
      name.dispose();
    }
  }

  @override
  void didUpdateWidget(covariant _StockBody oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.database, widget.database) ||
        oldWidget.registration.tenantId != widget.registration.tenantId ||
        oldWidget.registration.userId != widget.registration.userId ||
        oldWidget.registration.accessToken != widget.registration.accessToken) {
      // A tenant/database switch must not display cached stock from the
      // previous database while the replacement tenant loads.
      ++_serverGeneration;
      _serverLocationsLoading = false;
      _serverLocations = null;
      _serverLocationsError = null;
      _locations = <String>[];
      _medicines = <StockMedicineSnapshot>[];
      _expiring = <StockMedicineSnapshot>[];
      _overview = null;
      _location = null;
      _page = 0;
      _search.clear();
      _load();
      _loadServerLocations();
    }
  }

  @override
  void dispose() {
    ++_serverGeneration;
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final int generation = ++_generation;
    final String? location = _location;
    final String search = _search.text;
    final int page = _page;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final OfflineCatalogBrowser browser = OfflineCatalogBrowser(
        widget.database,
      );
      final List<String> locations = await browser.stockLocations();
      final InventoryOverview overview = await browser.inventoryOverview(
        locationId: location,
      );
      final List<StockMedicineSnapshot> medicines = await browser.medicines(
        search: search,
        locationId: location,
        page: page,
        expiringOnly: false,
      );
      final List<StockMedicineSnapshot> expiring = await browser.medicines(
        search: search,
        locationId: location,
        page: 0,
        expiringOnly: true,
      );
      if (!mounted || generation != _generation) return;
      setState(() {
        _locations = locations;
        _overview = overview;
        _medicines = medicines;
        _expiring = expiring;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted || generation != _generation) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    final TextTheme text = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          s.stock,
          style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 4),
        Text(s.stockReadOnly, style: text.bodySmall),
        const SizedBox(height: 12),
        if (!_loading && _error == null && _overview != null)
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              _Metric(
                icon: Icons.medication_outlined,
                label: s.metricMedicines,
                value: '${_overview!.medicines}',
                color: const Color(0xFFDDEAFF),
              ),
              _Metric(
                icon: Icons.inventory_2_outlined,
                label: s.metricOutOfStock,
                value: '${_overview!.outOfStock}',
                color: const Color(0xFFFFEED4),
              ),
              _Metric(
                icon: Icons.schedule_outlined,
                label: s.metricExpiring30,
                value: '${_overview!.expiringBatches}',
                color: const Color(0xFFFFF3D9),
              ),
              _Metric(
                icon: Icons.event_busy_outlined,
                label: s.expiredBatches,
                value: '${_overview!.expiredBatches}',
                color: const Color(0xFFFFE6DF),
              ),
            ],
          ),
        const SizedBox(height: 12),
        SegmentedButton<int>(
          segments: <ButtonSegment<int>>[
            ButtonSegment<int>(value: 0, label: Text(s.tabStock)),
            ButtonSegment<int>(value: 1, label: Text(s.tabBatches)),
            ButtonSegment<int>(value: 2, label: Text(s.tabExpiry)),
            ButtonSegment<int>(value: 3, label: Text(s.tabStorage)),
          ],
          selected: <int>{_tab},
          onSelectionChanged: (Set<int> selection) =>
              setState(() => _tab = selection.first),
        ),
        const SizedBox(height: 12),
        if (_tab == 3) ...<Widget>[
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(28),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                _Notice(message: _error!),
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerRight,
                  child: OutlinedButton.icon(
                    onPressed: _load,
                    icon: const Icon(Icons.refresh),
                    label: Text(s.refresh),
                  ),
                ),
              ],
            )
          else
            _buildStorage(context),
        ] else ...<Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: TextField(
                  decoration: InputDecoration(
                    labelText: s.searchMedicine,
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: IconButton(
                      tooltip: s.refresh,
                      icon: const Icon(Icons.refresh),
                      onPressed: _load,
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (String value) {
                    _page = 0;
                    _load();
                  },
                ),
              ),
              const SizedBox(width: 8),
              // Filters are not built yet; shown disabled to match the design.
              const IconButton.outlined(
                onPressed: null,
                icon: Icon(Icons.tune_rounded),
              ),
            ],
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            key: ValueKey('stock-location-${_location ?? 'all'}'),
            initialValue: _location ?? '',
            decoration: InputDecoration(labelText: s.location),
            items: <DropdownMenuItem<String>>[
              DropdownMenuItem<String>(value: '', child: Text(s.allLocations)),
              ..._locations.map(
                (String id) =>
                    DropdownMenuItem<String>(value: id, child: Text(id)),
              ),
            ],
            onChanged: (String? value) {
              final String? selected = value == null || value.isEmpty
                  ? null
                  : value;
              if (selected == _location) return;
              _location = selected;
              _page = 0;
              _load();
            },
          ),
          const SizedBox(height: 12),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(28),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _Notice(message: _error!)
          else
            _buildTab(context),
        ],
      ],
    );
  }

  Widget _buildTab(BuildContext context) {
    if (_tab == 0) {
      return _buildStockList(context);
    }
    final List<StockMedicineSnapshot> source = _tab == 1
        ? _medicines
        : _expiring;
    return _buildBatchList(context, source, expiringView: _tab == 2);
  }

  Widget _buildStorage(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    const Icon(
                      Icons.warehouse_outlined,
                      color: Color(0xFF1557C0),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        s.storageTitle,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                    IconButton(
                      tooltip: s.refresh,
                      onPressed: _loading ? null : _load,
                      icon: const Icon(Icons.refresh),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(s.storageBody),
              ],
            ),
          ),
        ),
        if (_serverLocationsLoading)
          const LinearProgressIndicator()
        else if (_serverLocationsError != null)
          ListTile(
            title: Text(_serverLocationsError!),
            trailing: IconButton(
              onPressed: _loadServerLocations,
              icon: const Icon(Icons.refresh),
            ),
          )
        else if (_serverLocations != null) ...<Widget>[
          ..._serverLocations!.map(
            (MobileStockLocation location) => ListTile(
              leading: const Icon(Icons.warehouse_outlined),
              title: Text(location.name),
              subtitle: Text('${location.code} · ${location.kind}'),
            ),
          ),
        ],
        if (!_serverLocationsLoading &&
            _serverLocations == null &&
            !_loading &&
            _error == null &&
            _locations.isEmpty) ...<Widget>[
          const SizedBox(height: 12),
          Text(s.noStorageLocations),
        ],
        if (_serverLocations == null &&
            !_loading &&
            _error == null &&
            _locations.isNotEmpty) ...<Widget>[
          const SizedBox(height: 12),
          Text(s.location, style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 8),
          ..._locations.map(
            (String id) => Card(
              child: ListTile(
                leading: const Icon(Icons.place_outlined),
                title: Text(id),
              ),
            ),
          ),
        ],
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed:
              _creatingLocation ||
                  _serverLocationsLoading ||
                  !widget.registration.permissions.contains('inventory.manage')
              ? null
              : _createLocation,
          icon: const Icon(Icons.add_location_alt_outlined),
          label: Text(s.addStorageLocation),
        ),
        const SizedBox(height: 8),
        Text(s.storageComingSoon),
      ],
    );
  }

  Widget _buildStockList(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    if (_medicines.isEmpty) {
      return _Notice(message: s.noMedicinesMatch);
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        ..._medicines.map(
          (StockMedicineSnapshot item) => Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: <Widget>[
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF2FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.medication_rounded,
                      color: Color(0xFF1557C0),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          item.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        if (item.genericName.isNotEmpty)
                          Text(
                            item.genericName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        const SizedBox(height: 4),
                        Text(
                          '${s.stock}: ${item.available.compact} ${item.saleUnit}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF1E9E5A),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: Colors.blueGrey,
                  ),
                ],
              ),
            ),
          ),
        ),
        Row(
          children: <Widget>[
            IconButton(
              tooltip: s.previousPage,
              onPressed: _page == 0
                  ? null
                  : () {
                      _page--;
                      _load();
                    },
              icon: const Icon(Icons.chevron_left),
            ),
            Text(s.pageNumber(_page + 1)),
            IconButton(
              tooltip: s.nextPage,
              onPressed: _medicines.length < 100
                  ? null
                  : () {
                      _page++;
                      _load();
                    },
              icon: const Icon(Icons.chevron_right),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildBatchList(
    BuildContext context,
    List<StockMedicineSnapshot> medicines, {
    required bool expiringView,
  }) {
    final ModuleStrings s = ModuleStrings.of(context);
    final List<_BatchRow> rows = <_BatchRow>[
      for (final StockMedicineSnapshot item in medicines)
        for (final StockBatchSnapshot batch in item.batches)
          _BatchRow(medicine: item, batch: batch),
    ];
    if (rows.isEmpty) {
      return _Notice(
        message: expiringView ? s.noExpiryMatches : s.noBatchMatches,
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: rows
          .map(
            (_BatchRow row) => Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                title: Text(
                  row.medicine.name,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                subtitle: Text(
                  '${s.batch} ${row.batch.batchNumber.isEmpty ? '—' : row.batch.batchNumber}'
                  ' · ${row.batch.locationId}'
                  ' · ${s.expires} ${_formatDate(row.batch.expiresAt)}'
                  '${row.batch.expired ? ' · ${s.expired}' : ''}',
                ),
                trailing: Text(
                  row.batch.quantity.compact,
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    color: row.batch.expired
                        ? const Color(0xFFD93A3A)
                        : const Color(0xFF1B2A41),
                  ),
                ),
              ),
            ),
          )
          .toList(growable: false),
    );
  }
}

class _BatchRow {
  const _BatchRow({required this.medicine, required this.batch});
  final StockMedicineSnapshot medicine;
  final StockBatchSnapshot batch;
}

String _formatDate(DateTime? value) {
  if (value == null) return '—';
  return '${value.year}-'
      '${value.month.toString().padLeft(2, '0')}-'
      '${value.day.toString().padLeft(2, '0')}';
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 19, color: const Color(0xFF1557C0)),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                value,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(label, style: const TextStyle(fontSize: 11)),
            ],
          ),
        ],
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(padding: const EdgeInsets.all(20), child: Text(message)),
    );
  }
}
