import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/localization/module_strings.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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
              data: (PharmacyDatabase db) => _StockBody(database: db),
            );
      },
    );
  }
}

class _StockBody extends StatefulWidget {
  const _StockBody({required this.database});

  final PharmacyDatabase database;

  @override
  State<_StockBody> createState() => _StockBodyState();
}

class _StockBodyState extends State<_StockBody> {
  final TextEditingController _search = TextEditingController();
  List<String> _locations = <String>[];
  List<StockMedicineSnapshot> _medicines = <StockMedicineSnapshot>[];
  List<StockMedicineSnapshot> _expiring = <StockMedicineSnapshot>[];
  InventoryOverview? _overview;
  String? _location;
  String? _error;
  bool _loading = true;
  int _page = 0;
  int _tab = 0; // 0 = Stock, 1 = Batches, 2 = Expiry, 3 = Storage
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
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
        if (_tab == 3)
          _buildStorage(context)
        else ...<Widget>[
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
                  ],
                ),
                const SizedBox(height: 10),
                Text(s.storageBody),
              ],
            ),
          ),
        ),
        if (_locations.isNotEmpty) ...<Widget>[
          const SizedBox(height: 12),
          Text(
            s.location,
            style: Theme.of(context).textTheme.titleSmall,
          ),
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
          onPressed: null,
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
