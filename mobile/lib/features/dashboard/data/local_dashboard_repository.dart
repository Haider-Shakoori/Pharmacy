import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/features/alerts/data/operational_alert_repository.dart';
import 'package:businessos_pharmacy/features/alerts/domain/operational_alert.dart';
import 'package:drift/drift.dart';

/// Dashboard figures are from this device's tenant-scoped offline database.
/// They are NOT pharmacy-wide cloud reports.
class LocalDashboardSnapshot {
  const LocalDashboardSnapshot({
    required this.salesToday,
    required this.salesTodayAmount,
    required this.cachedMedicines,
    required this.cachedCustomers,
    required this.pendingSync,
    required this.rejectedSync,
    required this.alerts,
  });

  final int salesToday;
  final FixedDecimal salesTodayAmount;
  final int cachedMedicines;
  final int cachedCustomers;
  final int pendingSync;
  final int rejectedSync;
  final OperationalAlertSnapshot alerts;
}

class LocalDashboardRepository {
  const LocalDashboardRepository(
    this._database, {
    required this.lowStockThreshold,
    required this.nearExpiryDays,
  });

  final PharmacyDatabase _database;
  final int lowStockThreshold;
  final int nearExpiryDays;

  Future<LocalDashboardSnapshot> snapshot({DateTime? now}) async {
    final DateTime date = now ?? DateTime.now();
    final String businessDate =
        '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';

    final List<QueryRow> sales = await _database.customSelect(
      '''
      SELECT total FROM local_sales
      WHERE business_date = ? AND status = 'completed'
      ''',
      variables: <Variable<Object>>[Variable<String>(businessDate)],
    ).get();

    FixedDecimal salesTotal = FixedDecimal.zero;
    for (final QueryRow sale in sales) {
      salesTotal += FixedDecimal.parse(sale.read<String>('total'));
    }

    Future<int> count(String table, String where) async {
      final QueryRow row = await _database.customSelect(
        'SELECT COUNT(*) AS total FROM $table WHERE $where',
      ).getSingle();
      return row.read<int>('total');
    }

    final int medicines = await count(
      'local_medicines',
      'is_active = 1 AND is_deleted = 0',
    );
    final int customers = await count('local_customers', 'is_deleted = 0');
    final int pending = await count(
      'sync_outbox_entries',
      "status IN ('pending', 'retry')",
    );
    final int rejected = await count(
      'sync_outbox_entries',
      "status = 'rejected'",
    );
    final OperationalAlertSnapshot alerts = await OperationalAlertRepository(
      _database,
      lowStockThreshold: lowStockThreshold,
      nearExpiryDays: nearExpiryDays,
    ).snapshot();

    return LocalDashboardSnapshot(
      salesToday: sales.length,
      salesTodayAmount: salesTotal,
      cachedMedicines: medicines,
      cachedCustomers: customers,
      pendingSync: pending,
      rejectedSync: rejected,
      alerts: alerts,
    );
  }
}
