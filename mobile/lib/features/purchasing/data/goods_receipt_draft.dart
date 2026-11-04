import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';

/// Local validation only. A receipt must not affect inventory until the
/// server validates and posts it under an authenticated, idempotent operation.
class GoodsReceiptDraftLine {
  const GoodsReceiptDraftLine({
    required this.purchaseOrderLineId,
    required this.orderedQuantity,
    required this.previouslyReceivedQuantity,
    required this.receivedQuantity,
    required this.unitCost,
    this.bonusQuantity = '0',
    this.batchNumber,
    this.expiresAt,
    this.manufacturedAt,
    this.salePrice,
    this.requiresBatch = false,
    this.requiresExpiry = false,
  });

  final String purchaseOrderLineId;
  final String orderedQuantity;
  final String previouslyReceivedQuantity;
  final String receivedQuantity;
  final String bonusQuantity;
  final String unitCost;
  final String? batchNumber;
  final String? expiresAt;
  final String? manufacturedAt;
  final String? salePrice;
  final bool requiresBatch;
  final bool requiresExpiry;

  Map<String, String?> toPayload() => <String, String?>{
    'purchase_order_line_id': purchaseOrderLineId,
    'received_quantity': receivedQuantity,
    'bonus_quantity': bonusQuantity,
    'unit_cost': unitCost,
    'batch_number': batchNumber,
    'expires_at': expiresAt,
    'manufactured_at': manufacturedAt,
    'sale_price': salePrice,
  };
}

class GoodsReceiptDraftValidator {
  const GoodsReceiptDraftValidator._();

  static bool _validDate(String value) {
    final match = RegExp(r'^\\d{4}-\\d{2}-\\d{2}
    List<GoodsReceiptDraftLine> lines, {
    required String orderStatus,
  }) {
    final errors = <String>[];
    if (orderStatus != 'approved' && orderStatus != 'partially_received') {
      errors.add(
        'Only approved or partially received orders can receive goods.',
      );
    }
    if (lines.isEmpty || lines.length > 250) {
      errors.add('A receipt must contain between 1 and 250 order lines.');
    }
    var hasReceived = false;
    final ids = <String>{};
    for (final line in lines) {
      if (line.purchaseOrderLineId.trim().isEmpty ||
          !ids.add(line.purchaseOrderLineId)) {
        errors.add('Receipt lines must have distinct order-line IDs.');
        continue;
      }
      try {
        final ordered = FixedDecimal.parse(line.orderedQuantity);
        final previous = FixedDecimal.parse(line.previouslyReceivedQuantity);
        final received = FixedDecimal.parse(line.receivedQuantity);
        final bonus = FixedDecimal.parse(line.bonusQuantity);
        final cost = FixedDecimal.parse(line.unitCost);
        if (ordered.isNegative ||
            previous.isNegative ||
            received.isNegative ||
            bonus.isNegative ||
            cost.isNegative) {
          errors.add('Quantities and costs cannot be negative.');
          continue;
        }
        if ((previous + received).compareTo(ordered) > 0) {
          errors.add('Received quantity exceeds the remaining order quantity.');
        }
        if (received.isPositive) {
          hasReceived = true;
          if (line.requiresBatch &&
              (line.batchNumber?.trim().isEmpty ?? true)) {
            errors.add('A batch number is required for tracked medicine.');
          }
          if (line.requiresExpiry && (line.expiresAt?.trim().isEmpty ?? true)) {
            errors.add('An expiry date is required for tracked medicine.');
          }
          if (line.expiresAt != null &&
              line.expiresAt!.isNotEmpty &&
              !_validDate(line.expiresAt!)) {
            errors.add('Expiry date is invalid.');
          }
          if (line.manufacturedAt != null &&
              line.manufacturedAt!.isNotEmpty &&
              !_validDate(line.manufacturedAt!)) {
            errors.add('Manufacturing date is invalid.');
          }
          if (line.salePrice != null &&
              line.salePrice!.isNotEmpty &&
              FixedDecimal.parse(line.salePrice!).isNegative) {
            errors.add('Sale price cannot be negative.');
          }
        }
      } on FormatException {
        errors.add('A receipt quantity, price, or date is invalid.');
      }
    }
    if (!hasReceived) {
      errors.add('At least one line must have a positive received quantity.');
    }
    return errors;
  }
}
).hasMatch(value);
    if (!match) return false;
    final date = DateTime.tryParse(value);
    if (date == null) return false;
    return date.toIso8601String().startsWith(value);
  }

  static List<String> validate(
    List<GoodsReceiptDraftLine> lines, {
    required String orderStatus,
  }) {
    final errors = <String>[];
    if (orderStatus != 'approved' && orderStatus != 'partially_received') {
      errors.add(
        'Only approved or partially received orders can receive goods.',
      );
    }
    if (lines.isEmpty || lines.length > 250) {
      errors.add('A receipt must contain between 1 and 250 order lines.');
    }
    var hasReceived = false;
    final ids = <String>{};
    for (final line in lines) {
      if (line.purchaseOrderLineId.trim().isEmpty ||
          !ids.add(line.purchaseOrderLineId)) {
        errors.add('Receipt lines must have distinct order-line IDs.');
        continue;
      }
      try {
        final ordered = FixedDecimal.parse(line.orderedQuantity);
        final previous = FixedDecimal.parse(line.previouslyReceivedQuantity);
        final received = FixedDecimal.parse(line.receivedQuantity);
        final bonus = FixedDecimal.parse(line.bonusQuantity);
        final cost = FixedDecimal.parse(line.unitCost);
        if (ordered.isNegative ||
            previous.isNegative ||
            received.isNegative ||
            bonus.isNegative ||
            cost.isNegative) {
          errors.add('Quantities and costs cannot be negative.');
          continue;
        }
        if ((previous + received).compareTo(ordered) > 0) {
          errors.add('Received quantity exceeds the remaining order quantity.');
        }
        if (received.isPositive) {
          hasReceived = true;
          if (line.requiresBatch &&
              (line.batchNumber?.trim().isEmpty ?? true)) {
            errors.add('A batch number is required for tracked medicine.');
          }
          if (line.requiresExpiry && (line.expiresAt?.trim().isEmpty ?? true)) {
            errors.add('An expiry date is required for tracked medicine.');
          }
          if (line.expiresAt != null &&
              line.expiresAt!.isNotEmpty &&
              DateTime.tryParse(line.expiresAt!) == null) {
            errors.add('Expiry date is invalid.');
          }
          if (line.manufacturedAt != null &&
              line.manufacturedAt!.isNotEmpty &&
              DateTime.tryParse(line.manufacturedAt!) == null) {
            errors.add('Manufacturing date is invalid.');
          }
          if (line.salePrice != null &&
              line.salePrice!.isNotEmpty &&
              FixedDecimal.parse(line.salePrice!).isNegative) {
            errors.add('Sale price cannot be negative.');
          }
        }
      } on FormatException {
        errors.add('A receipt quantity, price, or date is invalid.');
      }
    }
    if (!hasReceived) {
      errors.add('At least one line must have a positive received quantity.');
    }
    return errors;
  }
}
