import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/suppliers/data/mobile_supplier_api.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;

class SuppliersPanel extends ConsumerStatefulWidget {
  const SuppliersPanel({super.key});

  @override
  ConsumerState<SuppliersPanel> createState() => _SuppliersPanelState();
}

class _SuppliersPanelState extends ConsumerState<SuppliersPanel> {
  final _search = TextEditingController();
  Future<List<MobileSupplier>>? _suppliers;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _load() {
    final registration = ref.read(mobileRegistrationProvider).asData?.value;
    if (registration == null) return;
    final client = http.Client();
    setState(() {
      _suppliers =
          MobileSupplierApi(environment: AppEnvironment.current, client: client)
              .list(registration: registration, search: _search.text)
              .whenComplete(client.close);
    });
  }

  Future<void> _showDetails(MobileSupplier supplier) async {
    final registration = ref.read(mobileRegistrationProvider).asData?.value;
    if (registration == null) return;
    final client = http.Client();
    final future =
        MobileSupplierApi(environment: AppEnvironment.current, client: client)
            .detail(registration: registration, supplierId: supplier.id)
            .whenComplete(client.close);
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(supplier.name),
        content: SizedBox(
          width: 420,
          child: FutureBuilder<MobileSupplierDetail>(
            future: future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return Text('Unable to load details: ${snapshot.error}');
              }
              final detail = snapshot.data!;
              Widget activity(String heading, List<SupplierActivity> entries) {
                return ExpansionTile(
                  title: Text('$heading (${entries.length})'),
                  children: entries.isEmpty
                      ? <Widget>[
                          const ListTile(title: Text('No recent records')),
                        ]
                      : entries
                            .map(
                              (entry) => ListTile(
                                title: Text(entry.title),
                                subtitle: Text(entry.detail),
                                trailing: Text(entry.total),
                              ),
                            )
                            .toList(growable: false),
                );
              }

              return SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    if (detail.supplier.contactPerson != null)
                      Text('Contact: ${detail.supplier.contactPerson}'),
                    if (detail.supplier.phone != null)
                      Text('Phone: ${detail.supplier.phone}'),
                    if (detail.supplier.city != null)
                      Text('City: ${detail.supplier.city}'),
                    if (registration.permissions.contains('accounting.manage'))
                      Text(
                        detail.outstanding.isEmpty
                            ? 'No outstanding invoice balances'
                            : 'Outstanding: ${detail.outstanding.join(', ')}',
                      ),
                    activity('Purchase orders', detail.orders),
                    if (registration.permissions.contains('accounting.manage'))
                      activity('Invoices', detail.invoices),
                    if (registration.permissions.contains('accounting.manage'))
                      activity('Payments', detail.payments),
                  ],
                ),
              );
            },
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final registration = ref.watch(mobileRegistrationProvider).asData?.value;
    if (registration == null ||
        !registration.permissions.contains('inventory.manage')) {
      return const ListTile(
        leading: Icon(Icons.lock_outline),
        title: Text('Suppliers'),
        subtitle: Text('An active inventory management session is required.'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Suppliers', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        TextField(
          controller: _search,
          decoration: InputDecoration(
            labelText: 'Search suppliers',
            suffixIcon: IconButton(
              tooltip: 'Search',
              onPressed: _load,
              icon: const Icon(Icons.search),
            ),
          ),
          onSubmitted: (_) => _load(),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh),
          label: const Text('Load suppliers'),
        ),
        if (_suppliers != null)
          FutureBuilder<List<MobileSupplier>>(
            future: _suppliers,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return Text('Unable to load suppliers: ${snapshot.error}');
              }
              final suppliers = snapshot.data ?? <MobileSupplier>[];
              if (suppliers.isEmpty) return const Text('No suppliers found.');
              return Column(
                children: suppliers
                    .map(
                      (supplier) => ListTile(
                        onTap: () => _showDetails(supplier),
                        leading: const Icon(Icons.storefront_outlined),
                        title: Text(supplier.name),
                        subtitle: Text(
                          [
                            if (supplier.code != null) supplier.code!,
                            if (supplier.contactPerson != null)
                              supplier.contactPerson!,
                            if (supplier.phone != null) supplier.phone!,
                            if (supplier.city != null) supplier.city!,
                          ].join(' • '),
                        ),
                        trailing: supplier.isActive
                            ? const Icon(Icons.check_circle_outline)
                            : const Icon(Icons.block_outlined),
                      ),
                    )
                    .toList(growable: false),
              );
            },
          ),
      ],
    );
  }
}
