import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
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
    final AsyncValue<MobileRegistration?> registration = ref.watch(
      mobileRegistrationProvider,
    );
    return registration.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object error, StackTrace trace) => Text(error.toString()),
      data: (MobileRegistration? user) {
        if (user == null) {
          return const _Notice(message: 'Register this device to view stock.');
        }
        final bool permitted =
            user.permissions.contains('pos.sell') ||
            user.permissions.contains('inventory.manage');
        if (!permitted) {
          return const _Notice(
            message: 'Stock access is not permitted for this user.',
          );
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
    final TextTheme text = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          'Stock',
          style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 4),
        Text(
          'Read-only. Based on locally synced data.',
          style: text.bodySmall,
        ),
        const SizedBox(height: 12),
        if (!_loading && _error == null && _overview != null)
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              _Metric(
                icon: Icons.medication_outlined,
                label: 'Medicines',
                value: '${_overview!.medicines}',
                color: const Color(0xFFDDEAFF),
              ),
              _Metric(
                icon: Icons.inventory_2_outlined,
                label: 'Out of stock',
                value: '${_overview!.outOfStock}',
                color: const Color(0xFFFFEED4),
              ),
              _Metric(
                icon: Icons.schedule_outlined,
                label: 'Expiring 30 days',
                value: '${_overview!.expiringBatches}',
                color: const Color(0xFFFFF3D9),
              ),
              _Metric(
                icon: Icons.event_busy_outlined,
                label: 'Expired batches',
                value: '${_overview!.expiredBatches}',
                color: const Color(0xFFFFE6DF),
              ),
            ],
          ),
        const SizedBox(height: 12),
        SegmentedButton<int>(
          segments: const <ButtonSegment<int>>[
            ButtonSegment<int>(value: 0, label: Text('Stock')),
            ButtonSegment<int>(value: 1, label: Text('Batches')),
            ButtonSegment<int>(value: 2, label: Text('Expiry')),
            ButtonSegment<int>(value: 3, label: Text('Storage')),
          ],
          selected: <int>{_tab},
          onSelectionChanged: (Set<int> selection) =>
              setState(() => _tab = selection.first),
        ),
        const SizedBox(height: 12),
        if (_tab == 3)
          _buildStorage(context)
        else ...<Widget>[
          TextField(
            decoration: InputDecoration(
              labelText: 'Search medicine or code',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                tooltip: 'Refresh',
                icon: const Icon(Icons.refresh),
                onPressed: _load,
              ),
            ),
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: (String value) {
              _page = 0;
              _load();
            },
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            key: ValueKey('stock-location-${_location ?? 'all'}'),
            initialValue: _location ?? '',
            decoration: const InputDecoration(labelText: 'Location'),
            items: <DropdownMenuItem<String>>[
              const DropdownMenuItem<String>(
                value: '',
                child: Text('All locations'),
              ),
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
    final List<StockMedicineSnapshot> source = _tab == 1 ? _medicines : _expiring;
    return _buildBatchList(context, source, expiringView: _tab == 2);
  }

  Widget _buildStorage(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const Card(
          child: Padding(
            padding: EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Icon(Icons.warehouse_outlined, color: Color(0xFF1557C0)),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Medicine storage',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 10),
                Text(
                  'Storage locations (branch, room, rack, shelf and bin) are not available on this device yet. '
                  'Batches stay in their recorded location until this feature is enabled.',
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        const FilledButton.icon(
          onPressed: null,
          icon: Icon(Icons.add_location_alt_outlined),
          label: Text('Add storage location'),
        ),
        const SizedBox(height: 8),
        const Text(
          'Coming soon: create locations, assign batches, stock counts and transfers.',
        ),
      ],
    );
  }

  Widget _buildStockList(BuildContext context) {
    if (_medicines.isEmpty) {
      return const _Notice(message: 'No medicines match this search.');
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        ..._medicines.map(
          (StockMedicineSnapshot item) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              title: Text(
                item.name,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              subtitle: Text(
                <String>[item.medicineCode, item.genericName]
                    .where((String value) => value.isNotEmpty)
                    .join(' · '),
              ),
              trailing: Text(
                '${item.available.compact} ${item.saleUnit}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ),
        Row(
          children: <Widget>[
            IconButton(
              tooltip: 'Previous page',
              onPressed: _page == 0
                  ? null
                  : () {
                      _page--;
                      _load();
                    },
              icon: const Icon(Icons.chevron_left),
            ),
            Text('Page ${_page + 1}'),
            IconButton(
              tooltip: 'Next page',
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
    final List<_BatchRow> rows = <_BatchRow>[
      for (final StockMedicineSnapshot item in medicines)
        for (final StockBatchSnapshot batch in item.batches)
          _BatchRow(medicine: item, batch: batch),
    ];
    if (rows.isEmpty) {
      return _Notice(
        message: expiringView
            ? 'No expired or expiring batches.'
            : 'No batches match this search.',
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
                  'Batch ${row.batch.batchNumber.isEmpty ? '—' : row.batch.batchNumber}'
                  ' · ${row.batch.locationId}'
                  ' · Expires ${_formatDate(row.batch.expiresAt)}'
                  '${row.batch.expired ? ' · Expired' : ''}',
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
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Text(message),
      ),
    );
  }
}
