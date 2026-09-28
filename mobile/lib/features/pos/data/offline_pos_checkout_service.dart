import 'dart:convert';

import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/features/pos/domain/offline_pos_models.dart';
import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

typedef PosTransactionAuthorizer = Future<void> Function();

class OfflinePosCheckoutService {
  OfflinePosCheckoutService(
    this._database, {
    required this.authorizeTransaction,
    String Function()? idGenerator,
    DateTime Function()? clock,
  }) : _idGenerator = idGenerator ?? const Uuid().v4,
       _clock = clock ?? DateTime.now;

  static const Set<String> _offlinePaymentMethods = <String>{
    'cash',
    'bank',
    'mobile',
  };

  final PharmacyDatabase _database;
  final PosTransactionAuthorizer authorizeTransaction;
  final String Function() _idGenerator;
  final DateTime Function() _clock;

  Future<OfflinePosCheckoutResult> checkout(
    OfflinePosCheckoutRequest request,
  ) async {
    await authorizeTransaction();

    if (!request.permissions.contains('pos.sell')) {
      throw const OfflinePosException(
        'This pharmacy user does not have POS sale permission.',
      );
    }
    if (request.stockLocationId.trim().isEmpty) {
      throw const OfflinePosException('A stock location is required.');
    }
    if (request.lines.isEmpty || request.lines.length > 100) {
      throw const OfflinePosException(
        'An offline sale must contain between 1 and 100 lines.',
      );
    }
    if (request.payments.isEmpty || request.payments.length > 10) {
      throw const OfflinePosException(
        'An offline sale must contain between 1 and 10 payments.',
      );
    }

    final DateTime now = _clock();
    final String businessDate = request.businessDate ?? _businessDate(now);
    final String saleId = _idGenerator();
    final String idempotencyKey = 'sale:$saleId:completed';

    return _database.transaction(() async {
      FixedDecimal subtotal = FixedDecimal.zero;
      FixedDecimal discountTotal = FixedDecimal.zero;
      final List<Map<String, Object?>> linePayload = <Map<String, Object?>>[];

      for (final OfflinePosLineInput input in request.lines) {
        final medicine =
            await (_database.select(_database.localMedicines)..where(
                  (table) =>
                      table.id.equals(input.medicineId) &
                      table.isActive.equals(true) &
                      table.isDeleted.equals(false),
                ))
                .getSingleOrNull();

        if (medicine == null) {
          throw const OfflinePosException(
            'One of the selected medicines is no longer available locally.',
          );
        }

        final FixedDecimal quantity = FixedDecimal.parse(input.quantity);
        final FixedDecimal unitPrice = FixedDecimal.parse(input.unitPrice);
        final FixedDecimal discount = FixedDecimal.parse(input.discountAmount);

        if (!quantity.isPositive) {
          throw const OfflinePosException(
            'Sale quantity must be greater than zero.',
          );
        }
        if (unitPrice.isNegative || discount.isNegative) {
          throw const OfflinePosException(
            'Price and discount cannot be negative.',
          );
        }

        final List<_OfflineBatchSnapshot> batches = await _eligibleBatches(
          medicine.id,
          request.stockLocationId,
          now,
        );
        if (batches.isEmpty) {
          throw const OfflinePosException(
            'No locally cached sellable stock is available for this medicine.',
          );
        }

        final FixedDecimal basePrice = batches.first.salePrice;

        if (unitPrice != basePrice &&
            !request.permissions.contains('pos.price_override')) {
          throw const OfflinePosException(
            'This pharmacy user cannot override the sale price.',
          );
        }

        if (discount.isPositive &&
            !request.permissions.contains('pos.discount')) {
          throw const OfflinePosException(
            'This pharmacy user cannot apply a sale discount.',
          );
        }

        final FixedDecimal lineSubtotal = quantity.multiply(unitPrice);
        if (discount.compareTo(lineSubtotal) > 0) {
          throw const OfflinePosException(
            'A line discount cannot exceed its subtotal.',
          );
        }

        final FixedDecimal lineTotal = lineSubtotal - discount;
        final String lineId = _idGenerator();
        final List<Map<String, Object?>> allocations = await _allocateFefo(
          saleLineId: lineId,
          requestedQuantity: quantity,
          batches: batches,
          now: now,
        );
        final String? singleBatchId = allocations.length == 1
            ? allocations.single['product_batch_id']?.toString()
            : null;

        await _database.customStatement(
          '''
          INSERT INTO local_sale_lines (
            local_id, sale_local_id, medicine_id, batch_id, quantity,
            unit_price, discount_amount, line_total
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
          ''',
          <Object?>[
            lineId,
            saleId,
            medicine.id,
            singleBatchId,
            quantity.toString(),
            unitPrice.toString(),
            discount.toString(),
            lineTotal.toString(),
          ],
        );

        subtotal += lineSubtotal;
        discountTotal += discount;
        linePayload.add(<String, Object?>{
          'local_id': lineId,
          'medicine_id': medicine.id,
          'description': medicine.brandName,
          'sale_unit': medicine.saleUnit,
          'quantity': quantity.toString(),
          'unit_price': unitPrice.toString(),
          'discount_amount': discount.toString(),
          'line_total': lineTotal.toString(),
          'batch_allocations': allocations,
        });
      }

      final FixedDecimal total = subtotal - discountTotal;
      FixedDecimal tendered = FixedDecimal.zero;
      bool hasCash = false;
      final List<Map<String, Object?>> paymentPayload =
          <Map<String, Object?>>[];

      for (final OfflinePosPaymentInput input in request.payments) {
        if (!_offlinePaymentMethods.contains(input.method)) {
          throw const OfflinePosException(
            'Offline POS currently supports cash, bank and mobile payments.',
          );
        }

        final FixedDecimal amount = FixedDecimal.parse(input.amount);
        if (!amount.isPositive) {
          throw const OfflinePosException(
            'Payment amounts must be greater than zero.',
          );
        }

        tendered += amount;
        hasCash = hasCash || input.method == 'cash';
        final String paymentId = _idGenerator();

        await _database.customStatement(
          '''
          INSERT INTO local_sale_payments (
            local_id, sale_local_id, method, amount, reference
          ) VALUES (?, ?, ?, ?, ?)
          ''',
          <Object?>[
            paymentId,
            saleId,
            input.method,
            amount.toString(),
            input.reference,
          ],
        );

        paymentPayload.add(<String, Object?>{
          'local_id': paymentId,
          'method': input.method,
          'amount': amount.toString(),
          'reference': input.reference,
        });
      }

      if (tendered.compareTo(total) < 0) {
        throw const OfflinePosException(
          'Payments do not cover the offline sale total.',
        );
      }

      final FixedDecimal change = tendered - total;
      if (change.isPositive && !hasCash) {
        throw const OfflinePosException(
          'Offline overpayment can only be returned as cash change.',
        );
      }

      final int timestamp = now.millisecondsSinceEpoch ~/ 1000;

      await _database.customStatement(
        '''
        INSERT INTO local_sales (
          local_id, server_id, customer_id, stock_location_id, business_date,
          currency, status, sync_state, subtotal, discount_amount, total,
          tendered_amount, change_amount, created_at, updated_at, completed_at
        ) VALUES (?, NULL, ?, ?, ?, 'AFN', 'completed', 'pending', ?, ?, ?, ?, ?, ?, ?, ?)
        ''',
        <Object?>[
          saleId,
          request.customerId,
          request.stockLocationId,
          businessDate,
          subtotal.toString(),
          discountTotal.toString(),
          total.toString(),
          tendered.toString(),
          change.toString(),
          timestamp,
          timestamp,
          timestamp,
        ],
      );

      final Map<String, Object?> payload = <String, Object?>{
        'v': 1,
        'local_id': saleId,
        'idempotency_key': idempotencyKey,
        'stock_location_id': request.stockLocationId,
        'customer_id': request.customerId,
        'cashier_user_id': request.cashierUserId,
        'business_date': businessDate,
        'currency': 'AFN',
        'subtotal': subtotal.toString(),
        'discount_amount': discountTotal.toString(),
        'total': total.toString(),
        'tendered_amount': tendered.toString(),
        'change_amount': change.toString(),
        'lines': linePayload,
        'payments': paymentPayload,
        'created_at': now.toUtc().toIso8601String(),
      };

      await _database.customStatement(
        '''
        INSERT INTO sync_outbox_entries (
          idempotency_key, aggregate_type, aggregate_id, event_type,
          payload_json, status, attempt_count, created_at, updated_at
        ) VALUES (?, 'sale', ?, 'sale.completed', ?, 'pending', 0, ?, ?)
        ''',
        <Object?>[
          idempotencyKey,
          saleId,
          jsonEncode(payload),
          timestamp,
          timestamp,
        ],
      );

      return OfflinePosCheckoutResult(
        saleLocalId: saleId,
        subtotal: subtotal,
        discountAmount: discountTotal,
        total: total,
        tenderedAmount: tendered,
        changeAmount: change,
        idempotencyKey: idempotencyKey,
      );
    });
  }

