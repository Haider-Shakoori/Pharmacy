import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:businessos_pharmacy/features/medicines/presentation/medicine_scan_review_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const draft = MedicineScanDraft(
    brandName: 'Original brand',
    strength: '250 mg',
    batchNumber: 'LOT123',
    expiresAt: '12/2028',
    sourceText: 'Original brand 250 mg LOT123 EXP 12/2028',
  );

  testWidgets('requires confirmation before returning reviewed details', (
    tester,
  ) async {
    ReviewedMedicineScan? result;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: MedicineScanReviewPanel(
              draft: draft,
              onConfirmed: (value) => result = value,
            ),
          ),
        ),
      ),
    );
    expect(find.text('Use reviewed details'), findsOneWidget);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );

    await tester.enterText(find.byType(TextField).first, 'Corrected brand');
    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNotNull,
    );
    await tester.ensureVisible(find.byType(FilledButton));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byType(FilledButton));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(FilledButton));
    expect(result?.brandName, 'Corrected brand');
    expect(result?.strength, '250 mg');
  });

  testWidgets('editing an approved OCR field requires confirmation again', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: MedicineScanReviewPanel(
              draft: draft,
              onConfirmed: _ignoreReviewedMedicine,
            ),
          ),
        ),
      ),
    );

    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNotNull,
    );

    await tester.enterText(find.byType(TextField).first, 'Updated after review');
    await tester.pump();

    expect(
      tester.widget<CheckboxListTile>(find.byType(CheckboxListTile)).value,
      isFalse,
    );
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
  });

  testWidgets('empty brand blocks confirmation', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: MedicineScanReviewPanel(
              draft: draft,
              onConfirmed: _ignoreReviewedMedicine,
            ),
          ),
        ),
      ),
    );
    await tester.enterText(find.byType(TextField).first, ' ');
    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
  });
}

void _ignoreReviewedMedicine(ReviewedMedicineScan value) {}
