import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/features/pos/domain/offline_pos_models.dart';
import 'package:drift/drift.dart';

class OfflinePosCatalogRepository {
  const OfflinePosCatalogRepository(this._database);

  final PharmacyDatabase _database;

  Future<List<String>> stockLocationIds() async {
    final List<QueryRow> rows = await _database.customSelect('''
      SELECT DISTINCT stock_location_id
      FROM local_inventory_batches
      WHERE is_deleted = 0
        AND status = 'active'
      ORDER BY stock_location_id
      ''').get();

    return rows
        .map((QueryRow row) => row.read<String>('stock_location_id'))
        .toList(growable: false);
  }

  Future<List<OfflinePosCatalogItem>> search({
    required String stockLocationId,
    String query = '',
    int limit = 30,
  }) async {
    if (limit < 1 || limit > 500) {
      throw RangeError.range(limit, 1, 500, 'limit');
    }
    if (stockLocationId.trim().isEmpty) {
      throw const FormatException('Stock location is required.');
    }
    final String locationId = stockLocationId.trim();
    final String needle = query.trim();
    // Escape LIKE metacharacters so a literal search cannot become a wildcard.
    final String escapedNeedle = needle
        .replaceAll(r'\', r'\\')
        .replaceAll('%', r'\%')
        .replaceAll('_', r'\_');
    final String pattern = '%$escapedNeedle%';

    final medicineQuery = _database.select(_database.localMedicines)
      ..where(
        (table) =>
            table.isActive.equals(true) &
            table.isDeleted.equals(false) &
            (needle.isEmpty
                ? const Constant<bool>(true)
                : table.brandName.like(pattern, escapeChar: r'\') |
                      table.genericName.like(pattern, escapeChar: r'\') |
                      table.medicineCode.like(pattern, escapeChar: r'\')),
      )
      ..orderBy([(table) => OrderingTerm(expression: table.brandName)]);

    final medicines = await medicineQuery.get();
    final List<OfflinePosCatalogItem> results = <OfflinePosCatalogItem>[];

    for (final medicine in medicines) {
      final batchQuery = _database.select(_database.localInventoryBatches)
        ..where(
          (table) =>
              table.medicineId.equals(medicine.id) &
              table.stockLocationId.equals(locationId) &
              table.status.equals('active') &
              table.isDeleted.equals(false),
        );

      final batches = await batchQuery.get();
      batches.sort((a, b) {
        final DateTime? left = a.expiresAt;
        final DateTime? right = b.expiresAt;

        if (left == null && right == null) {
          return a.id.compareTo(b.id);
        }
        if (left == null) {
          return 1;
        }
        if (right == null) {
          return -1;
        }

        return left.compareTo(right);
      });

      FixedDecimal available = FixedDecimal.zero;
      FixedDecimal? salePrice;
      final DateTime today = DateTime.now();
      final DateTime startOfToday = DateTime(
        today.year,
        today.month,
        today.day,
      );

      for (final batch in batches) {
        final FixedDecimal quantity = FixedDecimal.parse(
          batch.availableQuantity,
        );
        if (!quantity.isPositive) {
          continue;
        }

        final DateTime? expiry = batch.expiresAt;
        if (expiry != null && expiry.isBefore(startOfToday)) {
          continue;
        }

        available += quantity;
        salePrice ??= FixedDecimal.parse(batch.salePrice);
      }

      if (!available.isPositive || salePrice == null) {
        continue;
      }

      results.add(
        OfflinePosCatalogItem(
          medicineId: medicine.id,
          brandName: medicine.brandName,
          genericName: medicine.genericName,
          saleUnit: medicine.saleUnit,
          stockLocationId: locationId,
          salePrice: salePrice,
          availableQuantity: available,
        ),
      );
      if (results.length >= limit) {
        break;
      }
    }

    return results;
  }
}
