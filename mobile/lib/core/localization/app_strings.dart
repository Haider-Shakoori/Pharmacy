import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:flutter/widgets.dart';

class AppStrings {
  const AppStrings._(this.locale);

  final AppLocale locale;

  static AppStrings of(BuildContext context) {
    return AppStrings._(
      AppLocale.fromCode(Localizations.localeOf(context).languageCode),
    );
  }

  static AppStrings forLocale(AppLocale locale) => AppStrings._(locale);

  String _value(String key) => _translations[locale]![key]!;

  String get appName => _value('appName');
  String get foundation => _value('foundation');
  String get foundationBody => _value('foundationBody');
  String get pos => _value('pos');
  String get stock => _value('stock');
  String get customers => _value('customers');
  String get sync => _value('sync');
  String get posTitle => _value('posTitle');
  String get posBody => _value('posBody');
  String get stockTitle => _value('stockTitle');
  String get stockBody => _value('stockBody');
  String get customersTitle => _value('customersTitle');
  String get customersBody => _value('customersBody');
  String get syncTitle => _value('syncTitle');
  String get syncBody => _value('syncBody');
  String get networkAvailable => _value('networkAvailable');
  String get networkUnavailable => _value('networkUnavailable');
  String get networkUnknown => _value('networkUnknown');
  String get serverNotVerified => _value('serverNotVerified');
  String get apiConfigured => _value('apiConfigured');
  String get apiNotConfigured => _value('apiNotConfigured');
  String get language => _value('language');

  static const Map<AppLocale, Map<String, String>>
  _translations = <AppLocale, Map<String, String>>{
    AppLocale.english: <String, String>{
      'appName': 'BusinessOS Pharmacy',
      'foundation': 'Offline-ready mobile foundation',
      'foundationBody': 'The local database, offline POS and synchronization engine are enabled in the next mobile batches.',
      'pos': 'POS',
      'stock': 'Stock',
      'customers': 'Customers',
      'sync': 'Sync',
      'posTitle': 'Point of Sale foundation',
      'posBody': 'Offline sales are not enabled yet. Batch 17 adds local persistence and Batch 19 enables local-first sales.',
      'stockTitle': 'Stock lookup foundation',
      'stockBody': 'Local batch and stock snapshots will be added with the SQLite/Drift data layer.',
      'customersTitle': 'Customer lookup foundation',
      'customersBody': 'Customer data will be cached locally only after the offline database and sync contracts are in place.',
      'syncTitle': 'Synchronization foundation',
      'syncBody': 'Connectivity is observed separately from server reachability. No transaction is marked synced without a server acknowledgement.',
      'networkAvailable': 'Network link available',
      'networkUnavailable': 'No network link',
      'networkUnknown': 'Checking network link',
      'serverNotVerified': 'Server reachability is verified separately',
      'apiConfigured': 'Tenant endpoint configured for this build',
      'apiNotConfigured': 'Tenant endpoint will be set during activation',
      'language': 'Language',
    },
    AppLocale.dari: <String, String>{
      'appName': 'فارمسی BusinessOS',
      'foundation': 'بنیاد موبایل آماده برای کار آفلاین',
      'foundationBody': 'دیتابیس محلی، فروش آفلاین و سیستم همگام‌سازی در مراحل بعدی موبایل فعال می‌شوند.',
      'pos': 'فروش',
      'stock': 'موجودی',
      'customers': 'مشتریان',
      'sync': 'همگام‌سازی',
      'posTitle': 'بنیاد نقطه فروش',
      'posBody': 'فروش آفلاین هنوز فعال نیست. مرحله ۱۷ ذخیره‌سازی محلی و مرحله ۱۹ فروش محلی را فعال می‌کند.',
      'stockTitle': 'بنیاد جستجوی موجودی',
      'stockBody':
          'بچ‌ها و موجودی محلی با لایه دیتابیس SQLite/Drift اضافه می‌شوند.',
      'customersTitle': 'بنیاد جستجوی مشتری',
      'customersBody': 'اطلاعات مشتری پس از آماده‌شدن دیتابیس آفلاین و قراردادهای همگام‌سازی به‌صورت محلی ذخیره می‌شود.',
      'syncTitle': 'بنیاد همگام‌سازی',
      'syncBody': 'اتصال شبکه جدا از دسترسی واقعی به سرور بررسی می‌شود. هیچ معامله بدون تأیید سرور همگام‌شده حساب نمی‌شود.',
      'networkAvailable': 'اتصال شبکه موجود است',
      'networkUnavailable': 'اتصال شبکه موجود نیست',
      'networkUnknown': 'در حال بررسی شبکه',
      'serverNotVerified': 'دسترسی به سرور جداگانه بررسی می‌شود',
      'apiConfigured': 'آدرس فارمسی برای این نسخه تنظیم شده است',
      'apiNotConfigured': 'آدرس فارمسی هنگام فعال‌سازی تنظیم می‌شود',
      'language': 'زبان',
    },
    AppLocale.pashto: <String, String>{
      'appName': 'BusinessOS فارمسي',
      'foundation': 'د آفلاین کار لپاره چمتو موبایل بنسټ',
      'foundationBody': 'ځايي ډیټابیس، آفلاین پلور او همغږي په راتلونکو موبایل پړاوونو کې فعالېږي.',
      'pos': 'پلور',
      'stock': 'ذخیره',
      'customers': 'پېرودونکي',
      'sync': 'همغږي',
      'posTitle': 'د پلور بنسټ',
      'posBody': 'آفلاین پلور لا فعال نه دی. ۱۷م پړاو ځايي ذخیره او ۱۹م پړاو ځايي-لومړی پلور فعالوي.',
      'stockTitle': 'د ذخیرې لټون بنسټ',
      'stockBody':
          'ځايي بچونه او ذخیره به د SQLite/Drift ډیټابیس له طبقې سره اضافه شي.',
      'customersTitle': 'د پېرودونکي لټون بنسټ',
      'customersBody': 'د پېرودونکو معلومات به د آفلاین ډیټابیس او همغږۍ تړونونو له چمتو کېدو وروسته ځايي وساتل شي.',
      'syncTitle': 'د همغږۍ بنسټ',
      'syncBody': 'د شبکې اړیکه د سرور له لاسرسي جلا څارل کېږي. هېڅ معامله د سرور له تایید پرته همغږې شوې نه ګڼل کېږي.',
      'networkAvailable': 'د شبکې اړیکه شته',
      'networkUnavailable': 'د شبکې اړیکه نشته',
      'networkUnknown': 'شبکه کتل کېږي',
      'serverNotVerified': 'د سرور لاسرسی جلا تاییدېږي',
      'apiConfigured': 'د فارمسي پته د دې نسخې لپاره ټاکل شوې',
      'apiNotConfigured': 'د فارمسي پته به د فعالولو پر مهال وټاکل شي',
      'language': 'ژبه',
    },
  };
}
