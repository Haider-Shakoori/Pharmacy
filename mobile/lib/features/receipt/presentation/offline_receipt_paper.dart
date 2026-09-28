import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/receipt/domain/offline_receipt.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

enum ReceiptPaperWidth { mm58, mm80 }

class OfflineReceiptPaper extends StatelessWidget {
  const OfflineReceiptPaper({
    required this.receipt,
    required this.pharmacyName,
    required this.cashierName,
    required this.paperWidth,
    super.key,
  });

  final OfflineReceipt receipt;
  final String pharmacyName;
  final String cashierName;
  final ReceiptPaperWidth paperWidth;

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    final double width = paperWidth == ReceiptPaperWidth.mm58 ? 384 : 576;
    final TextStyle small = TextStyle(
      fontSize: paperWidth == ReceiptPaperWidth.mm58 ? 14 : 16,
      color: Colors.black,
    );
    final TextStyle strong = small.copyWith(fontWeight: FontWeight.w700);

    return Directionality(
      textDirection: Directionality.of(context),
      child: SizedBox(
        width: width,
        child: Material(
          color: Colors.white,
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: DefaultTextStyle(
              style: small,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text(
                    pharmacyName,
                    textAlign: TextAlign.center,
                    style: strong.copyWith(
                      fontSize: paperWidth == ReceiptPaperWidth.mm58 ? 20 : 24,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    strings.receiptTitle,
                    textAlign: TextAlign.center,
                    style: strong,
                  ),
                  const SizedBox(height: 8),
                  const Divider(color: Colors.black),
                  _row(strings.receiptNumber, receipt.displayNumber, small),
                  _row(
                    strings.receiptBusinessDate,
                    receipt.businessDate,
                    small,
                  ),
                  _row(
                    strings.receiptCompletedAt,
                    DateFormat('yyyy-MM-dd HH:mm').format(receipt.completedAt),
                    small,
                  ),
                  _row(strings.receiptCashier, cashierName, small),
                  _row(
                    strings.receiptCustomer,
                    receipt.customerName ?? strings.receiptWalkIn,
                    small,
                  ),
                  _row(
                    strings.receiptLocation,
                    receipt.stockLocationId,
                    small,
                  ),
                  const Divider(color: Colors.black),
                  for (final OfflineReceiptLine line in receipt.lines) ...<Widget>[
                    Text(line.medicineName, style: strong),
                    _row(
                      line.quantity.compact +
                          ' ' +
                          line.saleUnit +
                          ' × ' +
                          line.unitPrice.compact,
                      line.lineTotal.compact + ' AFN',
                      small,
                    ),
                    if (line.discountAmount.isPositive)
                      _row(
                        strings.receiptDiscount,
                        '-' + line.discountAmount.compact + ' AFN',
                        small,
                      ),
                    const SizedBox(height: 4),
                  ],
                  const Divider(color: Colors.black),
                  _row(
                    strings.receiptSubtotal,
                    receipt.subtotal.compact + ' AFN',
                    small,
                  ),
                  _row(
                    strings.receiptDiscount,
                    receipt.discountAmount.compact + ' AFN',
                    small,
                  ),
                  _row(
                    strings.receiptTotal,
                    receipt.total.compact + ' AFN',
                    strong,
                  ),
                  _row(
                    strings.receiptPaid,
                    receipt.tenderedAmount.compact + ' AFN',
                    small,
                  ),
                  if (receipt.changeAmount.isPositive)
                    _row(
                      strings.receiptChange,
                      receipt.changeAmount.compact + ' AFN',
                      small,
                    ),
                  if (receipt.payments.isNotEmpty) ...<Widget>[
                    const Divider(color: Colors.black),
                    for (final OfflineReceiptPayment payment
                        in receipt.payments)
                      _row(
                        strings.receiptPayment(payment.method),
                        payment.amount.compact + ' AFN',
                        small,
                      ),
                  ],
                  const Divider(color: Colors.black),
                  Text(
                    receipt.isSynced
                        ? strings.receiptSynced
                        : strings.receiptPendingSync,
                    textAlign: TextAlign.center,
                    style: small,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    strings.receiptThankYou,
                    textAlign: TextAlign.center,
                    style: strong,
                  ),
                  const SizedBox(height: 18),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _row(String label, String value, TextStyle style) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(child: Text(label, style: style)),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: style,
            ),
          ),
        ],
      ),
    );
  }
}
