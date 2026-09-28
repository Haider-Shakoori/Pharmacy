import 'package:businessos_pharmacy/app/router.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/core/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

class BusinessOsPharmacyApp extends StatefulWidget {
  const BusinessOsPharmacyApp({super.key});

  @override
  State<BusinessOsPharmacyApp> createState() => _BusinessOsPharmacyAppState();
}

class _BusinessOsPharmacyAppState extends State<BusinessOsPharmacyApp> {
  late final ValueNotifier<AppLocale> _localeNotifier;
  late final RouterConfig<Object> _router;

  @override
  void initState() {
    super.initState();
    _localeNotifier = ValueNotifier<AppLocale>(AppLocale.english);
    _router = createAppRouter(_localeNotifier);
  }

  @override
  void dispose() {
    _localeNotifier.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<AppLocale>(
      valueListenable: _localeNotifier,
      builder: (BuildContext context, AppLocale appLocale, Widget? child) {
        return MaterialApp.router(
          debugShowCheckedModeBanner: false,
          title: 'BusinessOS Pharmacy',
          theme: AppTheme.light(),
          routerConfig: _router,
          locale: appLocale.locale,
          supportedLocales: AppLocale.values
              .map((AppLocale locale) => locale.locale)
              .toList(growable: false),
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
        );
      },
    );
  }
}
