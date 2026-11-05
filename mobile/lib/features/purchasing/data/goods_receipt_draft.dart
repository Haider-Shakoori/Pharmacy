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

  static String? _optionalPayloadValue(String? value) {
    final String? normalized = value?.trim();
    return normalized == null || normalized.isEmpty ? null : normalized;
  }

  Map<String, String?> toPayload() => <String, String?>{
    'purchase_order_line_id': purchaseOrderLineId.trim(),
    'received_quantity': receivedQuantity.trim(),
    'bonus_quantity': bonusQuantity.trim(),
    'unit_cost': unitCost.trim(),
    'batch_number': _optionalPayloadValue(batchNumber),
    'expires_at': _optionalPayloadValue(expiresAt),
    'manufactured_at': _optionalPayloadValue(manufacturedAt),
    'sale_price': _optionalPayloadValue(salePrice),
  };
}

class GoodsReceiptDraftValidator {
  const GoodsReceiptDraftValidator._();

  static bool _validDate(String value) {
    if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(value)) return false;
    final date = DateTime.tryParse(value);
    if (date == null) return false;
    return date.toIso8601String().startsWith(value);
  }

  static List<String> validate(
    List<GoodsReceiptDraftLine> lines, {
    required String orderStatus,
  }) {
    final errors = <String>[];
    if (orderStatus.trim() != 'approved' &&
        orderStatus.trim() != 'partially_received') {
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
          !ids.add(line.purchaseOrderLineId.trim())) {
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
        }
        if (line.expiresAt != null &&
            line.expiresAt!.trim().isNotEmpty &&
            !_validDate(line.expiresAt!.trim())) {
          errors.add('Expiry date is invalid.');
        }
        if (line.manufacturedAt != null &&
            line.manufacturedAt!.trim().isNotEmpty &&
            !_validDate(line.manufacturedAt!.trim())) {
          errors.add('Manufacturing date is invalid.');
        }
        if (line.expiresAt != null &&
            line.manufacturedAt != null &&
            _validDate(line.expiresAt!.trim()) &&
            _validDate(line.manufacturedAt!.trim()) &&
            line.manufacturedAt!.trim().compareTo(line.expiresAt!.trim()) > 0) {
          errors.add('Manufacturing date cannot be after expiry date.');
        }
        if (line.salePrice != null &&
            line.salePrice!.trim().isNotEmpty &&
            FixedDecimal.parse(line.salePrice!.trim()).isNegative) {
          errors.add('Sale price cannot be negative.');
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
