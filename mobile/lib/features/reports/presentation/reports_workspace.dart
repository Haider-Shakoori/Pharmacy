import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/localization/module_strings.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:businessos_pharmacy/features/reports/data/offline_sales_summary.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

enum _ReportKind { sales, profitLoss, inventory, expiry, purchase, customers, financial }

class _ReportInfo {
  const _ReportInfo({
    required this.kind,
    required this.icon,
    required this.title,
    required this.detail,
    required this.available,
  });

  final _ReportKind kind;
  final IconData icon;
  final String title;
  final String detail;
  final bool available;
}

List<_ReportInfo> _reportList(ModuleStrings s) => <_ReportInfo>[
  _ReportInfo(
    kind: _ReportKind.sales,
    icon: Icons.show_chart_rounded,
    title: s.salesReport,
    detail: s.salesReportDetail,
    available: true,
  ),
  _ReportInfo(
    kind: _ReportKind.inventory,
    icon: Icons.inventory_2_outlined,
    title: s.inventoryReport,
    detail: s.inventoryReportDetail,
    available: true,
  ),
  _ReportInfo(
    kind: _ReportKind.expiry,
    icon: Icons.alarm_rounded,
    title: s.expiryReport,
    detail: s.expiryReportDetail,
    available: true,
  ),
  _ReportInfo(
    kind: _ReportKind.customers,
    icon: Icons.people_outline_rounded,
    title: s.customerReport,
    detail: s.customerReportDetail,
    available: true,
  ),
  _ReportInfo(
    kind: _ReportKind.profitLoss,
    icon: Icons.trending_up_rounded,
    title: s.profitLoss,
    detail: s.profitLossDetail,
    available: false,
  ),
  _ReportInfo(
    kind: _ReportKind.purchase,
    icon: Icons.local_shipping_outlined,
    title: s.purchaseReport,
    detail: s.purchaseReportDetail,
    available: false,
  ),
  _ReportInfo(
    kind: _ReportKind.financial,
    icon: Icons.account_balance_wallet_outlined,
    title: s.financialReport,
    detail: s.financialReportDetail,
    available: false,
  ),
];

/// Reports directory with working local detail views.
///
/// Reports that depend on data not yet synced to the phone are shown as
/// "Soon" and cannot be opened, so no placeholder figures are displayed.
class ReportsWorkspace extends ConsumerStatefulWidget {
  const ReportsWorkspace({super.key});

  @override
  ConsumerState<ReportsWorkspace> createState() => _ReportsWorkspaceState();
}

class _ReportsWorkspaceState extends ConsumerState<ReportsWorkspace> {
  _ReportKind? _selected;

