import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';

class OfflineReceiptLine {
  const OfflineReceiptLine({
    required this.medicineName,
    required this.saleUnit,
    required this.quantity,
    required this.unitPrice,
    required this.discountAmount,
    required this.lineTotal,
  });

  final String medicineName;
  final String saleUnit;
  final FixedDecimal quantity;
  final FixedDecimal unitPrice;
  final FixedDecimal discountAmount;
  final FixedDecimal lineTotal;
}

class OfflineReceiptPayment {
  const OfflineReceiptPayment({
    required this.method,
    required this.amount,
    this.reference,
  });

  final String method;
  final FixedDecimal amount;
  final String? reference;
}

class OfflineReceipt {
  const OfflineReceipt({
    required this.saleLocalId,
    required this.stockLocationId,
    required this.businessDate,
    required this.syncState,
    required this.subtotal,
    required this.discountAmount,
    required this.total,
    required this.tenderedAmount,
    required this.changeAmount,
    required this.completedAt,
    required this.lines,
    required this.payments,
    this.serverId,
    this.customerName,
  });

  final String saleLocalId;
  final String? serverId;
  final String stockLocationId;
  final String businessDate;
  final String syncState;
  final String? customerName;
  final FixedDecimal subtotal;
  final FixedDecimal discountAmount;
  final FixedDecimal total;
  final FixedDecimal tenderedAmount;
  final FixedDecimal changeAmount;
  final DateTime completedAt;
  final List<OfflineReceiptLine> lines;
  final List<OfflineReceiptPayment> payments;

  String get displayNumber {
    final String compact = saleLocalId.replaceAll('-', '');
    final String suffix = compact.length <= 10
        ? compact
        : compact.substring(compact.length - 10);

    return 'OFF-${suffix.toUpperCase()}';
  }

  bool get isSynced => syncState == 'synced';
}
