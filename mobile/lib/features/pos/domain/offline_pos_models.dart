import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';

class OfflinePosCatalogItem {
  const OfflinePosCatalogItem({
    required this.medicineId,
    required this.brandName,
    required this.saleUnit,
    required this.stockLocationId,
    required this.salePrice,
    required this.availableQuantity,
    this.genericName,
  });

  final String medicineId;
  final String brandName;
  final String? genericName;
  final String saleUnit;
  final String stockLocationId;
  final FixedDecimal salePrice;
  final FixedDecimal availableQuantity;
}

class OfflinePosLineInput {
  const OfflinePosLineInput({
    required this.medicineId,
    required this.quantity,
    required this.unitPrice,
    this.discountAmount = '0',
  });

  final String medicineId;
  final String quantity;
  final String unitPrice;
  final String discountAmount;
}

class OfflinePosPaymentInput {
  const OfflinePosPaymentInput({
    required this.method,
    required this.amount,
    this.reference,
  });

  final String method;
  final String amount;
  final String? reference;
}

class OfflinePosCheckoutRequest {
  const OfflinePosCheckoutRequest({
    required this.stockLocationId,
    required this.cashierUserId,
    required this.permissions,
    required this.lines,
    required this.payments,
    this.customerId,
    this.businessDate,
  });

  final String stockLocationId;
  final String cashierUserId;
  final Set<String> permissions;
  final List<OfflinePosLineInput> lines;
  final List<OfflinePosPaymentInput> payments;
  final String? customerId;
  final String? businessDate;
}

class OfflinePosCheckoutResult {
  const OfflinePosCheckoutResult({
    required this.saleLocalId,
    required this.subtotal,
    required this.discountAmount,
    required this.total,
    required this.tenderedAmount,
    required this.changeAmount,
    required this.idempotencyKey,
  });

  final String saleLocalId;
  final FixedDecimal subtotal;
  final FixedDecimal discountAmount;
  final FixedDecimal total;
  final FixedDecimal tenderedAmount;
  final FixedDecimal changeAmount;
  final String idempotencyKey;
}

class OfflinePosException implements Exception {
  const OfflinePosException(this.message);

  final String message;

  @override
  String toString() => message;
}