  @override
  Widget build(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    final AsyncValue<MobileRegistration?> registration = ref.watch(
      mobileRegistrationProvider,
    );
    return registration.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object error, StackTrace trace) => Text(error.toString()),
      data: (MobileRegistration? user) {
        if (user == null) {
          return _Notice(message: s.registerToViewReports);
        }
        return ref
            .watch(pharmacyDatabaseProvider)
            .when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (Object error, StackTrace trace) => Text(error.toString()),
              data: (PharmacyDatabase db) {
                final _ReportKind? selected = _selected;
                if (selected == null) {
                  return _buildDirectory(context, s);
                }
                return _buildDetail(context, s, db, selected);
              },
            );
      },
    );
  }

  Widget _buildDirectory(BuildContext context, ModuleStrings s) {
    final TextTheme text = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          s.reports,
          style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 4),
        Text(s.reportsSubtitle, style: text.bodySmall),
        const SizedBox(height: 12),
        ..._reportList(s).map(
          (_ReportInfo info) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: info.available
                    ? const Color(0xFFDDEAFF)
                    : const Color(0xFFEFF1F5),
                child: Icon(
                  info.icon,
                  color: info.available
                      ? const Color(0xFF1557C0)
                      : Colors.blueGrey,
                ),
              ),
              title: Text(
                info.title,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              subtitle: Text(info.detail),
              trailing: info.available
                  ? const Icon(Icons.chevron_right_rounded)
                  : Chip(
                      label: Text(s.soon),
                      visualDensity: VisualDensity.compact,
                    ),
              onTap: info.available
                  ? () => setState(() => _selected = info.kind)
                  : null,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDetail(
    BuildContext context,
    ModuleStrings s,
    PharmacyDatabase db,
    _ReportKind kind,
  ) {
    final _ReportInfo info = _reportList(s).firstWhere(
      (_ReportInfo report) => report.kind == kind,
    );
    final Widget body = switch (kind) {
      _ReportKind.sales => _SalesReport(database: db),
      _ReportKind.inventory => _InventoryReport(database: db),
      _ReportKind.expiry => _ExpiryReport(database: db),
      _ReportKind.customers => _CustomerReport(database: db),
      _ => _Notice(message: s.reportNotAvailable),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            IconButton(
              tooltip: s.back,
              onPressed: () => setState(() => _selected = null),
              icon: const Icon(Icons.arrow_back_rounded),
            ),
            Expanded(
              child: Text(
                info.title,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        body,
      ],
    );
  }
}

String _formatDate(DateTime value) {
  return '${value.year.toString().padLeft(4, '0')}-'
      '${value.month.toString().padLeft(2, '0')}-'
      '${value.day.toString().padLeft(2, '0')}';
}

class _SalesData {
  const _SalesData(this.summary, this.trend);
  final OfflineSalesSummary summary;
  final List<OfflineDailySales> trend;
}

class _SalesReport extends StatefulWidget {
  const _SalesReport({required this.database});

  final PharmacyDatabase database;

  @override
  State<_SalesReport> createState() => _SalesReportState();
}

class _SalesReportState extends State<_SalesReport> {
  int _days = 1;

  @override
  Widget build(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    final DateTime now = DateTime.now();
    final String end = _formatDate(now);
    final String start = _formatDate(
      DateTime(now.year, now.month, now.day - _days + 1),
    );
    final OfflineSalesSummaryRepository repository =
        OfflineSalesSummaryRepository(widget.database);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        SegmentedButton<int>(
          segments: <ButtonSegment<int>>[
            ButtonSegment<int>(value: 1, label: Text(s.today)),
            ButtonSegment<int>(value: 7, label: Text(s.days7)),
            ButtonSegment<int>(value: 30, label: Text(s.days30)),
          ],
          selected: <int>{_days},
          onSelectionChanged: (Set<int> selection) =>
              setState(() => _days = selection.first),
        ),
        const SizedBox(height: 12),
        FutureBuilder<_SalesData>(
          key: ValueKey('sales-$_days-$end'),
          future: () async {
            final OfflineSalesSummary summary = await repository
                .forBusinessDateRange(start, end);
            final List<OfflineDailySales> trend = _days > 1
                ? await repository.dailyTrend(start, end)
                : <OfflineDailySales>[];
            return _SalesData(summary, trend);
          }(),
          builder: (BuildContext context, AsyncSnapshot<_SalesData> snapshot) {
            if (snapshot.hasError) {
              return _Notice(message: s.unableToReadSales);
            }
            if (!snapshot.hasData) {
              return const Padding(
                padding: EdgeInsets.all(28),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final _SalesData data = snapshot.data!;
            final int peak = data.trend.fold<int>(
              0,
              (int max, OfflineDailySales day) =>
                  day.salesCount > max ? day.salesCount : max,
            );
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    _Metric(
                      icon: Icons.receipt_long_outlined,
                      label: s.invoicesCount,
                      value: '${data.summary.salesCount}',
                      color: const Color(0xFFDDEAFF),
                    ),
                    _Metric(
                      icon: Icons.payments_outlined,
                      label: s.revenueAfn,
                      value: data.summary.total.compact,
                      color: const Color(0xFFDAF5E6),
                    ),
                    _Metric(
                      icon: Icons.sync_rounded,
                      label: s.pendingSyncShort,
                      value: '${data.summary.pendingSync}',
                      color: const Color(0xFFE8E1FF),
                    ),
                  ],
                ),
                if (data.trend.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 12),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            s.dailyInvoices,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: 8),
                          ...data.trend.map(
                            (OfflineDailySales day) => Padding(
                              padding: const EdgeInsets.symmetric(vertical: 3),
                              child: Row(
                                children: <Widget>[
                                  SizedBox(
                                    width: 48,
                                    child: Text(
                                      day.businessDate.substring(5),
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodySmall,
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
                  ),
                ],
              ],
            );
          },
        ),
      ],
    );
  }
}

class _InventoryData {
  const _InventoryData(this.overview, this.cost);
  final InventoryOverview overview;
  final StockCostOverview cost;
}

class _InventoryReport extends StatelessWidget {
  const _InventoryReport({required this.database});

  final PharmacyDatabase database;

