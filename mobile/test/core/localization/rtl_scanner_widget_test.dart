import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/localization/scanner_strings.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  for (final locale in AppLocale.values) {
    testWidgets('${locale.code} scanner labels respect locale direction', (tester) async {
      late String scanTitle;
      late String cameraError;
      await tester.pumpWidget(MaterialApp(
        locale: locale.locale,
        supportedLocales: AppLocale.values.map((value) => value.locale),
        localizationsDelegates: <LocalizationsDelegate<dynamic>>[
          const _TestPashtoCupertinoFallback(),
          ...GlobalMaterialLocalizations.delegates,
        ],
        home: Builder(builder: (context) {
          final strings = ScannerStrings.of(context);
          scanTitle = strings.scanTitle;
          cameraError = strings.cameraUnavailable('permission_denied');
          return Scaffold(body: Text(strings.scanTitle));
        }),
      ));
      expect(Directionality.of(tester.element(find.text(scanTitle))),
          locale.isRtl ? TextDirection.rtl : TextDirection.ltr);
      expect(scanTitle, isNotEmpty);
      expect(cameraError, contains('permission_denied'));
      expect(cameraError, isNot(contains('{code}')));
      if (locale.isRtl) {
        expect(scanTitle, isNot('Scan medicine code'));
      }
    });
  }
}

class _TestPashtoCupertinoFallback extends LocalizationsDelegate<CupertinoLocalizations> {
  const _TestPashtoCupertinoFallback();
  @override
  bool isSupported(Locale locale) => locale.languageCode == 'ps';
  @override
  Future<CupertinoLocalizations> load(Locale locale) =>
      GlobalCupertinoLocalizations.delegate.load(const Locale('fa'));
  @override
  bool shouldReload(_TestPashtoCupertinoFallback old) => false;
}
