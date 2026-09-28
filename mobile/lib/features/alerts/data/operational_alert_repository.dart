import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/features/alerts/domain/operational_alert.dart';
import 'package:drift/drift.dart';

class OperationalAlertRepository {
  OperationalAlertRepository(
    this._database, {
    required this.lowStockThreshold,
    required this.nearExpiryDays,
    DateTime Function()? clock,
  }) : _clock = clock ?? DateTime.now;

  final PharmacyDatabase _database;
  final int lowStockThreshold;
  final int nearExpiryDays;
  final DateTime Function() _clock;

  Future<OperationalAlertSnapshot> snapshot() async {
    final DateTime now = _clock();
    final DateTime today = DateTime(now.year, now.month, now.day);
    final DateTime nearExpiryEnd = today.add(Duration(days: nearExpiryDays));
    final int todaySeconds = today.millisecondsSinceEpoch ~/ 1000;
    final int endSeconds = nearExpiryEnd.millisecondsSinceEpoch ~/ 1000;

    final List<QueryRow> medicines = await _database.customSelect(
      '''
      SELECT id, brand_name
      FROM local_medicines
      WHERE is_active = 1 AND is_deleted = 0
      ORDER BY brand_name
      ''',
    ).get();

    final List<QueryRow> batches = await _database.customSelect(
      '''
      SELECT id, medicine_id, batch_number, available_quantity, expires_at
      FROM local_inventory_batches
      WHERE status = 'active' AND is_deleted = 0
      ORDER BY expires_at, id
      ''',
    ).get();

    final Map<String, FixedDecimal> sellable = <String, FixedDecimal>{
      for (final QueryRow medicine in medicines)
        medicine.read<String>('id'): FixedDecimal.zero,
    };
    final Map<String, String> names = <String, String>{
      for (final QueryRow medicine in medicines)
        medicine.read<String>('id'): medicine.read<String>('brand_name'),
    };

    final List<LocalOperationalAlert> nearExpiry = <LocalOperationalAlert>[];
    final List<LocalOperationalAlert> expired = <LocalOperationalAlert>[];

    for (final QueryRow batch in batches) {
      final String medicineId = batch.read<String>('medicine_id');
      final FixedDecimal quantity = FixedDecimal.parse(
        batch.read<String>('available_quantity'),
      );
      if (!quantity.isPositive) {
        continue;
      }

      final int? expiresAt = batch.readNullable<int>('expires_at');
      if (expiresAt == null || expiresAt >= todaySeconds) {
        sellable[medicineId] =
            (sellable[medicineId] ?? FixedDecimal.zero) + quantity;
      }

      final String name = names[medicineId] ?? medicineId;
      final String lot = batch.read<String>('batch_number').isEmpty
          ? '—'
          : batch.read<String>('batch_number');

      if (expiresAt != null && expiresAt < todaySeconds) {
        expired.add(
          LocalOperationalAlert(
            kind: OperationalAlertKind.expired,
            severity: OperationalAlertSeverity.critical,
            title: name,
            detail: 'Batch $lot has ${quantity.compact} expired units.',
          ),
        );
      } else if (expiresAt != null && expiresAt <= endSeconds) {
        final DateTime expiry = DateTime.fromMillisecondsSinceEpoch(
          expiresAt * 1000,
        );
        nearExpiry.add(
          LocalOperationalAlert(
            kind: OperationalAlertKind.nearExpiry,
            severity: OperationalAlertSeverity.warning,
            title: name,
            detail:
                'Batch $lot expires ${expiry.year}-${expiry.month.toString().padLeft(2, '0')}-${expiry.day.toString().padLeft(2, '0')}.',
          ),
        );
      }
    }

    final FixedDecimal threshold = FixedDecimal.parse(
      lowStockThreshold.toString(),
    );
    final List<LocalOperationalAlert> lowStock = <LocalOperationalAlert>[];

    for (final QueryRow medicine in medicines) {
      final String medicineId = medicine.read<String>('id');
      final FixedDecimal available = sellable[medicineId] ?? FixedDecimal.zero;
      if (available.compareTo(threshold) <= 0) {
        lowStock.add(
          LocalOperationalAlert(
            kind: OperationalAlertKind.lowStock,
            severity: OperationalAlertSeverity.warning,
            title: medicine.read<String>('brand_name'),
            detail:
                '${available.compact} available; alert threshold is $lowStockThreshold.',
          ),
        );
      }
    }

    final List<QueryRow> rejectedRows = await _database.customSelect(
      '''
      SELECT aggregate_id, last_error
      FROM sync_outbox_entries
      WHERE status = 'rejected'
      ORDER BY updated_at DESC
      LIMIT 20
      ''',
    ).get();

    final List<LocalOperationalAlert> rejected = rejectedRows
        .map(
          (QueryRow row) => LocalOperationalAlert(
            kind: OperationalAlertKind.syncRejected,
            severity: OperationalAlertSeverity.critical,
            title: 'Sale synchronization conflict',
            detail:
                row.readNullable<String>('last_error') ??
                'A completed offline sale needs reconciliation.',
          ),
        )
        .toList(growable: false);

    return OperationalAlertSnapshot(
      alerts: <LocalOperationalAlert>[
        ...expired,
        ...rejected,
        ...lowStock,
        ...nearExpiry,
      ],
      lowStockCount: lowStock.length,
      nearExpiryCount: nearExpiry.length,
      expiredCount: expired.length,
      syncRejectedCount: rejected.length,
    );
  }

  Future<bool> notificationsEnabled() async {
    final QueryRow? row = await _database
        .customSelect(
          "SELECT value FROM app_metadata WHERE key = 'alerts.notifications.enabled'",
        )
        .getSingleOrNull();

    return row?.read<String>('value') == '1';
  }

  Future<void> setNotificationsEnabled(bool enabled) async {
    await _writeMetadata(
      'alerts.notifications.enabled',
      enabled ? '1' : '0',
    );
  }

  Future<String?> lastNotifiedFingerprint() async {
    final QueryRow? row = await _database
        .customSelect(
          "SELECT value FROM app_metadata WHERE key = 'alerts.last_notified_fingerprint'",
        )
        .getSingleOrNull();

    return row?.read<String>('value');
  }

  Future<void> setLastNotifiedFingerprint(String fingerprint) {
    return _writeMetadata('alerts.last_notified_fingerprint', fingerprint);
  }

  Future<void> _writeMetadata(String key, String value) {
    final int now = _clock().millisecondsSinceEpoch ~/ 1000;
    return _database.customStatement(
      '''
      INSERT INTO app_metadata (key, value, updated_at)
      VALUES (?, ?, ?)
      ON CONFLICT(key) DO UPDATE SET
        value = excluded.value,
        updated_at = excluded.updated_at
      ''',
      <Object?>[key, value, now],
    );
  }
}
