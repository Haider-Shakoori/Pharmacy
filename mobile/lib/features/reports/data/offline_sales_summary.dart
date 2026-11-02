import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:drift/drift.dart';

/// Totals for completed sales on this device, not tenant-wide figures.
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

class OfflineDailySales {
  const OfflineDailySales({
    required this.businessDate,
    required this.salesCount,
    required this.total,
  });

  final String businessDate;
  final int salesCount;
  final FixedDecimal total;
}

class OfflineSalesSummaryRepository {
  const OfflineSalesSummaryRepository(this.database);

  final PharmacyDatabase database;

  Future<OfflineSalesSummary> forBusinessDate(String date) =>
      forBusinessDateRange(date, date);

  /// Both business dates are inclusive and must be ISO-formatted.
  Future<OfflineSalesSummary> forBusinessDateRange(
    String startDate,
    String endDate,
  ) async {
    final RegExp datePattern = RegExp(r'^\d{4}-\d{2}-\d{2}$');
    if (!datePattern.hasMatch(startDate) ||
        !datePattern.hasMatch(endDate)) {
      throw ArgumentError('Dates must use YYYY-MM-DD');
    }
    if (startDate.compareTo(endDate) > 0) {
      throw ArgumentError('Start date must not be after end date');
    }

    final List<QueryRow> rows = await database.customSelect(
      '''
      SELECT total, sync_state
      FROM local_sales
      WHERE status = 'completed'
        AND business_date >= ?
        AND business_date <= ?
      ''',
      variables: <Variable<Object>>[
        Variable<String>(startDate),
        Variable<String>(endDate),
      ],
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
  /// One point per calendar date, including zero-sale days, for device-local trends.
  Future<List<OfflineDailySales>> dailyTrend(
    String startDate,
    String endDate,
  ) async {
    final DateTime? start = DateTime.tryParse(startDate);
    final DateTime? end = DateTime.tryParse(endDate);
    if (start == null || end == null ||
        startDate.length != 10 || endDate.length != 10 ||
        start.isAfter(end) || end.difference(start).inDays > 365) {
      throw ArgumentError('Invalid sales trend date range');
    }
    final List<QueryRow> rows = await database.customSelect(
      '''
      SELECT business_date, total
      FROM local_sales
      WHERE status = 'completed'
        AND business_date >= ? AND business_date <= ?
      ORDER BY business_date
      ''',
      variables: <Variable<Object>>[
        Variable<String>(startDate),
        Variable<String>(endDate),
      ],
    ).get();
    final Map<String, List<FixedDecimal>> totals =
        <String, List<FixedDecimal>>{};
    for (final QueryRow row in rows) {
      final String date = row.read<String>('business_date');
      totals.putIfAbsent(date, () => <FixedDecimal>[]).add(
        FixedDecimal.parse(row.read<String>('total')),
      );
    }
    final List<OfflineDailySales> result = <OfflineDailySales>[];
    for (DateTime day = start;
        !day.isAfter(end);
        day = DateTime(day.year, day.month, day.day + 1)) {
      final String date =
          '${day.year.toString().padLeft(4, '0')}-'
          '${day.month.toString().padLeft(2, '0')}-'
          '${day.day.toString().padLeft(2, '0')}';
      final List<FixedDecimal> values = totals[date] ?? <FixedDecimal>[];
      FixedDecimal sum = FixedDecimal.zero;
      for (final FixedDecimal value in values) {
        sum += value;
      }
      result.add(OfflineDailySales(
        businessDate: date,
        salesCount: values.length,
        total: sum,
      ));
    }
    return result;
  }

}
