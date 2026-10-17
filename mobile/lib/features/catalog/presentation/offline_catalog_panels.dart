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
  String? _location;
  String? _error;
  bool _loading = true;

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
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final OfflineCatalogBrowser browser = OfflineCatalogBrowser(
        widget.database,
      );
      final List<String> locations = await browser.stockLocations();
      final List<StockMedicineSnapshot> medicines = await browser.medicines(
        search: _search.text,
        locationId: _location,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _locations = locations;
        _medicines = medicines;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) {
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
          onSubmitted: (String value) => _load(),
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
            _load();
          },
        ),
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
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final List<CustomerSnapshot> customers = await OfflineCatalogBrowser(
        widget.database,
      ).customers(search: _search.text);
      if (!mounted) {
        return;
      }
      setState(() {
        _customers = customers;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) {
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
          onSubmitted: (String value) => _load(),
        ),
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
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person_outline)),
                title: Text(customer.name),
                subtitle: Text(customer.phone.isEmpty ? '—' : customer.phone),
                trailing: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: <Widget>[
                    Text(
                      strings.catalogBalance,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    Text('AFN ${customer.balance}'),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}
