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
  String get connectionModeTitle => _value('connectionModeTitle');
  String get connectionModeBody => _value('connectionModeBody');
  String get mode => _value('mode');
  String get localMode => _value('localMode');
  String get cloudMode => _value('cloudMode');
  String get automaticMode => _value('automaticMode');
  String get localModeBody => _value('localModeBody');
  String get cloudModeBody => _value('cloudModeBody');
  String get automaticModeBody => _value('automaticModeBody');
  String get localServer => _value('localServer');
  String get cloudServer => _value('cloudServer');
  String get localServerHelp => _value('localServerHelp');
  String get cloudServerHelp => _value('cloudServerHelp');
  String get saveConnection => _value('saveConnection');
  String get saving => _value('saving');
  String get connectionSaved => _value('connectionSaved');
  String get registrationTitle => _value('registrationTitle');
  String get registrationBody => _value('registrationBody');
  String get licenseKey => _value('licenseKey');
  String get email => _value('email');
  String get password => _value('password');
  String get registerDevice => _value('registerDevice');
  String get registering => _value('registering');
  String get registeredDevice => _value('registeredDevice');
  String get unregisterDevice => _value('unregisterDevice');
  String get registrationRequired => _value('registrationRequired');
  String get noServerConfigured => _value('noServerConfigured');
  String get posSearch => _value('posSearch');
  String get posSearchHint => _value('posSearchHint');
  String get posLocation => _value('posLocation');
  String get posNoLocalStock => _value('posNoLocalStock');
  String get posCart => _value('posCart');
  String get posEmptyCart => _value('posEmptyCart');
  String get posQuantity => _value('posQuantity');
  String get posUnitPrice => _value('posUnitPrice');
  String get posDiscount => _value('posDiscount');
  String get posEdit => _value('posEdit');
  String get posAdd => _value('posAdd');
  String get posPaymentMethod => _value('posPaymentMethod');
  String get posCompleteSale => _value('posCompleteSale');
  String get posSaleSaved => _value('posSaleSaved');
  String get posPermissionDenied => _value('posPermissionDenied');
  String get posSyncPending => _value('posSyncPending');
  String get posTotal => _value('posTotal');
  String get posAvailable => _value('posAvailable');
  String get posCash => _value('posCash');
  String get posBank => _value('posBank');
  String get posMobile => _value('posMobile');
  String get posRemove => _value('posRemove');
  String get posInvalidValue => _value('posInvalidValue');
  String get offlineLeaseMissing => _value('offlineLeaseMissing');
  String get offlineLeaseInvalid => _value('offlineLeaseInvalid');
  String get offlineLeaseExpired => _value('offlineLeaseExpired');
  String get offlineLeaseClockError => _value('offlineLeaseClockError');
  String get syncStatusTitle => _value('syncStatusTitle');
  String get syncPending => _value('syncPending');
  String get syncRejected => _value('syncRejected');
  String get syncLast => _value('syncLast');
  String get syncNever => _value('syncNever');
  String get syncNow => _value('syncNow');
  String get syncing => _value('syncing');
  String get syncCompleted => _value('syncCompleted');
  String get syncPushed => _value('syncPushed');
  String get syncPulled => _value('syncPulled');

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
      'posTitle': 'Offline-first Point of Sale',
      'posBody': 'Sales are committed to this device first and queued safely for synchronization.',
      'stockTitle': 'Stock lookup foundation',
      'stockBody': 'Local batch and stock snapshots will be added with the SQLite/Drift data layer.',
      'customersTitle': 'Customer lookup foundation',
      'customersBody': 'Customer data will be cached locally only after the offline database and sync contracts are in place.',
      'syncTitle': 'Synchronization',
      'syncBody': 'Push offline sales only after server acknowledgement, then pull incremental catalog, inventory and customer updates.',
      'networkAvailable': 'Network link available',
      'networkUnavailable': 'No network link',
      'networkUnknown': 'Checking network link',
      'serverNotVerified': 'Server reachability is verified separately',
      'apiConfigured': 'Tenant endpoint configured for this build',
      'apiNotConfigured': 'Tenant endpoint will be set during activation',
      'language': 'Language',
      'connectionModeTitle': 'Connection mode',
      'connectionModeBody': 'Choose how this device reaches the pharmacy server. Offline remains available in every mode.',
      'mode': 'Mode',
      'localMode': 'Local',
      'cloudMode': 'Cloud',
      'automaticMode': 'Automatic',
      'localModeBody': 'Use the pharmacy server on the same LAN/Wi-Fi. Manual private IP or hostname is supported.',
      'cloudModeBody':
          'Use the pharmacy tenant HTTPS server over the Internet.',
      'automaticModeBody': 'Prefer the local server, fall back to cloud, then keep working offline when neither is reachable.',
      'localServer': 'Local pharmacy server',
      'cloudServer': 'Cloud pharmacy server',
      'localServerHelp': 'Example: 192.168.1.20 or pharmacy-server.local',
      'cloudServerHelp': 'HTTPS is required for cloud connections.',
      'saveConnection': 'Save connection',
      'saving': 'Saving…',
      'connectionSaved': 'Connection settings saved.',
      'registrationTitle': 'Device registration',
      'registrationBody': 'Register this Android device with a valid pharmacy license and an active pharmacy user. Local, Cloud and Automatic modes share the same tenant identity.',
      'licenseKey': 'License key',
      'email': 'Email',
      'password': 'Password',
      'registerDevice': 'Register device',
      'registering': 'Registering…',
      'registeredDevice': 'Registered device',
      'unregisterDevice': 'Change pharmacy / unregister',
      'registrationRequired': 'Device registration is required before transactional POS is enabled.',
      'noServerConfigured':
          'No reachable server is configured for the selected connection mode.',
      'posSearch': 'Search medicine',
      'posSearchHint': 'Brand, generic name or medicine code',
      'posLocation': 'Stock location',
      'posNoLocalStock': 'No local stock snapshot is available yet. Complete the first synchronization before offline selling.',
      'posCart': 'Cart',
      'posEmptyCart': 'Add medicines from the local catalog to start a sale.',
      'posQuantity': 'Quantity',
      'posUnitPrice': 'Unit price',
      'posDiscount': 'Discount',
      'posEdit': 'Apply',
      'posAdd': 'Add',
      'posPaymentMethod': 'Payment method',
      'posCompleteSale': 'Complete offline sale',
      'posSaleSaved': 'Sale saved locally.',
      'posPermissionDenied':
          'Your pharmacy account does not have POS sale permission.',
      'posSyncPending': 'Pending synchronization',
      'posTotal': 'Total',
      'posAvailable': 'Available',
      'posCash': 'Cash',
      'posBank': 'Bank',
      'posMobile': 'Mobile payment',
      'posRemove': 'Remove',
      'posInvalidValue': 'Enter valid quantity, price and discount values.',
      'syncStatusTitle': 'Synchronization status',
      'syncPending': 'Pending',
      'syncRejected': 'Rejected',
      'syncLast': 'Last sync',
      'syncNever': 'This device has not completed a sync yet.',
      'syncNow': 'Sync now',
      'syncing': 'Synchronizing…',
      'syncCompleted': 'Synchronization completed',
      'syncPushed': 'sales pushed',
      'syncPulled': 'records pulled',
    },
    AppLocale.dari: <String, String>{
      'appName': 'فارمسی BusinessOS',
      'foundation': 'بنیاد موبایل آماده برای کار آفلاین',
      'foundationBody': 'دیتابیس محلی، فروش آفلاین و سیستم همگام‌سازی در مراحل بعدی موبایل فعال می‌شوند.',
      'pos': 'فروش',
      'stock': 'موجودی',
      'customers': 'مشتریان',
      'sync': 'همگام‌سازی',
      'posTitle': 'نقطه فروش آفلاین',
      'posBody': 'فروش ابتدا در همین دستگاه ثبت می‌شود و سپس برای همگام‌سازی در صف امن قرار می‌گیرد.',
      'stockTitle': 'بنیاد جستجوی موجودی',
      'stockBody':
          'بچ‌ها و موجودی محلی با لایه دیتابیس SQLite/Drift اضافه می‌شوند.',
      'customersTitle': 'بنیاد جستجوی مشتری',
      'customersBody': 'اطلاعات مشتری پس از آماده‌شدن دیتابیس آفلاین و قراردادهای همگام‌سازی به‌صورت محلی ذخیره می‌شود.',
      'syncTitle': 'همگام‌سازی',
      'syncBody': 'فروش‌های آفلاین فقط پس از تأیید سرور ارسال می‌شوند و سپس دواها، موجودی و مشتریان به‌صورت افزایشی دریافت می‌شوند.',
      'networkAvailable': 'اتصال شبکه موجود است',
      'networkUnavailable': 'اتصال شبکه موجود نیست',
      'networkUnknown': 'در حال بررسی شبکه',
      'serverNotVerified': 'دسترسی به سرور جداگانه بررسی می‌شود',
      'apiConfigured': 'آدرس فارمسی برای این نسخه تنظیم شده است',
      'apiNotConfigured': 'آدرس فارمسی هنگام فعال‌سازی تنظیم می‌شود',
      'language': 'زبان',
      'connectionModeTitle': 'حالت اتصال',
      'connectionModeBody': 'روش اتصال این دستگاه به سرور فارمسی را انتخاب کنید. کار آفلاین در هر حالت فعال می‌ماند.',
      'mode': 'حالت',
      'localMode': 'محلی',
      'cloudMode': 'ابری',
      'automaticMode': 'خودکار',
      'localModeBody': 'از سرور فارمسی در همان شبکه LAN/Wi-Fi استفاده می‌کند. آی‌پی خصوصی یا نام میزبان قابل ورود است.',
      'cloudModeBody':
          'از طریق اینترنت به سرور HTTPS اختصاصی فارمسی وصل می‌شود.',
      'automaticModeBody': 'ابتدا سرور محلی، سپس سرور ابری و در نبود هر دو کار آفلاین ادامه می‌یابد.',
      'localServer': 'سرور محلی فارمسی',
      'cloudServer': 'سرور ابری فارمسی',
      'localServerHelp': 'نمونه: 192.168.1.20 یا pharmacy-server.local',
      'cloudServerHelp': 'برای اتصال ابری HTTPS الزامی است.',
      'saveConnection': 'ذخیره اتصال',
      'saving': 'در حال ذخیره…',
      'connectionSaved': 'تنظیمات اتصال ذخیره شد.',
      'registrationTitle': 'ثبت دستگاه',
      'registrationBody': 'این دستگاه اندروید را با جواز معتبر فارمسی و حساب فعال کاربر ثبت کنید. حالت‌های محلی، ابری و خودکار از یک هویت فارمسی استفاده می‌کنند.',
      'licenseKey': 'کلید جواز',
      'email': 'ایمیل',
      'password': 'رمز عبور',
      'registerDevice': 'ثبت دستگاه',
      'registering': 'در حال ثبت…',
      'registeredDevice': 'دستگاه ثبت‌شده',
      'unregisterDevice': 'تغییر فارمسی / لغو ثبت',
      'registrationRequired':
          'پیش از فعال‌شدن فروش تراکنشی، ثبت دستگاه الزامی است.',
      'noServerConfigured':
          'برای حالت اتصال انتخاب‌شده هیچ سرور قابل دسترس تنظیم نشده است.',
      'posSearch': 'جستجوی دوا',
      'posSearchHint': 'نام تجارتی، نام جنریک یا کود دوا',
      'posLocation': 'محل موجودی',
      'posNoLocalStock': 'هنوز موجودی محلی دریافت نشده است. پیش از فروش آفلاین اولین همگام‌سازی را انجام دهید.',
      'posCart': 'سبد فروش',
      'posEmptyCart': 'برای آغاز فروش دوا را از فهرست محلی اضافه کنید.',
      'posQuantity': 'تعداد',
      'posUnitPrice': 'قیمت واحد',
      'posDiscount': 'تخفیف',
      'posEdit': 'اعمال',
      'posAdd': 'افزودن',
      'posPaymentMethod': 'روش پرداخت',
      'posCompleteSale': 'تکمیل فروش آفلاین',
      'posSaleSaved': 'فروش در دستگاه ذخیره شد.',
      'posPermissionDenied': 'حساب شما اجازه فروش POS را ندارد.',
      'posSyncPending': 'در انتظار همگام‌سازی',
      'posTotal': 'مجموع',
      'posAvailable': 'موجود',
      'posCash': 'نقد',
      'posBank': 'بانک',
      'posMobile': 'پرداخت موبایلی',
      'posRemove': 'حذف',
      'posInvalidValue': 'تعداد، قیمت و تخفیف معتبر وارد کنید.',
      'syncStatusTitle': 'وضعیت همگام‌سازی',
      'syncPending': 'در انتظار',
      'syncRejected': 'ردشده',
      'syncLast': 'آخرین همگام‌سازی',
      'syncNever': 'این دستگاه هنوز همگام‌سازی کامل انجام نداده است.',
      'syncNow': 'همگام‌سازی اکنون',
      'syncing': 'در حال همگام‌سازی…',
      'syncCompleted': 'همگام‌سازی تکمیل شد',
      'syncPushed': 'فروش ارسال شد',
      'syncPulled': 'رکورد دریافت شد',
    },
    AppLocale.pashto: <String, String>{
      'appName': 'BusinessOS فارمسي',
      'foundation': 'د آفلاین کار لپاره چمتو موبایل بنسټ',
      'foundationBody': 'ځايي ډیټابیس، آفلاین پلور او همغږي په راتلونکو موبایل پړاوونو کې فعالېږي.',
      'pos': 'پلور',
      'stock': 'ذخیره',
      'customers': 'پېرودونکي',
      'sync': 'همغږي',
      'posTitle': 'آفلاین-لومړی پلور',
      'posBody': 'پلور لومړی په همدې وسیله خوندي کېږي او وروسته د خوندي همغږۍ لپاره په کتار کې ساتل کېږي.',
      'stockTitle': 'د ذخیرې لټون بنسټ',
      'stockBody':
          'ځايي بچونه او ذخیره به د SQLite/Drift ډیټابیس له طبقې سره اضافه شي.',
      'customersTitle': 'د پېرودونکي لټون بنسټ',
      'customersBody': 'د پېرودونکو معلومات به د آفلاین ډیټابیس او همغږۍ تړونونو له چمتو کېدو وروسته ځايي وساتل شي.',
      'syncTitle': 'همغږي',
      'syncBody': 'آفلاین پلور یوازې د سرور له تایید وروسته لېږل کېږي، بیا درمل، ذخیره او پېرودونکي په تدریجي ډول راکښته کېږي.',
      'networkAvailable': 'د شبکې اړیکه شته',
      'networkUnavailable': 'د شبکې اړیکه نشته',
      'networkUnknown': 'شبکه کتل کېږي',
      'serverNotVerified': 'د سرور لاسرسی جلا تاییدېږي',
      'apiConfigured': 'د فارمسي پته د دې نسخې لپاره ټاکل شوې',
      'apiNotConfigured': 'د فارمسي پته به د فعالولو پر مهال وټاکل شي',
      'language': 'ژبه',
      'connectionModeTitle': 'د نښلون حالت',
      'connectionModeBody': 'وټاکئ چې دا وسیله څنګه د فارمسي سرور ته نښلي. آفلاین کار په ټولو حالتونو کې پاتې کېږي.',
      'mode': 'حالت',
      'localMode': 'محلي',
      'cloudMode': 'کلاوډ',
      'automaticMode': 'اتومات',
      'localModeBody': 'په هماغه LAN/Wi-Fi کې د فارمسي ځايي سرور کاروي. شخصي IP یا کوربه نوم داخلېدای شي.',
      'cloudModeBody': 'د انټرنېټ له لارې د فارمسي ځانګړي HTTPS سرور کاروي.',
      'automaticModeBody': 'لومړی ځايي سرور، بیا کلاوډ، او که دواړه نه وي آفلاین کار ته دوام ورکوي.',
      'localServer': 'د فارمسي ځايي سرور',
      'cloudServer': 'د فارمسي کلاوډ سرور',
      'localServerHelp': 'بېلګه: 192.168.1.20 یا pharmacy-server.local',
      'cloudServerHelp': 'د کلاوډ نښلون لپاره HTTPS اړین دی.',
      'saveConnection': 'نښلون خوندي کړئ',
      'saving': 'خوندي کېږي…',
      'connectionSaved': 'د نښلون امستنې خوندي شوې.',
      'registrationTitle': 'د وسیلې ثبت',
      'registrationBody': 'دا Android وسیله د فارمسي له معتبر جواز او فعال کارن حساب سره ثبت کړئ. محلي، کلاوډ او اتومات حالتونه د همدې فارمسي یو هویت کاروي.',
      'licenseKey': 'د جواز کیلي',
      'email': 'برېښنالیک',
      'password': 'پټنوم',
      'registerDevice': 'وسیله ثبت کړئ',
      'registering': 'ثبتېږي…',
      'registeredDevice': 'ثبت شوې وسیله',
      'unregisterDevice': 'فارمسي بدلول / ثبت لغوه کول',
      'registrationRequired':
          'د معاملاتي پلور له فعالېدو مخکې د وسیلې ثبت اړین دی.',
      'noServerConfigured':
          'د ټاکل شوي نښلون حالت لپاره د لاسرسي وړ سرور نه دی تنظیم شوی.',
      'posSearch': 'د درملو لټون',
      'posSearchHint': 'برانډ، جنریک نوم یا د درملو کوډ',
      'posLocation': 'د ذخیرې ځای',
      'posNoLocalStock': 'لا تر اوسه ځايي ذخیره نشته. د آفلاین پلور مخکې لومړۍ همغږي بشپړه کړئ.',
      'posCart': 'د پلور ټوکرۍ',
      'posEmptyCart': 'د پلور د پیل لپاره له ځايي کتلاګ څخه درمل ورزیات کړئ.',
      'posQuantity': 'مقدار',
      'posUnitPrice': 'د واحد بیه',
      'posDiscount': 'تخفیف',
      'posEdit': 'تطبیق',
      'posAdd': 'زیاتول',
      'posPaymentMethod': 'د تادیې طریقه',
      'posCompleteSale': 'آفلاین پلور بشپړ کړئ',
      'posSaleSaved': 'پلور په وسیله کې خوندي شو.',
      'posPermissionDenied': 'ستاسو د فارمسي حساب د POS پلور اجازه نه لري.',
      'posSyncPending': 'همغږۍ ته په تمه',
      'posTotal': 'ټول',
      'posAvailable': 'شته',
      'posCash': 'نغدې',
      'posBank': 'بانک',
      'posMobile': 'موبایل تادیه',
      'posRemove': 'لرې کول',
      'posInvalidValue': 'سم مقدار، بیه او تخفیف داخل کړئ.',
      'syncStatusTitle': 'د همغږۍ حالت',
      'syncPending': 'په تمه',
      'syncRejected': 'رد شوي',
      'syncLast': 'وروستۍ همغږي',
      'syncNever': 'دې وسیلې لا بشپړه همغږي نه ده کړې.',
      'syncNow': 'اوس همغږي کړئ',
      'syncing': 'همغږي کېږي…',
      'syncCompleted': 'همغږي بشپړه شوه',
      'syncPushed': 'پلور ولېږل شو',
      'syncPulled': 'ریکارډ راکښته شو',
    },
  };
}
