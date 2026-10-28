import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/features/alerts/domain/operational_alert.dart';
import 'package:businessos_pharmacy/features/dashboard/data/local_dashboard_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Home overview uses the same registration and local data as POS and Stock.
/// It never invents pharmacy-wide sales or loads another database.
class DarmaltoonDashboard extends ConsumerWidget {
  const DarmaltoonDashboard({required this.onNavigate, super.key});

  /// 1 = POS, 2 = Inventory, 3 = Customers, 4 = More/Sync.
  final ValueChanged<int> onNavigate;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final _DashboardText t = _DashboardText.of(context);
    final AsyncValue<MobileRegistration?> registration =
        ref.watch(mobileRegistrationProvider);

    return registration.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object error, StackTrace stack) => Text(error.toString()),
      data: (MobileRegistration? user) {
        if (user == null) {
          return _EmptyDashboard(
            title: t.welcome,
            description: t.registerFirst,
            action: t.configure,
            onPressed: () => onNavigate(4),
          );
        }

        final bool canSell = user.permissions.contains('pos.sell');
        final bool canManageStock =
            canSell || user.permissions.contains('inventory.manage');
        final bool canSeeCustomers =
            canSell || user.permissions.contains('customers.manage');
        if (!canSell && !canManageStock && !canSeeCustomers) {
          return _EmptyDashboard(
            title: user.tenantName,
            description: t.noAccess,
            action: t.configure,
            onPressed: () => onNavigate(4),
          );
        }

        return ref.watch(pharmacyDatabaseProvider).when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (Object error, StackTrace stack) => Text(error.toString()),
          data: (PharmacyDatabase database) => _DashboardContents(
            key: ValueKey<String>(user.tenantId),
            registration: user,
            database: database,
            onNavigate: onNavigate,
            canSell: canSell,
            canManageStock: canManageStock,
            canSeeCustomers: canSeeCustomers,
          ),
        );
      },
    );
  }
}

class _DashboardContents extends StatefulWidget {
  const _DashboardContents({
    required this.registration,
    required this.database,
    required this.onNavigate,
    required this.canSell,
    required this.canManageStock,
    required this.canSeeCustomers,
    super.key,
  });

  final MobileRegistration registration;
  final PharmacyDatabase database;
  final ValueChanged<int> onNavigate;
  final bool canSell;
  final bool canManageStock;
  final bool canSeeCustomers;

  @override
  State<_DashboardContents> createState() => _DashboardContentsState();
}

class _DashboardContentsState extends State<_DashboardContents> {
  late Future<LocalDashboardSnapshot> _snapshot;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  void _refresh() {
    setState(() {
      _snapshot = LocalDashboardRepository(
        widget.database,
        lowStockThreshold: widget.registration.lowStockThreshold,
        nearExpiryDays: widget.registration.nearExpiryDays,
      ).snapshot();
    });
  }