  Future<List<_OfflineBatchSnapshot>> _eligibleBatches(
    String medicineId,
    String stockLocationId,
    DateTime now,
  ) async {
    final DateTime today = DateTime(now.year, now.month, now.day);
    final int todaySeconds = today.millisecondsSinceEpoch ~/ 1000;
    final List<QueryRow> rows = await _database.customSelect(
      '''
      SELECT id, available_quantity, sale_price, purchase_cost
      FROM local_inventory_batches
      WHERE medicine_id = ?
        AND stock_location_id = ?
        AND status = 'active'
        AND is_deleted = 0
        AND (expires_at IS NULL OR expires_at >= ?)
      ORDER BY
        CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END,
        expires_at,
        CASE WHEN server_created_at IS NULL THEN 1 ELSE 0 END,
        server_created_at,
        id
      ''',
      variables: <Variable<Object>>[
        Variable<String>(medicineId),
        Variable<String>(stockLocationId),
        Variable<int>(todaySeconds),
      ],
    ).get();

    return rows
        .map(
          (QueryRow row) => _OfflineBatchSnapshot(
            id: row.read<String>('id'),
            available: FixedDecimal.parse(
              row.read<String>('available_quantity'),
            ),
            salePrice: FixedDecimal.parse(row.read<String>('sale_price')),
            purchaseCost: FixedDecimal.parse(row.read<String>('purchase_cost')),
          ),
        )
        .where((_OfflineBatchSnapshot batch) => batch.available.isPositive)
        .toList(growable: false);
  }

