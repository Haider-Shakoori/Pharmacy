import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/purchasing/data/purchase_receiving_summary.dart';
import 'package:businessos_pharmacy/features/purchasing/presentation/goods_receipt_worksheet.dart';
import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Offline purchasing is read-only until the server supports idempotent
/// purchase-order and goods-receipt push events.
class OfflinePurchasingPanel extends ConsumerWidget {
  const OfflinePurchasingPanel({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final registration = ref.watch(mobileRegistrationProvider);
    return registration.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => Text('Registration error: $error'),
      data: (MobileRegistration? user) {
        if (user == null) {
          return const Text('Register this device to view purchasing records.');
        }
        if (!user.permissions.contains('purchases.manage') &&
            !user.permissions.contains('inventory.manage')) {
          return const Text(
            'Purchasing access is not permitted for this user.',
          );
        }
        return ref
            .watch(pharmacyDatabaseProvider)
            .when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => Text('Offline database error: $error'),
              data: (PharmacyDatabase db) => _PurchasingBrowser(database: db),
            );
      },
    );
  }
}

class _PurchasingBrowser extends StatefulWidget {
  const _PurchasingBrowser({required this.database});
  final PharmacyDatabase database;

  @override
  State<_PurchasingBrowser> createState() => _PurchasingBrowserState();
}

class _PurchasingBrowserState extends State<_PurchasingBrowser> {
  final _search = TextEditingController();
  bool _orders = false;
  int _page = 0;
  static const int _pageSize = 30;
  late Future<List<QueryRow>> _rows;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  void _refresh() {
    _rows = _load();
  }

  Future<List<QueryRow>> _load() {
    final pattern =
        '%${_search.text.trim().replaceAll('!', '!!').replaceAll('%', '!%').replaceAll('_', '!_')}%';
    final sql = _orders
        ? '''
          SELECT p.id, p.number AS title, p.status AS subtitle,
                 p.grand_total AS amount, p.currency,
                 p.order_date, p.expected_date, p.lines_json,
                 COALESCE(s.name, 'Unknown supplier') AS supplier
          FROM local_purchase_orders p
          LEFT JOIN local_suppliers s ON s.id = p.supplier_id
          WHERE p.is_deleted = 0
            AND (p.number LIKE ? ESCAPE '!' OR
                 s.name LIKE ? ESCAPE '!')
          ORDER BY p.order_date DESC, p.id DESC
          LIMIT ? OFFSET ?
          '''
        : '''
          SELECT id, name AS title, COALESCE(code, '') AS subtitle,
                 phone, contact_person, is_active
          FROM local_suppliers
          WHERE is_deleted = 0
            AND (name LIKE ? ESCAPE '!' OR
                 COALESCE(code, '') LIKE ? ESCAPE '!' OR
                 COALESCE(phone, '') LIKE ? ESCAPE '!')
          ORDER BY name COLLATE NOCASE, id
          LIMIT ? OFFSET ?
          ''';
    return widget.database
        .customSelect(
          sql,
          variables: <Variable<Object>>[
            Variable<String>(pattern),
            Variable<String>(pattern),
            if (!_orders) Variable<String>(pattern),
            Variable<int>(_pageSize + 1),
            Variable<int>(_page * _pageSize),
          ],
        )
        .get();
  }

