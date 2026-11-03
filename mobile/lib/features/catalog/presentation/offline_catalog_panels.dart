import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class OfflineStockPanel extends StatelessWidget {
  const OfflineStockPanel({super.key});

  @override
  Widget build(BuildContext context) {
    return const _CatalogAccess(isStock: true);
  }
}

class OfflineCustomersPanel extends StatelessWidget {
  const OfflineCustomersPanel({super.key});

  @override
  Widget build(BuildContext context) {
    return const _CatalogAccess(isStock: false);
  }
}

class _CatalogAccess extends ConsumerWidget {
  const _CatalogAccess({required this.isStock});

  final bool isStock;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppStrings strings = AppStrings.of(context);
    final AsyncValue<MobileRegistration?> registration = ref.watch(
      mobileRegistrationProvider,
    );
    return registration.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object error, StackTrace trace) => Text(error.toString()),
      data: (MobileRegistration? user) {
        if (user == null) {
          return _InfoCard(message: strings.registrationRequired);
        }
        final bool permitted =
            user.permissions.contains('pos.sell') ||
            user.permissions.contains(
              isStock ? 'inventory.manage' : 'customers.manage',
            );
        if (!permitted) {
          return _InfoCard(message: strings.catalogPermissionDenied);
        }
        // Open the tenant-scoped database only after a device is registered.
        return ref
            .watch(pharmacyDatabaseProvider)
            .when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (Object error, StackTrace trace) => Text(error.toString()),
              data: (PharmacyDatabase db) => isStock
                  ? _StockBrowser(database: db)
                  : _CustomerBrowser(database: db),
            );
      },
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(padding: const EdgeInsets.all(20), child: Text(message)),
  );
}

class _StockBrowser extends StatefulWidget {
  const _StockBrowser({required this.database});

  final PharmacyDatabase database;

  @override
  State<_StockBrowser> createState() => _StockBrowserState();
}

