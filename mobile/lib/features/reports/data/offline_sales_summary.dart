import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:drift/drift.dart';

/// Local-only sales totals. These must not be presented as tenant-wide figures.
class OfflineSalesSummary {
  const OfflineSalesSummary({
    required this.salesCount,
    required this.total,
    required this.pendingSync,
  });

  final int salesCount;
  final FixedDecimal total;
  final int pendingSync;
}

class OfflineSalesSummaryRepository {
  const OfflineSalesSummaryRepository(this.database);

  final PharmacyDatabase database;

  Future<OfflineSalesSummary> forBusinessDate(String date) async {
    final QueryRow row = await database.customSelect(
      '''
      SELECT COUNT(*) AS sales_count,
             COALESCE(SUM(CAST(total AS REAL)), 0) AS sales_total,
             COALESCE(SUM(CASE WHEN sync_state != 'synced' THEN 1 ELSE 0 END), 0) AS pending_sync
      FROM local_sales
      WHERE status = 'completed' AND business_date = ?
      ''',
      variables: <Variable<Object>>[Variable<String>(date)],
    ).getSingle();

    return OfflineSalesSummary(
      salesCount: row.read<int>('sales_count'),
      total: FixedDecimal.parse(row.read<double>('sales_total').toStringAsFixed(4)),
      pendingSync: row.read<int>('pending_sync'),
    );
  }
}
