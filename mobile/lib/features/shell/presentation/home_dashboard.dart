import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/features/catalog/data/offline_catalog_browser.dart';
import 'package:businessos_pharmacy/features/reports/data/offline_sales_summary.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Compact, real-data home dashboard following the approved reference layout.
///
/// Only figures the device can compute from local data are shown. Profit and
/// receivables are intentionally omitted until those calculations exist.
class HomeDashboard extends ConsumerStatefulWidget {
  const HomeDashboard({required this.onNavigate, super.key});

  /// Called with the bottom-navigation tab index and an optional More section.
  final void Function(int tab, String? section) onNavigate;

  @override
  ConsumerState<HomeDashboard> createState() => _HomeDashboardState();
}

class _HomeDashboardState extends ConsumerState<HomeDashboard> {
  int _refreshVersion = 0;

  static String _formatDate(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-'
      '${value.month.toString().padLeft(2, '0')}-'
      '${value.day.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final registration = ref.watch(mobileRegistrationProvider);
    final database = registration.asData?.value == null
        ? null
        : ref.watch(pharmacyDatabaseProvider);
    final now = DateTime.now();
    final today = _formatDate(now);
    final text = Theme.of(context).textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                'Dashboard',
                style: text.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(today, style: text.bodySmall),
            IconButton(
              key: const ValueKey('dashboard-refresh'),
              tooltip: 'Refresh',
              onPressed: () => setState(() => _refreshVersion++),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (database == null)
          const LinearProgressIndicator()
        else
          database.when(
            loading: () => const LinearProgressIndicator(),
            error: (Object error, StackTrace trace) =>
                const Text('Local dashboard unavailable'),
            data: (db) => FutureBuilder<_DashboardData>(
              key: ValueKey('dashboard-$_refreshVersion-$today'),
              future: () async {
                final OfflineSalesSummary summary =
                    await OfflineSalesSummaryRepository(db)
                        .forBusinessDate(today);
                final InventoryOverview overview =
                    await OfflineCatalogBrowser(db).inventoryOverview(now: now);
                return _DashboardData(summary, overview);
              }(),
              builder:
                  (
                    BuildContext context,
                    AsyncSnapshot<_DashboardData> snapshot,
                  ) {
                    if (snapshot.hasError) {
                      return const Text('Unable to load dashboard');
                    }
                    if (!snapshot.hasData) {
                      return const LinearProgressIndicator();
                    }
                    return _buildKpis(context, snapshot.data!);
                  },
            ),
          ),
        const SizedBox(height: 16),
        Text(
          'Quick access',
          style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 10),
        _buildQuickAccess(context),
      ],
    );
  }

  Widget _buildKpis(BuildContext context, _DashboardData data) {
    final summary = data.summary;
    final overview = data.overview;
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 1.7,
      children: <Widget>[
        _KpiTile(
          label: 'Sales today',
          value: '${summary.total.compact} AFN',
          icon: Icons.payments_outlined,
          color: const Color(0xFF1E9E5A),
          background: const Color(0xFFDAF5E6),
        ),
        _KpiTile(
          label: 'Invoices',
          value: '${summary.salesCount}',
          icon: Icons.receipt_long_outlined,
          color: const Color(0xFF1557C0),
          background: const Color(0xFFDDEAFF),
        ),
        _KpiTile(
          label: 'Low stock',
          value: '${overview.outOfStock}',
          icon: Icons.inventory_2_outlined,
          color: const Color(0xFFE38A1B),
          background: const Color(0xFFFFE9D6),
        ),
        _KpiTile(
          label: 'Expiring soon',
          value: '${overview.expiringBatches}',
          icon: Icons.event_busy_outlined,
          color: const Color(0xFFD93A3A),
          background: const Color(0xFFFDE4E4),
        ),
        _KpiTile(
          label: 'Expired batches',
          value: '${overview.expiredBatches}',
          icon: Icons.warning_amber_rounded,
          color: const Color(0xFFD93A3A),
          background: const Color(0xFFFDE4E4),
        ),
        _KpiTile(
          label: 'Pending sync',
          value: '${summary.pendingSync}',
          icon: Icons.sync_rounded,
          color: const Color(0xFF7A4FD6),
          background: const Color(0xFFE8E1FF),
        ),
      ],
    );
  }

  Widget _buildQuickAccess(BuildContext context) {
    const List<({IconData icon, String label, int tab, String? section})>
    links = <({IconData icon, String label, int tab, String? section})>[
      (icon: Icons.point_of_sale_rounded, label: 'POS', tab: 1, section: null),
      (icon: Icons.medication_rounded, label: 'Medicines', tab: 2, section: null),
      (icon: Icons.inventory_2_rounded, label: 'Inventory', tab: 2, section: null),
      (icon: Icons.bar_chart_rounded, label: 'Reports', tab: 3, section: null),
      (
        icon: Icons.shopping_bag_outlined,
        label: 'Purchases',
        tab: 4,
        section: 'Purchases',
      ),
      (
        icon: Icons.people_alt_rounded,
        label: 'Customers',
        tab: 4,
        section: 'Customers',
      ),
      (
        icon: Icons.notifications_active_outlined,
        label: 'Alerts',
        tab: 4,
        section: 'Alerts',
      ),
      (icon: Icons.sync_rounded, label: 'Sync', tab: 4, section: 'Sync'),
    ];

    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 4,
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 0.9,
      children: links
          .map(
            (link) => Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: () => widget.onNavigate(link.tab, link.section),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Icon(link.icon, color: const Color(0xFF1557C0)),
                    const SizedBox(height: 6),
                    Text(
                      link.label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          )
          .toList(growable: false),
    );
  }
}

class _DashboardData {
  const _DashboardData(this.summary, this.overview);
  final OfflineSalesSummary summary;
  final InventoryOverview overview;
}

class _KpiTile extends StatelessWidget {
  const _KpiTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    required this.background,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, size: 18, color: color),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, color: color),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: Color(0xFF1B2A41),
            ),
          ),
        ],
      ),
    );
  }
}