  @override
  Widget build(BuildContext context) {
    final _DashboardText t = _DashboardText.of(context);
    final ThemeData theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: <Color>[Color(0xFF075A9C), Color(0xFF1395C0)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Row(
            children: <Widget>[
              CircleAvatar(
                radius: 22,
                backgroundColor: Colors.white.withValues(alpha: 0.18),
                child: const Icon(Icons.local_pharmacy_rounded, color: Colors.white),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      t.greeting(widget.registration.userName),
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      widget.registration.tenantName,
                      style: const TextStyle(color: Color(0xFFE4F5FF)),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: t.refresh,
                onPressed: _refresh,
                color: Colors.white,
                icon: const Icon(Icons.refresh_rounded),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                t.overview,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              t.localScope,
              style: theme.textTheme.labelSmall?.copyWith(
                color: const Color(0xFF64748B),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        FutureBuilder<LocalDashboardSnapshot>(
          future: _snapshot,
          builder: (BuildContext context, AsyncSnapshot<LocalDashboardSnapshot> state) {
            if (state.hasError) {
              return _EmptyDashboard(
                title: t.unavailable,
                description: state.error.toString(),
                action: t.refresh,
                onPressed: _refresh,
              );
            }
            if (!state.hasData) {
              return const Padding(
                padding: EdgeInsets.symmetric(vertical: 28),
                child: Center(child: CircularProgressIndicator()),
              );
            }

            final LocalDashboardSnapshot data = state.data!;
            final List<_Metric> metrics = <_Metric>[
              if (widget.canSell)
                _Metric(t.todaySales, data.salesToday.toString(),
                    Icons.receipt_long_rounded, const Color(0xFF0E82C4)),
              if (widget.canSell)
                _Metric(t.todayRevenue, 'AFN ${data.salesTodayAmount.compact}',
                    Icons.payments_outlined, const Color(0xFF078768)),
              if (widget.canManageStock)
                _Metric(t.medicines, data.cachedMedicines.toString(),
                    Icons.medication_outlined, const Color(0xFF7565D8)),
              if (widget.canSeeCustomers)
                _Metric(t.customers, data.cachedCustomers.toString(),
                    Icons.people_outline_rounded, const Color(0xFF3B82F6)),
              _Metric(t.pendingSync, data.pendingSync.toString(),
                  Icons.cloud_upload_outlined, const Color(0xFFD8891C)),
              if (widget.canManageStock)
                _Metric(t.stockAlerts, data.alerts.total.toString(),
                    Icons.notification_important_outlined, const Color(0xFFC44757)),
            ];

            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                LayoutBuilder(
                  builder: (BuildContext context, BoxConstraints constraints) {
                    final double width = (constraints.maxWidth - 12) / 2;
                    return Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: metrics.map((_Metric metric) => SizedBox(
                        width: width,
                        child: _MetricTile(metric: metric),
                      )).toList(growable: false),
                    );
                  },
                ),
                const SizedBox(height: 24),
                Text(t.quickActions, style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                )),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: <Widget>[
                    if (widget.canSell)
                      _ActionChip(label: t.newSale, icon: Icons.add_shopping_cart_rounded,
                          onTap: () => widget.onNavigate(1)),
                    if (widget.canManageStock)
                      _ActionChip(label: t.inventory, icon: Icons.inventory_2_outlined,
                          onTap: () => widget.onNavigate(2)),
                    if (widget.canSeeCustomers)
                      _ActionChip(label: t.customers, icon: Icons.people_outline_rounded,
                          onTap: () => widget.onNavigate(3)),
                    _ActionChip(label: t.syncNow, icon: Icons.sync_rounded,
                        onTap: () => widget.onNavigate(4)),
                  ],
                ),
                if (widget.canManageStock && data.alerts.total > 0) ...<Widget>[
                  const SizedBox(height: 24),
                  Text(t.attention, style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  )),
                  const SizedBox(height: 8),
                  Card(
                    child: Column(
                      children: data.alerts.alerts.take(3).map((LocalOperationalAlert alert) =>
                        ListTile(
                          dense: true,
                          leading: Icon(
                            alert.severity == OperationalAlertSeverity.critical
                                ? Icons.error_outline_rounded
                                : Icons.warning_amber_rounded,
                            color: alert.severity == OperationalAlertSeverity.critical
                                ? const Color(0xFFC44757)
                                : const Color(0xFFD8891C),
                          ),
                          title: Text(alert.title, maxLines: 1,
                              overflow: TextOverflow.ellipsis),
                          subtitle: Text(alert.detail, maxLines: 2,
                              overflow: TextOverflow.ellipsis),
                        ),
                      ).toList(growable: false),
                    ),
                  ),
                ],
                if (data.rejectedSync > 0) ...<Widget>[
                  const SizedBox(height: 12),
                  Card(
                    color: const Color(0xFFFFF4F1),
                    child: ListTile(
                      leading: const Icon(Icons.sync_problem, color: Color(0xFFB42318)),
                      title: Text(t.syncConflicts(data.rejectedSync)),
                      onTap: () => widget.onNavigate(4),
                      trailing: const Icon(Icons.chevron_right),
                    ),
                  ),
                ],
                const SizedBox(height: 18),
                Text(t.scopeNotice, style: theme.textTheme.bodySmall),
              ],
            );
          },
        ),
      ],
    );
  }
}

class _Metric {
  const _Metric(this.label, this.value, this.icon, this.color);

