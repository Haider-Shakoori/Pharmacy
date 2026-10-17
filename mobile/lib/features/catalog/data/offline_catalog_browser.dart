import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:drift/drift.dart';

/// Read-only, tenant-scoped catalog snapshots. All writes are handled by the
/// existing sync engine and POS service, never by a catalog browsing screen.
class OfflineCatalogBrowser {
  const OfflineCatalogBrowser(this._database);

  final PharmacyDatabase _database;

  Future<List<String>> stockLocations() async {
    final List<QueryRow> rows = await _database.customSelect('''
      SELECT DISTINCT stock_location_id
      FROM local_inventory_batches
      WHERE is_deleted = 0
      ORDER BY stock_location_id
      ''').get();
    return rows
        .map((QueryRow row) => row.read<String>('stock_location_id'))
        .toList(growable: false);
  }

  Future<List<StockMedicineSnapshot>> medicines({
    String search = '',
    String? locationId,
    DateTime? now,
  }) async {
    final String needle = '%${search.trim()}%';
    final List<QueryRow> medicines = await _database
        .customSelect(
          '''
      SELECT id, medicine_code, brand_name, generic_name, sale_unit
      FROM local_medicines
      WHERE is_deleted = 0
        AND is_active = 1
        AND (brand_name LIKE ? OR COALESCE(generic_name, '') LIKE ?
             OR COALESCE(medicine_code, '') LIKE ?)
      ORDER BY brand_name COLLATE NOCASE, id
      LIMIT 100
      ''',
          variables: <Variable<Object>>[
            Variable<String>(needle),
            Variable<String>(needle),
            Variable<String>(needle),
          ],
        )
        .get();

    final DateTime timestamp = now ?? DateTime.now();
    final DateTime today = DateTime(
      timestamp.year,
      timestamp.month,
      timestamp.day,
    );
    final List<StockMedicineSnapshot> results = <StockMedicineSnapshot>[];

    for (final QueryRow medicine in medicines) {
      final String id = medicine.read<String>('id');
      final List<Variable<Object>> args = <Variable<Object>>[
        Variable<String>(id),
      ];
      String locationCondition = '';
      if (locationId != null) {
        locationCondition = 'AND stock_location_id = ?';
        args.add(Variable<String>(locationId));
      }

      final List<QueryRow> batches = await _database.customSelect('''
        SELECT id, batch_number, stock_location_id, available_quantity,
               sale_price, status, expires_at
        FROM local_inventory_batches
        WHERE medicine_id = ? AND is_deleted = 0 $locationCondition
        ORDER BY expires_at IS NULL, expires_at, id
        ''', variables: args).get();

      FixedDecimal available = FixedDecimal.zero;
      final List<StockBatchSnapshot> lots = <StockBatchSnapshot>[];
      for (final QueryRow batch in batches) {
        final int? seconds = batch.readNullable<int>('expires_at');
        final DateTime? expiresAt = seconds == null
            ? null
            : DateTime.fromMillisecondsSinceEpoch(seconds * 1000);
        final String status = batch.read<String>('status');
        final FixedDecimal quantity = FixedDecimal.parse(
          batch.read<String>('available_quantity'),
        );
        final bool expired = expiresAt != null && expiresAt.isBefore(today);
        if (status == 'active' && !expired && quantity.isPositive) {
          available += quantity;
        }
        lots.add(
          StockBatchSnapshot(
            id: batch.read<String>('id'),
            batchNumber: batch.read<String>('batch_number'),
            locationId: batch.read<String>('stock_location_id'),
            quantity: quantity,
            salePrice: batch.read<String>('sale_price'),
            status: status,
            expiresAt: expiresAt,
            expired: expired,
          ),
        );
      }

      results.add(
        StockMedicineSnapshot(
          id: id,
          medicineCode: medicine.readNullable<String>('medicine_code') ?? '',
          name: medicine.read<String>('brand_name'),
          genericName: medicine.readNullable<String>('generic_name') ?? '',
          saleUnit: medicine.read<String>('sale_unit'),
          available: available,
          batches: lots,
        ),
      );
    }
    return results;
  }

  Future<List<CustomerSnapshot>> customers({String search = ''}) async {
    final String pattern = '%${search.trim()}%';
    final List<QueryRow> rows = await _database
        .customSelect(
          '''
      SELECT id, name, phone, balance
      FROM local_customers
      WHERE is_deleted = 0
        AND (name LIKE ? OR COALESCE(phone, '') LIKE ?)
      ORDER BY name COLLATE NOCASE, id
      LIMIT 100
      ''',
          variables: <Variable<Object>>[
            Variable<String>(pattern),
            Variable<String>(pattern),
          ],
        )
        .get();
    return rows
        .map(
          (QueryRow row) => CustomerSnapshot(
            id: row.read<String>('id'),
            name: row.read<String>('name'),
            phone: row.readNullable<String>('phone') ?? '',
            balance: row.read<String>('balance'),
          ),
        )
        .toList(growable: false);
  }
}

class StockMedicineSnapshot {
  const StockMedicineSnapshot({
    required this.id,
    required this.medicineCode,
    required this.name,
    required this.genericName,
    required this.saleUnit,
    required this.available,
    required this.batches,
  });

  final String id;
  final String medicineCode;
  final String name;
  final String genericName;
  final String saleUnit;
  final FixedDecimal available;
  final List<StockBatchSnapshot> batches;
}

class StockBatchSnapshot {
  const StockBatchSnapshot({
    required this.id,
    required this.batchNumber,
    required this.locationId,
    required this.quantity,
    required this.salePrice,
    required this.status,
    required this.expiresAt,
    required this.expired,
  });

  final String id;
  final String batchNumber;
  final String locationId;
  final FixedDecimal quantity;
  final String salePrice;
  final String status;
  final DateTime? expiresAt;
  final bool expired;
}

class CustomerSnapshot {
  const CustomerSnapshot({
    required this.id,
    required this.name,
    required this.phone,
    required this.balance,
  });

  final String id;
  final String name;
  final String phone;
  final String balance;
}
