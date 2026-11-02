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
    // Sum fixed-decimal strings in Dart to avoid floating-point currency drift.
    final List<QueryRow> rows = await database.customSelect(
      '''
      SELECT total, sync_state
      FROM local_sales
      WHERE status = 'completed' AND business_date = ?
      ''',
      variables: <Variable<Object>>[Variable<String>(date)],
    ).get();

    FixedDecimal total = FixedDecimal.zero;
    int pendingSync = 0;
    for (final QueryRow row in rows) {
      total += FixedDecimal.parse(row.read<String>('total'));
      if (row.read<String>('sync_state') != 'synced') {
        pendingSync++;
      }
    }

    return OfflineSalesSummary(
      salesCount: rows.length,
      total: total,
      pendingSync: pendingSync,
    );
  }
}