  final String label;
  final String value;
  final IconData icon;
  final Color color;
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.metric});

  final _Metric metric;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Container(
              padding: const EdgeInsets.all(9),
              decoration: BoxDecoration(
                color: metric.color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(metric.icon, color: metric.color, size: 23),
            ),
            const SizedBox(height: 11),
            Text(metric.value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                )),
            const SizedBox(height: 3),
            Text(metric.label, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

class _ActionChip extends StatelessWidget {
  const _ActionChip({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ActionChip(
    avatar: Icon(icon, size: 19, color: Theme.of(context).colorScheme.primary),
    label: Text(label),
    onPressed: onTap,
  );
}

class _EmptyDashboard extends StatelessWidget {
  const _EmptyDashboard({
    required this.title,
    required this.description,
    required this.action,
    required this.onPressed,
  });

  final String title;
  final String description;
  final String action;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: <Widget>[
          const Icon(Icons.local_pharmacy_rounded, size: 42),
          const SizedBox(height: 12),
          Text(title, style: Theme.of(context).textTheme.titleLarge,
              textAlign: TextAlign.center),
          const SizedBox(height: 8),
          Text(description, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton(onPressed: onPressed, child: Text(action)),
        ],
      ),
    ),
  );
}

class _DashboardText {
  const _DashboardText(this.locale);

  final AppLocale locale;

  static _DashboardText of(BuildContext context) =>
      _DashboardText(AppLocale.fromCode(Localizations.localeOf(context).languageCode));

  String get welcome => switch (locale) {
    AppLocale.dari => 'به درملتون خوش آمدید',
    AppLocale.pashto => 'درملتون ته ښه راغلاست',
    _ => 'Welcome to Darmaltoon',
  };
  String greeting(String user) => switch (locale) {
    AppLocale.dari => 'سلام، $user',
    AppLocale.pashto => 'سلام، $user',
    _ => 'Hello, $user',
  };
  String get overview => switch (locale) {
    AppLocale.dari => 'نمای کلی',
    AppLocale.pashto => 'عمومي کتنه',
    _ => 'Overview',
  };
  String get localScope => switch (locale) {
    AppLocale.dari => 'این دستگاه',
    AppLocale.pashto => 'دا وسیله',
    _ => 'This device',
  };
  String get todaySales => switch (locale) {
    AppLocale.dari => 'فروشات امروز',
    AppLocale.pashto => 'د نن ورځې پلور',
    _ => 'Sales today',
  };
  String get todayRevenue => switch (locale) {
    AppLocale.dari => 'مبلغ امروز',
    AppLocale.pashto => 'د نن ورځې عاید',
    _ => 'Today’s sales amount',
  };
  String get medicines => switch (locale) {
    AppLocale.dari => 'ادویه',
    AppLocale.pashto => 'درمل',
    _ => 'Medicines',
  };
  String get customers => switch (locale) {
    AppLocale.dari => 'مشتریان',
    AppLocale.pashto => 'پېرودونکي',
    _ => 'Customers',
  };
  String get pendingSync => switch (locale) {
    AppLocale.dari => 'در انتظار همگام‌سازی',
    AppLocale.pashto => 'د همغږۍ په تمه',
    _ => 'Pending sync',
  };
  String get stockAlerts => switch (locale) {
    AppLocale.dari => 'هشدارها',
    AppLocale.pashto => 'خبرتیاوې',
    _ => 'Alerts',
  };
  String get quickActions => switch (locale) {
    AppLocale.dari => 'عملیات سریع',
    AppLocale.pashto => 'چټکې کړنې',
    _ => 'Quick actions',
  };
  String get newSale => switch (locale) {
    AppLocale.dari => 'فروش جدید',
    AppLocale.pashto => 'نوی پلور',
    _ => 'New sale',
  };
  String get inventory => switch (locale) {
    AppLocale.dari => 'موجودی',
    AppLocale.pashto => 'ذخیره',
    _ => 'Inventory',
  };
  String get syncNow => switch (locale) {
    AppLocale.dari => 'همگام‌سازی',
    AppLocale.pashto => 'همغږي',
    _ => 'Sync',
  };
  String get attention => switch (locale) {
    AppLocale.dari => 'نیاز به توجه',
    AppLocale.pashto => 'پاملرنې ته اړتیا',
    _ => 'Needs attention',
  };
  String get refresh => switch (locale) {
    AppLocale.dari => 'تازه کردن',
    AppLocale.pashto => 'تازه کول',
    _ => 'Refresh',
  };
  String get configure => switch (locale) {
    AppLocale.dari => 'تنظیمات دستگاه',
    AppLocale.pashto => 'د وسیلې تنظیمات',
    _ => 'Device setup',
  };
  String get registerFirst => switch (locale) {
    AppLocale.dari => 'برای مشاهده اطلاعات دواخانه نخست دستگاه را ثبت کنید.',
    AppLocale.pashto => 'د درملتون د معلوماتو لپاره لومړی وسیله ثبت کړئ.',
    _ => 'Register this device to access your pharmacy’s data.',
  };
  String get noAccess => switch (locale) {
    AppLocale.dari => 'حساب شما اجازه مشاهده این اطلاعات را ندارد.',
    AppLocale.pashto => 'ستاسو حساب د دې معلوماتو اجازه نه لري.',
    _ => 'Your account has no permission to view this overview.',
  };
  String get unavailable => switch (locale) {
    AppLocale.dari => 'اطلاعات فعلاً در دسترس نیست',
    AppLocale.pashto => 'معلومات اوس نشته',
    _ => 'Overview unavailable',
  };
  String syncConflicts(int count) => switch (locale) {
    AppLocale.dari => '$count مورد اختلاف همگام‌سازی',
    AppLocale.pashto => '$count د همغږۍ ستونزې',
    _ => '$count sync conflicts require attention',
  };
  String get scopeNotice => switch (locale) {
    AppLocale.dari => 'فروشات تنها معاملات ثبت‌شده در این دستگاه است؛ اطلاعات دواخانه پس از همگام‌سازی تازه می‌شود.',
    AppLocale.pashto => 'د پلور شمېرې یوازې د دې وسیلې دي؛ د درملتون معلومات له همغږۍ وروسته تازه کېږي.',
    _ => 'Sales reflect transactions recorded on this device, not pharmacy-wide totals. Cached inventory and customer counts refresh after sync.',
  };
}