  Future<List<Map<String, Object?>>> _allocateFefo({
    required String saleLineId,
    required FixedDecimal requestedQuantity,
    required List<_OfflineBatchSnapshot> batches,
    required DateTime now,
  }) async {
    FixedDecimal remaining = requestedQuantity;
    final List<Map<String, Object?>> allocations = <Map<String, Object?>>[];
    final int timestamp = now.millisecondsSinceEpoch ~/ 1000;

    for (final _OfflineBatchSnapshot batch in batches) {
      if (!remaining.isPositive) {
        break;
      }

      final FixedDecimal take = batch.available.compareTo(remaining) < 0
          ? batch.available
          : remaining;
      final FixedDecimal availableAfter = batch.available - take;
      final String allocationId = _idGenerator();

      await _database.customStatement(
        '''
        UPDATE local_inventory_batches
        SET available_quantity = ?
        WHERE id = ?
        ''',
        <Object?>[availableAfter.toString(), batch.id],
      );

      await _database.customStatement(
        '''
        INSERT INTO local_sale_batch_allocations (
          local_id, sale_line_local_id, product_batch_id,
          quantity, unit_cost, created_at
        ) VALUES (?, ?, ?, ?, ?, ?)
        ''',
        <Object?>[
          allocationId,
          saleLineId,
          batch.id,
          take.toString(),
          batch.purchaseCost.toString(),
          timestamp,
        ],
      );

      allocations.add(<String, Object?>{
        'local_id': allocationId,
        'product_batch_id': batch.id,
        'quantity': take.toString(),
        'unit_cost': batch.purchaseCost.toString(),
      });
      remaining -= take;
    }

    if (remaining.isPositive) {
      throw const OfflinePosException(
        'Insufficient locally cached eligible stock for this medicine.',
      );
    }

    return allocations;
  }

  String _businessDate(DateTime value) {
    final String month = value.month.toString().padLeft(2, '0');
    final String day = value.day.toString().padLeft(2, '0');

    return '${value.year}-$month-$day';
  }
}

class _OfflineBatchSnapshot {
  const _OfflineBatchSnapshot({
    required this.id,
    required this.available,
    required this.salePrice,
    required this.purchaseCost,
  });

  final String id;
  final FixedDecimal available;
  final FixedDecimal salePrice;
  final FixedDecimal purchaseCost;
}