  void _change({bool? orders}) {
    setState(() {
      if (orders != null) _orders = orders;
      _page = 0;
      _refresh();
    });
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _details(QueryRow row) {
    final lines = _orders
        ? (jsonDecode(row.read<String>('lines_json')) as List<dynamic>)
        : <dynamic>[];
    final receiving = _orders
        ? PurchaseReceivingSummary.fromLines(lines)
        : null;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: ListView(
            shrinkWrap: true,
            children: <Widget>[
              Text(
                row.read<String>('title'),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (_orders) ...<Widget>[
                Text('Supplier: ${row.read<String>('supplier')}'),
                Text('Status: ${row.read<String>('subtitle')}'),
                Text(
                  'Total: ${row.read<String>('amount')} ${row.read<String>('currency')}',
                ),
                Text(
                  'Order date: ${row.readNullable<String>('order_date') ?? '—'}',
                ),
                Text(
                  'Expected: ${row.readNullable<String>('expected_date') ?? '—'}',
                ),
                const Divider(),
                if (receiving != null) ...<Widget>[
                  Text('Ordered quantity: ${receiving.ordered.compact}'),
                  Text('Received quantity: ${receiving.received.compact}'),
                  Text(
                    'Outstanding quantity: ${receiving.outstanding.compact}',
                  ),
                  Text('Lines awaiting receipt: ${receiving.linesOutstanding}'),
                  if (receiving.overReceived.isPositive)
                    Text(
                      'Over-received quantity: ${receiving.overReceived.compact}',
                    ),
                  const SizedBox(height: 8),
                ],
                Text(
                  'Order lines',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                if (lines.isNotEmpty &&
                    (row.read<String>('subtitle') == 'approved' ||
                        row.read<String>('subtitle') == 'partially_received'))
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => GoodsReceiptWorksheet(
                            database: widget.database,
                            purchaseOrderId: row.read<String>('id'),
                            orderNumber: row.read<String>('title'),
                            orderStatus: row.read<String>('subtitle'),
                            lines: lines,
                          ),
                        ),
                      ),
                      icon: const Icon(Icons.inventory_outlined),
                      label: const Text('Open receiving worksheet'),
                    ),
                  ),
                if (lines.isEmpty) const Text('No lines synced.'),
                for (final raw in lines)
                  if (raw is Map)
                    ListTile(
                      dense: true,
                      title: Text(
                        raw['description']?.toString() ??
                            raw['medicine_id']?.toString() ??
                            'Medicine',
                      ),
                      subtitle: Text(
                        'Ordered: ${raw['ordered_quantity'] ?? '0'}  •  Received: ${raw['received_quantity'] ?? '0'}',
                      ),
                      trailing: Text(raw['line_total']?.toString() ?? '0'),
                    ),
              ] else ...<Widget>[
                Text('Code: ${row.read<String>('subtitle')}'),
                Text('Phone: ${row.readNullable<String>('phone') ?? '—'}'),
                Text(
                  'Contact: ${row.readNullable<String>('contact_person') ?? '—'}',
                ),
                Text(
                  row.read<int>('is_active') == 1
                      ? 'Active supplier'
                      : 'Inactive supplier',
                ),
              ],
              const SizedBox(height: 12),
              const Text(
                'Read-only offline copy. Changes and goods receiving require server synchronization.',
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text('Purchasing', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 6),
        const Text('Synced records available offline • read-only'),
        const SizedBox(height: 12),
        SegmentedButton<bool>(
          segments: const <ButtonSegment<bool>>[
            ButtonSegment(
              value: false,
              label: Text('Suppliers'),
              icon: Icon(Icons.local_shipping_outlined),
            ),
            ButtonSegment(
              value: true,
              label: Text('Orders'),
              icon: Icon(Icons.receipt_long_outlined),
            ),
          ],
          selected: <bool>{_orders},
          onSelectionChanged: (values) => _change(orders: values.first),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _search,
          decoration: InputDecoration(
            prefixIcon: const Icon(Icons.search),
            hintText: _orders
                ? 'Search order or supplier'
                : 'Search supplier, code or phone',
            border: const OutlineInputBorder(),
          ),
          onChanged: (_) => _change(),
        ),
        const SizedBox(height: 12),
        FutureBuilder<List<QueryRow>>(
          future: _rows,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return Text(
                'Unable to read offline purchasing records: ${snapshot.error}',
              );
            }
            if (!snapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final all = snapshot.data!;
            final hasMore = all.length > _pageSize;
            final rows = all.take(_pageSize).toList();
            return Column(
              children: <Widget>[
                if (rows.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(20),
                    child: Text(
                      'No synced records found. Connect and synchronize first.',
                    ),
                  ),
                for (final row in rows)
                  Card(
                    child: ListTile(
                      title: Text(row.read<String>('title')),
                      subtitle: Text(
                        _orders
                            ? '${row.read<String>('supplier')} • ${row.read<String>('subtitle')}'
                            : row.read<String>('subtitle'),
                      ),
                      trailing: _orders
                          ? Text(
                              '${row.read<String>('amount')} ${row.read<String>('currency')}',
                            )
                          : const Icon(Icons.chevron_right),
                      onTap: () => _details(row),
                    ),
                  ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: <Widget>[
                    Text('Page ${_page + 1}'),
                    Row(
                      children: <Widget>[
                        IconButton(
                          tooltip: 'Previous page',
                          onPressed: _page == 0
                              ? null
                              : () {
                                  setState(() {
                                    _page--;
                                    _refresh();
                                  });
                                },
                          icon: const Icon(Icons.chevron_left),
                        ),
                        IconButton(
                          tooltip: 'Next page',
                          onPressed: hasMore
                              ? () {
                                  setState(() {
                                    _page++;
                                    _refresh();
                                  });
                                }
                              : null,
                          icon: const Icon(Icons.chevron_right),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            );
          },
        ),
      ],
    );
  }
}