  @override
  Widget build(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    final OfflineCatalogBrowser browser = OfflineCatalogBrowser(database);
    return FutureBuilder<_InventoryData>(
      future: () async {
        final InventoryOverview overview = await browser.inventoryOverview(
          now: DateTime.now(),
        );
        final StockCostOverview cost = await browser.stockCostOverview(
          locationId: null,
        );
        return _InventoryData(overview, cost);
      }(),
      builder: (BuildContext context, AsyncSnapshot<_InventoryData> snapshot) {
        if (snapshot.hasError) {
          return _Notice(message: s.unableToReadInventory);
        }
        if (!snapshot.hasData) {
          return const Padding(
            padding: EdgeInsets.all(28),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        final _InventoryData data = snapshot.data!;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                _Metric(
                  icon: Icons.medication_outlined,
                  label: s.metricMedicines,
                  value: '${data.overview.medicines}',
                  color: const Color(0xFFDDEAFF),
                ),
                _Metric(
                  icon: Icons.inventory_2_outlined,
                  label: s.metricOutOfStock,
                  value: '${data.overview.outOfStock}',
                  color: const Color(0xFFFFEED4),
                ),
                _Metric(
                  icon: Icons.schedule_outlined,
                  label: s.metricExpiring30,
                  value: '${data.overview.expiringBatches}',
                  color: const Color(0xFFFFF3D9),
                ),
                _Metric(
                  icon: Icons.event_busy_outlined,
                  label: s.expiredBatches,
                  value: '${data.overview.expiredBatches}',
                  color: const Color(0xFFFFE6DF),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Card(
              child: ListTile(
                leading: const Icon(Icons.payments_outlined),
                title: Text(s.acquisitionValue),
                subtitle: Text(
                  '${data.cost.batchCount} ${s.acquisitionSubtitle}',
                ),
                trailing: Text(
                  'AFN ${data.cost.acquisitionValue.compact}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _ExpiryReport extends StatelessWidget {
  const _ExpiryReport({required this.database});

  final PharmacyDatabase database;

  @override
  Widget build(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    final OfflineCatalogBrowser browser = OfflineCatalogBrowser(database);
    return FutureBuilder<List<StockMedicineSnapshot>>(
      future: browser.medicines(
        search: '',
        locationId: null,
        page: 0,
        expiringOnly: true,
      ),
      builder:
          (
            BuildContext context,
            AsyncSnapshot<List<StockMedicineSnapshot>> snapshot,
          ) {
            if (snapshot.hasError) {
              return _Notice(message: s.unableToReadExpiry);
            }
            if (!snapshot.hasData) {
              return const Padding(
                padding: EdgeInsets.all(28),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final List<_BatchRow> rows = <_BatchRow>[
              for (final StockMedicineSnapshot item in snapshot.data!)
                for (final StockBatchSnapshot batch in item.batches)
                  _BatchRow(medicine: item, batch: batch),
            ];
            if (rows.isEmpty) {
              return _Notice(message: s.noExpiryOnDevice);
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
                          ' · ${s.expires} ${_formatDateOrDash(row.batch.expiresAt)}'
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
          },
    );
  }
}

class _CustomerReport extends StatelessWidget {
  const _CustomerReport({required this.database});

  final PharmacyDatabase database;

  @override
  Widget build(BuildContext context) {
    final ModuleStrings s = ModuleStrings.of(context);
    final OfflineCatalogBrowser browser = OfflineCatalogBrowser(database);
    return FutureBuilder<List<CustomerSnapshot>>(
      future: browser.customers(search: '', page: 0),
      builder:
          (BuildContext context, AsyncSnapshot<List<CustomerSnapshot>> snapshot) {
            if (snapshot.hasError) {
              return _Notice(message: s.unableToReadCustomers);
            }
            if (!snapshot.hasData) {
              return const Padding(
                padding: EdgeInsets.all(28),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final List<CustomerSnapshot> customers = snapshot.data!;
            if (customers.isEmpty) {
              return _Notice(message: s.noCustomersOnDevice);
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: customers
                  .map(
                    (CustomerSnapshot customer) => Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: const CircleAvatar(
                          child: Icon(Icons.person_outline),
                        ),
                        title: Text(
                          customer.name,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          customer.phone.isEmpty ? '—' : customer.phone,
                        ),
                        trailing: Text(
                          'AFN ${customer.balance}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                    ),
                  )
                  .toList(growable: false),
            );
          },
    );
  }
}

class _BatchRow {
  const _BatchRow({required this.medicine, required this.batch});
  final StockMedicineSnapshot medicine;
  final StockBatchSnapshot batch;
}

String _formatDateOrDash(DateTime? value) {
  if (value == null) return '—';
  return _formatDate(value);
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
