import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:businessos_pharmacy/features/medicines/presentation/medicine_scan_review_panel.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const draft = MedicineScanDraft(
    brandName: 'Sample medicine',
    sourceText: 'Sample medicine',
  );

  for (final locale in AppLocale.values) {
    testWidgets('${locale.code} OCR review requires pharmacist confirmation', (
      tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          locale: locale.locale,
          supportedLocales: AppLocale.values.map((value) => value.locale),
          localizationsDelegates: <LocalizationsDelegate<dynamic>>[
            const _PashtoCupertinoFallback(),
            ...GlobalMaterialLocalizations.delegates,
          ],
          home: Scaffold(
            body: SingleChildScrollView(
              child: MedicineScanReviewPanel(
                draft: draft,
                onConfirmed: _ignoreReview,
              ),
            ),
          ),
        ),
      );

      final title = switch (locale) {
        AppLocale.english => 'Review scanned medicine details',
        AppLocale.dari => 'بررسی جزئیات دوای اسکن‌شده',
        AppLocale.pashto => 'د سکین شويو درملو معلومات وڅېړئ',
      };
      expect(find.text(title), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.text(title))),
        locale.isRtl ? TextDirection.rtl : TextDirection.ltr,
      );
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );

      await tester.ensureVisible(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pump();
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNotNull,
      );
    });
  }
}

void _ignoreReview(ReviewedMedicineScan value) {}

class _PashtoCupertinoFallback
    extends LocalizationsDelegate<CupertinoLocalizations> {
  const _PashtoCupertinoFallback();

  @override
  bool isSupported(Locale locale) => locale.languageCode == 'ps';

  @override
  Future<CupertinoLocalizations> load(Locale locale) =>
      GlobalCupertinoLocalizations.delegate.load(const Locale('fa'));

  @override
  bool shouldReload(_PashtoCupertinoFallback old) => false;
}
