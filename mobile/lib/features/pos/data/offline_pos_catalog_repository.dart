import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/features/pos/domain/offline_pos_models.dart';
import 'package:drift/drift.dart';

class OfflinePosCatalogRepository {
  const OfflinePosCatalogRepository(this._database);

  final PharmacyDatabase _database;

  Future<List<String>> stockLocationIds() async {
    final List<QueryRow> rows = await _database.customSelect(
      '''
      SELECT DISTINCT stock_location_id
      FROM local_inventory_batches
      WHERE is_deleted = 0
        AND status = 'active'
      ORDER BY stock_location_id
      ''',
    ).get();

    return rows
        .map((QueryRow row) => row.read<String>('stock_location_id'))
        .toList(growable: false);
  }

  Future<List<OfflinePosCatalogItem>> search({
    required String stockLocationId,
    String query = '',
    int limit = 30,
  }) async {
    final String needle = query.trim();
    final String pattern = '%$needle%';

    final medicineQuery = _database.select(_database.localMedicines)
      ..where(
        (table) =>
            table.isActive.equals(true) &
            table.isDeleted.equals(false) &
            (needle.isEmpty
                ? const Constant<bool>(true)
                : table.brandName.like(pattern) |
                      table.genericName.like(pattern) |
                      table.medicineCode.like(pattern)),
      )
      ..limit(limit);

    final medicines = await medicineQuery.get();
    medicines.sort((a, b) => a.brandName.compareTo(b.brandName));
    final List<OfflinePosCatalogItem> results = <OfflinePosCatalogItem>[];

    for (final medicine in medicines) {
      final batchQuery = _database.select(_database.localInventoryBatches)
        ..where(
          (table) =>
              table.medicineId.equals(medicine.id) &
              table.stockLocationId.equals(stockLocationId) &
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
      final DateTime startOfToday = DateTime(\n        today.year,\n        today.month,\n        today.day,\n      );

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
          stockLocationId: stockLocationId,
          salePrice: salePrice,
          availableQuantity: available,
        ),
      );
    }

    return results;
  }
}