class _StockBrowserState extends State<_StockBrowser> {
  final TextEditingController _search = TextEditingController();
  List<String> _locations = <String>[];
  List<StockMedicineSnapshot> _medicines = <StockMedicineSnapshot>[];
  InventoryOverview? _overview;
  StockCostOverview? _costOverview;
  List<StockSaleMovement> _movements = <StockSaleMovement>[];
  String? _location;
  String? _error;
  bool _loading = true;
  bool _expiryOnly = false;
  int _page = 0;
  int _loadGeneration = 0;

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
    final int generation = ++_loadGeneration;
    final String? location = _location;
    final int page = _page;
    final String search = _search.text;
    final bool expiryOnly = _expiryOnly;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final OfflineCatalogBrowser browser = OfflineCatalogBrowser(
        widget.database,
      );
      final List<String> locations = await browser.stockLocations();
      final InventoryOverview overview = await browser.inventoryOverview(locationId: _location);
      final List<StockMedicineSnapshot> medicines = await browser.medicines(
        search: search,
        locationId: location,
        page: page,
        expiringOnly: expiryOnly,
      );
      final StockCostOverview costOverview = await browser.stockCostOverview(
        locationId: location,
      );
      final List<StockSaleMovement> movements = await browser.recentSaleMovements(
        locationId: location,
      );
      if (!mounted || generation != _loadGeneration) {
        return;
      }
      setState(() {
        _costOverview = costOverview;
        _movements = movements;
        _locations = locations;
        _overview = overview;
        _medicines = medicines;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted || generation != _loadGeneration) {
        return;
      }
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(strings.stockTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(
          strings.catalogReadOnly,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        if (!_loading && _error == null) ...<Widget>[
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              _InventoryMetric(
                icon: Icons.medication_outlined,
                label: 'Medicines',
                value: '${_overview?.medicines ?? 0}',
                color: const Color(0xFFDDEAFF),
              ),
              _InventoryMetric(
                icon: Icons.event_busy_outlined,
                label: 'Expired batches',
                value: '${_overview?.expiredBatches ?? 0}',
                color: const Color(0xFFFFE6DF),
              ),
              _InventoryMetric(
                icon: Icons.inventory_2_outlined,
                label: 'Out of stock',
                value: '${_overview?.outOfStock ?? 0}',
                color: const Color(0xFFFFEED4),
              ),
            ],
          ),
          _InventoryMetric(
            icon: Icons.schedule_outlined,
            label: 'Expiring in 30 days',
            value: '${_overview?.expiringBatches ?? 0}',
            color: const Color(0xFFFFF3D9),
          ),
          ListTile(
            leading: const Icon(Icons.payments_outlined),
            title: const Text('Remaining stock acquisition value'),
            subtitle: Text(
              '${_costOverview?.batchCount ?? 0} active, non-expired batches · based on recorded unit costs',
            ),
            trailing: Text(
              'AFN ${_costOverview?.acquisitionValue.compact ?? '0'}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
          ExpansionTile(
            title: const Text('Recent POS stock deductions'),
            subtitle: Text('${_movements.length} recent batch allocations · read only'),
            children: _movements.isEmpty
                ? <Widget>[
                    const ListTile(title: Text('No completed POS batch deductions recorded locally')),
                  ]
                : _movements.map((StockSaleMovement movement) => ListTile(
                    dense: true,
                    title: Text(movement.medicineName),
                    subtitle: Text(
                      'Batch: ${movement.batchNumber.isEmpty ? '—' : movement.batchNumber} · ${movement.locationId}',
                    ),
                    trailing: Text('−${movement.quantity}'),
                  )).toList(growable: false),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Show expired / expiring medicines only'),
            subtitle: const Text('Expired or due within 30 days'),
            value: _expiryOnly,
            onChanged: (bool value) {
              setState(() {
                _expiryOnly = value;
                _page = 0;
              });
              _load();
            },
          ),
          const SizedBox(height: 5),
          Text(
            'Summary counts include all locally synced active medicines, with the selected location filter. Browse the medicine list in pages of up to 100.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
        const SizedBox(height: 12),
        TextField(
          controller: _search,
          decoration: InputDecoration(
            labelText: strings.posSearch,
            hintText: strings.posSearchHint,
            prefixIcon: const Icon(Icons.search),
            suffixIcon: IconButton(
              tooltip: strings.catalogRefresh,
              icon: const Icon(Icons.refresh),
              onPressed: _load,
            ),
          ),
          textInputAction: TextInputAction.search,
          onSubmitted: (String value) {
            _page = 0;
            _load();
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _location ?? '',
          decoration: InputDecoration(labelText: strings.posLocation),
          items: <DropdownMenuItem<String>>[
            DropdownMenuItem<String>(
              value: '',
              child: Text(strings.catalogAllLocations),
            ),
            ..._locations.map(
              (String id) =>
                  DropdownMenuItem<String>(value: id, child: Text(id)),
            ),
          ],
          onChanged: (String? value) {
            _location = value == null || value.isEmpty ? null : value;
            _page = 0;
            _load();
          },
        ),
        if (!_loading && _error == null)
          Row(children: <Widget>[
            IconButton(
              tooltip: 'Previous 100 medicines',
              onPressed: _page == 0 ? null : () {
                _page--;
                _load();
              },
              icon: const Icon(Icons.chevron_left),
            ),
            Text('Page ${_page + 1}'),
            IconButton(
              tooltip: 'Next 100 medicines',
              onPressed: _medicines.length < 100 ? null : () {
                _page++;
                _load();
              },
              icon: const Icon(Icons.chevron_right),
            ),
          ]),
        if (_loading)
          const Padding(
            padding: EdgeInsets.all(28),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_error != null)
          _InfoCard(message: _error!)
        else if (_medicines.isEmpty)
          _InfoCard(message: strings.catalogEmptyStock)
        else
          ..._medicines.map(
            (StockMedicineSnapshot item) => Card(
              child: ExpansionTile(
                title: Text(item.name),
                subtitle: Text(
                  [
                    item.medicineCode,
                    item.genericName,
                  ].where((String value) => value.isNotEmpty).join(' · '),
                ),
                trailing: Text(
                  '${item.available.compact} ${item.saleUnit}',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                children: item.batches.isEmpty
                    ? <Widget>[ListTile(title: Text(strings.catalogNoBatches))]
                    : item.batches
                          .map((StockBatchSnapshot batch) {
                            final String date = batch.expiresAt == null
                                ? '—'
                                : '${batch.expiresAt!.year}-'
                                      '${batch.expiresAt!.month.toString().padLeft(2, '0')}-'
                                      '${batch.expiresAt!.day.toString().padLeft(2, '0')}';
                            final String status = batch.expired
                                ? strings.catalogExpired
                                : batch.status;
                            return ListTile(
                              dense: true,
                              title: Text(
                                '${strings.catalogBatch}: ${batch.batchNumber.isEmpty ? '—' : batch.batchNumber}',
                              ),
                              subtitle: Text(
                                '${batch.locationId} · ${strings.catalogExpiry}: $date · $status',
                              ),
                              trailing: Text(batch.quantity.compact),
                            );
                          })
                          .toList(growable: false),
              ),
            ),
          ),
      ],
    );
  }
}

class _CustomerBrowser extends StatefulWidget {
  const _CustomerBrowser({required this.database});

  final PharmacyDatabase database;

  @override
  State<_CustomerBrowser> createState() => _CustomerBrowserState();
}

class _CustomerBrowserState extends State<_CustomerBrowser> {
  final TextEditingController _search = TextEditingController();
  List<CustomerSnapshot> _customers = <CustomerSnapshot>[];
  bool _loading = true;
  int _page = 0;
  int _loadGeneration = 0;
  String? _error;

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
    final int generation = ++_loadGeneration;
    final int page = _page;
    final String search = _search.text;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final List<CustomerSnapshot> customers = await OfflineCatalogBrowser(
        widget.database,
      ).customers(search: search, page: page);
      if (!mounted || generation != _loadGeneration) {
        return;
      }
      setState(() {
        _customers = customers;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted || generation != _loadGeneration) {
        return;
      }
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          strings.customersTitle,
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 8),
        Text(
          strings.catalogReadOnly,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _search,
          decoration: InputDecoration(
            labelText: strings.catalogSearchCustomers,
            prefixIcon: const Icon(Icons.search),
            suffixIcon: IconButton(
              tooltip: strings.catalogRefresh,
              icon: const Icon(Icons.refresh),
              onPressed: _load,
            ),
          ),
          textInputAction: TextInputAction.search,
          onSubmitted: (String value) {
            _page = 0;
            _load();
          },
        ),
        if (!_loading && _error == null)
          Row(children: <Widget>[
            IconButton(
              tooltip: 'Previous 100 customers',
              onPressed: _page == 0 ? null : () {
                _page--;
                _load();
              },
              icon: const Icon(Icons.chevron_left),
            ),
            Text('Page ${_page + 1}'),
            IconButton(
              tooltip: 'Next 100 customers',
              onPressed: _customers.length < 100 ? null : () {
                _page++;
                _load();
              },
              icon: const Icon(Icons.chevron_right),
            ),
          ]),
        if (_loading)
          const Padding(
            padding: EdgeInsets.all(28),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_error != null)
          _InfoCard(message: _error!)
        else if (_customers.isEmpty)
          _InfoCard(message: strings.catalogEmptyCustomers)
        else
          ..._customers.map(
            (CustomerSnapshot customer) => Card(
              child: ExpansionTile(
                leading: const CircleAvatar(child: Icon(Icons.person_outline)),
                title: Text(customer.name),
                subtitle: Text(customer.phone.isEmpty ? '—' : customer.phone),
                children: <Widget>[
                  ListTile(
                    leading: const Icon(Icons.account_balance_wallet_outlined),
                    title: Text(strings.catalogBalance),
                    trailing: Text('AFN ${customer.balance}'),
                  ),
                  ListTile(
                    leading: const Icon(Icons.phone_outlined),
                    title: Text(customer.phone.isEmpty ? 'No phone recorded' : customer.phone),
                    subtitle: const Text('Synced customer contact'),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _InventoryMetric extends StatelessWidget {
  const _InventoryMetric({
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
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: <Widget>[
      Icon(icon, size: 19, color: const Color(0xFF1557C0)),
      const SizedBox(width: 8),
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
        Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
        Text(label, style: const TextStyle(fontSize: 11)),
      ]),
    ]),
  );
}
