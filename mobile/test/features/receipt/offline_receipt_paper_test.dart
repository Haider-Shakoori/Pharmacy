import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/features/receipt/domain/offline_receipt.dart';
import 'package:businessos_pharmacy/features/receipt/presentation/offline_receipt_paper.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('receipt paper renders local transaction details', (
    WidgetTester tester,
  ) async {
    final OfflineReceipt receipt = OfflineReceipt(
      saleLocalId: 'sale-1234567890',
      stockLocationId: 'MAIN',
      businessDate: '2026-09-28',
      syncState: 'pending',
      subtotal: FixedDecimal.parse('25'),
      discountAmount: FixedDecimal.parse('1'),
      total: FixedDecimal.parse('24'),
      tenderedAmount: FixedDecimal.parse('30'),
      changeAmount: FixedDecimal.parse('6'),
      completedAt: DateTime(2026, 9, 28, 12, 30),
      lines: <OfflineReceiptLine>[
        OfflineReceiptLine(
          medicineName: 'Paracetamol',
          saleUnit: 'box',
          quantity: FixedDecimal.parse('2'),
          unitPrice: FixedDecimal.parse('12.5'),
          discountAmount: FixedDecimal.parse('1'),
          lineTotal: FixedDecimal.parse('24'),
        ),
      ],
      payments: <OfflineReceiptPayment>[
        OfflineReceiptPayment(
          method: 'cash',
          amount: FixedDecimal.parse('30'),
        ),
      ],
    );

    await tester.pumpWidget(
      MaterialApp(
        locale: AppLocale.english.locale,
        supportedLocales: AppLocale.values
            .map((AppLocale locale) => locale.locale)
            .toList(growable: false),
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: Scaffold(
          body: OfflineReceiptPaper(
            receipt: receipt,
            pharmacyName: 'Kabul Pharmacy',
            cashierName: 'Cashier',
            paperWidth: ReceiptPaperWidth.mm80,
          ),
        ),
      ),
    );

    expect(find.text('Kabul Pharmacy'), findsOneWidget);
    expect(find.text('Paracetamol'), findsOneWidget);
    expect(find.text('24 AFN'), findsWidgets);
    expect(find.text('Pending synchronization'), findsOneWidget);
  });
}
