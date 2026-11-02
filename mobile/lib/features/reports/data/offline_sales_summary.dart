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

  Future<OfflineSalesSummary> forBusinessDate(String date) =>
      forBusinessDateRange(date, date);

  /// Inclusive date range using the same YYYY-MM-DD business date as POS.
  Future<OfflineSalesSummary> forBusinessDateRange(
    String startDate,
    String endDate,
  ) async {
    final RegExp isoDate = RegExp(r'^\\d{4}-\\d{2}-\\d{2}
      throw ArgumentError('Start date must not be after end date');
    }
    // Sum fixed-decimal strings in Dart to avoid floating-point currency drift.
    final List<QueryRow> rows = await database.customSelect(
      '''
      SELECT total, sync_state
      FROM local_sales
      WHERE status = 'completed' AND business_date >= ? AND business_date <= ?
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
}
);
    if (!isoDate.hasMatch(startDate) || !isoDate.hasMatch(endDate)) {
      throw ArgumentError('Dates must use YYYY-MM-DD');
    }
    if (startDate.compareTo(endDate) > 0) {
      throw ArgumentError('Start date must not be after end date');
    }
    // Sum fixed-decimal strings in Dart to avoid floating-point currency drift.
    final List<QueryRow> rows = await database.customSelect(
      '''
      SELECT total, sync_state
      FROM local_sales
      WHERE status = 'completed' AND business_date >= ? AND business_date <= ?
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
}
