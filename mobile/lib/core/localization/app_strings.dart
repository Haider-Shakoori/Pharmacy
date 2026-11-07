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
  String dashboardModuleTitle(String englishTitle) {
    if (locale == AppLocale.english) return englishTitle;
    final titles = locale == AppLocale.dari
        ? <String, String>{
            'POS (Sales)': 'فروش',
            'Medicines': 'دواها',
            'Inventory': 'موجودی',
            'Purchases': 'خریدها',
            'Customers': 'مشتریان',
            'Reports': 'گزارش‌ها',
            'Alerts': 'هشدارها',
            'Sync': 'همگام‌سازی',
            'Settings': 'تنظیمات',
          }
        : <String, String>{
            'POS (Sales)': 'پلور',
            'Medicines': 'درمل',
            'Inventory': 'زېرمه',
            'Purchases': 'پېرودنې',
            'Customers': 'پېرودونکي',
            'Reports': 'راپورونه',
            'Alerts': 'خبرتیاوې',
            'Sync': 'همغږي',
            'Settings': 'تنظیمات',
          };
    return titles[englishTitle] ?? englishTitle;
  }

  String dashboardModuleDetail(String englishDetail) {
    if (locale == AppLocale.english) return englishDetail;
    final details = locale == AppLocale.dari
        ? <String, String>{
            'Offline checkout': 'فروش آفلاین',
            'Catalog & stock': 'فهرست و موجودی',
            'Batches & expiry': 'بچ‌ها و انقضا',
            'Orders & receiving': 'سفارش و دریافت',
            'Accounts': 'حساب‌ها',
            'Business insights': 'گزارش‌های تجاری',
            'Stock & expiry': 'موجودی و انقضا',
            'Offline-first': 'اولویت با آفلاین',
            'Device & access': 'دستگاه و دسترسی',
          }
        : <String, String>{
            'Offline checkout': 'افلاین پلور',
            'Catalog & stock': 'لړلیک او زېرمه',
            'Batches & expiry': 'بستې او نېټې',
            'Orders & receiving': 'فرمایشونه او ترلاسه کول',
            'Accounts': 'حسابونه',
            'Business insights': 'سوداګریز راپورونه',
            'Stock & expiry': 'زېرمه او نېټې',
            'Offline-first': 'لومړی افلاین',
            'Device & access': 'وسیله او لاسرسی',
          };
    return details[englishDetail] ?? englishDetail;
  }

  String get dashboardWorkspace => _value('dashboardWorkspace');
  String get dashboardWorkspaceSubtitle => _value('dashboardWorkspaceSubtitle');
  String get dashboardToday => _value('dashboardToday');
  String get dashboardSales => _value('dashboardSales');
  String get dashboardRevenue => _value('dashboardRevenue');
  String get dashboardPendingSync => _value('dashboardPendingSync');
  String get dashboardInventoryAlerts => _value('dashboardInventoryAlerts');
  String get dashboardViewStock => _value('dashboardViewStock');
  String get dashboardOutOfStock => _value('dashboardOutOfStock');
  String get dashboardExpiredBatches => _value('dashboardExpiredBatches');
  String get dashboardExpiringSoon => _value('dashboardExpiringSoon');
  String dashboardMedicineCount(int count) =>
      _value('dashboardMedicineCount')
          .replaceFirst('{count}', count.toString());
  String get dashboardTitle => _value('dashboardTitle');
  String get dashboardSubtitle => _value('dashboardSubtitle');
  String get dashboardQuickAccess => _value('dashboardQuickAccess');
  String get dashboardSearchHint => _value('dashboardSearchHint');
  String get dashboardNoMatches => _value('dashboardNoMatches');
  String get dashboardLocalOverview => _value('dashboardLocalOverview');
  String get dashboardRefresh => _value('dashboardRefresh');
  String get dashboardClearSearch => _value('dashboardClearSearch');
  String dashboardResultCount(int count, {required bool filtered}) =>
      _value(filtered ? 'dashboardMatchingCount' : 'dashboardAvailableCount')
          .replaceFirst('{count}', count.toString());
  String get home => _value('home');
  String get medicines => _value('medicines');
  String get reports => _value('reports');
  String get more => _value('more');
  String get purchases => _value('purchases');
  String get alerts => _value('alerts');
  String get settings => _value('settings');
  String get pos => _value('pos');
  String get stock => _value('stock');
  String get customers => _value('customers');
  String get sync => _value('sync');
  String get posTitle => _value('posTitle');
  String get posBody => _value('posBody');
  String get stockTitle => _value('stockTitle');
  String get stockBody => _value('stockBody');
  String get catalogReadOnly => _value('catalogReadOnly');
  String get catalogPermissionDenied => _value('catalogPermissionDenied');
  String get catalogRefresh => _value('catalogRefresh');
  String get catalogAllLocations => _value('catalogAllLocations');
  String get catalogEmptyStock => _value('catalogEmptyStock');
  String get catalogNoBatches => _value('catalogNoBatches');
  String get catalogExpired => _value('catalogExpired');
  String get catalogBatch => _value('catalogBatch');
  String get catalogExpiry => _value('catalogExpiry');
  String get catalogEmptyCustomers => _value('catalogEmptyCustomers');
  String get catalogSearchCustomers => _value('catalogSearchCustomers');
  String get catalogBalance => _value('catalogBalance');
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
  String get receiptTitle => _value('receiptTitle');
  String get receiptPrintTitle => _value('receiptPrintTitle');
  String get receiptNumber => _value('receiptNumber');
  String get receiptBusinessDate => _value('receiptBusinessDate');
  String get receiptCompletedAt => _value('receiptCompletedAt');
  String get receiptCashier => _value('receiptCashier');
  String get receiptCustomer => _value('receiptCustomer');
  String get receiptWalkIn => _value('receiptWalkIn');
  String get receiptLocation => _value('receiptLocation');
  String get receiptSubtotal => _value('receiptSubtotal');
  String get receiptDiscount => _value('receiptDiscount');
  String get receiptTotal => _value('receiptTotal');
  String get receiptPaid => _value('receiptPaid');
  String get receiptChange => _value('receiptChange');
  String get receiptPendingSync => _value('receiptPendingSync');
  String get receiptSynced => _value('receiptSynced');
  String get receiptThankYou => _value('receiptThankYou');
  String get receiptRecent => _value('receiptRecent');
  String get receiptPrint => _value('receiptPrint');
  String get receiptPaper58 => _value('receiptPaper58');
  String get receiptPaper80 => _value('receiptPaper80');
  String get receiptScanPrinters => _value('receiptScanPrinters');
  String get receiptScanning => _value('receiptScanning');
  String get receiptNoPrinters => _value('receiptNoPrinters');
  String get receiptUnnamedPrinter => _value('receiptUnnamedPrinter');
  String get receiptPrinted => _value('receiptPrinted');
  String get receiptPrinterConnectFailed =>
      _value('receiptPrinterConnectFailed');
  String get receiptPrintingHelp => _value('receiptPrintingHelp');
  String get alertsTitle => _value('alertsTitle');
  String get alertsRefresh => _value('alertsRefresh');
  String get alertsEnableNotifications => _value('alertsEnableNotifications');
  String get alertsNone => _value('alertsNone');

  String alertsSummary(int low, int near, int expired, int rejected) {
    return _value('alertsSummary')
        .replaceFirst('{low}', low.toString())
        .replaceFirst('{near}', near.toString())
        .replaceFirst('{expired}', expired.toString())
        .replaceFirst('{rejected}', rejected.toString());
  }

  String receiptPayment(String method) {
    return switch (method) {
      'cash' => posCash,
      'bank' => posBank,
      'mobile' => posMobile,
      _ => method,
    };
  }

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
      'dashboardWorkspace': 'Pharmacy workspace',
      'dashboardWorkspaceSubtitle': 'Offline sales and inventory workspace',
      'dashboardToday': 'Today on this device',
      'dashboardSales': 'Sales',
      'dashboardRevenue': 'Revenue',
      'dashboardPendingSync': 'Pending sync',
      'dashboardInventoryAlerts': 'Inventory alerts — local catalog',
      'dashboardViewStock': 'View stock',
      'dashboardOutOfStock': 'Out of stock',
      'dashboardExpiredBatches': 'Expired batches',
      'dashboardExpiringSoon': 'Expiring in 30 days',
      'dashboardMedicineCount':
          'Based on {count} locally synced active medicines.',
      'dashboardTitle': 'Dashboard',
      'dashboardSubtitle': 'Your pharmacy at a glance',
      'dashboardQuickAccess': 'Quick access',
      'dashboardSearchHint': 'Find a pharmacy module',
      'dashboardNoMatches': 'No matching modules',
      'dashboardLocalOverview': 'Local overview',
      'dashboardRefresh': 'Refresh local overview',
      'dashboardClearSearch': 'Clear module search',
      'dashboardMatchingCount': '{count} matching modules',
      'dashboardAvailableCount': '{count} pharmacy modules available',
      'home': 'Home',
      'medicines': 'Medicines',
      'reports': 'Reports',
      'more': 'More',
      'purchases': 'Purchases',
      'alerts': 'Alerts',
      'settings': 'Settings',
      'appName': 'BusinessOS Pharmacy',
      'foundation': 'Offline-ready mobile foundation',
      'foundationBody': 'The local database, offline POS and synchronization engine are enabled in the next mobile batches.',
      'pos': 'POS',
      'stock': 'Stock',
      'customers': 'Customers',
      'sync': 'Sync',
      'posTitle': 'Offline-first Point of Sale',
      'posBody': 'Sales are committed to this device first and queued safely for synchronization.',
      'stockTitle': 'Stock & Inventory',
      'stockBody': 'Local batch and stock snapshots will be added with the SQLite/Drift data layer.',
      'customersTitle': 'Customers',
      'customersBody': 'Customer data will be cached locally only after the offline database and sync contracts are in place.',
      'catalogReadOnly': 'Locally cached data · read only. Sync to refresh.',
      'catalogPermissionDenied':
          'Your account cannot view this pharmacy catalog.',
      'catalogRefresh': 'Refresh cached records',
      'catalogAllLocations': 'All locations',
      'catalogEmptyStock':
          'No cached medicines yet. Open Sync to download inventory.',
      'catalogNoBatches': 'No cached batches for this medicine.',
      'catalogExpired': 'Expired',
      'catalogBatch': 'Batch',
      'catalogExpiry': 'Expiry',
      'catalogEmptyCustomers':
          'No cached customers yet. Open Sync to download customers.',
      'catalogSearchCustomers': 'Search customers by name or phone',
      'catalogBalance': 'Balance',
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
      'receiptTitle': 'Sales receipt',
      'receiptPrintTitle': 'Print receipt',
      'receiptNumber': 'Receipt',
      'receiptBusinessDate': 'Business date',
      'receiptCompletedAt': 'Completed',
      'receiptCashier': 'Cashier',
      'receiptCustomer': 'Customer',
      'receiptWalkIn': 'Walk-in',
      'receiptLocation': 'Stock location',
      'receiptSubtotal': 'Subtotal',
      'receiptDiscount': 'Discount',
      'receiptTotal': 'Total',
      'receiptPaid': 'Paid',
      'receiptChange': 'Change',
      'receiptPendingSync': 'Pending synchronization',
      'receiptSynced': 'Synchronized',
      'receiptThankYou': 'Thank you',
      'receiptRecent': 'Recent receipts',
      'receiptPrint': 'Print',
      'receiptPaper58': '58 mm',
      'receiptPaper80': '80 mm',
      'receiptScanPrinters': 'Scan printers',
      'receiptScanning': 'Scanning…',
      'receiptNoPrinters': 'No Bluetooth/USB printer found.',
      'receiptUnnamedPrinter': 'Thermal printer',
      'receiptPrinted': 'Receipt sent to printer.',
      'receiptPrinterConnectFailed': 'Could not connect to the printer.',
      'receiptPrintingHelp': 'Printing uses the locally stored receipt and works without Internet. Bluetooth/BLE, USB and configured network printers are supported.',
      'alertsTitle': 'Operational alerts',
      'alertsRefresh': 'Refresh alerts',
      'alertsEnableNotifications': 'Enable local notifications',
      'alertsNone': 'No inventory or synchronization alerts.',
      'alertsSummary': '{low} low stock · {near} near expiry · {expired} expired · {rejected} sync conflicts',
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
      'dashboardWorkspace': 'محیط کاری دواخانه',
      'dashboardWorkspaceSubtitle': 'فروش و موجودی آفلاین',
      'dashboardToday': 'امروز در این دستگاه',
      'dashboardSales': 'فروش',
      'dashboardRevenue': 'عواید',
      'dashboardPendingSync': 'در انتظار همگام‌سازی',
      'dashboardInventoryAlerts': 'هشدارهای موجودی محلی',
      'dashboardViewStock': 'مشاهده موجودی',
      'dashboardOutOfStock': 'ناموجود',
      'dashboardExpiredBatches': 'بچ‌های منقضی',
      'dashboardExpiringSoon': 'انقضا در ۳۰ روز',
      'dashboardMedicineCount': 'بر اساس {count} دوای فعال همگام‌شده.',
      'dashboardTitle': 'داشبورد',
      'dashboardSubtitle': 'نمای کلی دواخانه شما',
      'dashboardQuickAccess': 'دسترسی سریع',
      'dashboardSearchHint': 'جستجوی بخش دواخانه',
      'dashboardNoMatches': 'هیچ بخشی یافت نشد',
      'dashboardLocalOverview': 'نمای کلی محلی',
      'dashboardRefresh': 'تازه‌سازی نمای کلی',
      'dashboardClearSearch': 'پاک کردن جستجو',
      'dashboardMatchingCount': '{count} بخش مطابق',
      'dashboardAvailableCount': '{count} بخش دواخانه موجود',
      'home': 'خانه',
      'medicines': 'دواها',
      'reports': 'گزارش‌ها',
      'more': 'بیشتر',
      'purchases': 'خریدها',
      'alerts': 'هشدارها',
      'settings': 'تنظیمات',
      'appName': 'فارمسی BusinessOS',
      'foundation': 'بنیاد موبایل آماده برای کار آفلاین',
      'foundationBody': 'دیتابیس محلی، فروش آفلاین و سیستم همگام‌سازی در مراحل بعدی موبایل فعال می‌شوند.',
      'pos': 'فروش',
      'stock': 'موجودی',
      'customers': 'مشتریان',
      'sync': 'همگام‌سازی',
      'posTitle': 'نقطه فروش آفلاین',
      'posBody': 'فروش ابتدا در همین دستگاه ثبت می‌شود و سپس برای همگام‌سازی در صف امن قرار می‌گیرد.',
      'stockTitle': 'موجودی و بچ‌ها',
      'stockBody':
          'بچ‌ها و موجودی محلی با لایه دیتابیس SQLite/Drift اضافه می‌شوند.',
      'customersTitle': 'مشتریان',
      'customersBody': 'اطلاعات مشتری پس از آماده‌شدن دیتابیس آفلاین و قراردادهای همگام‌سازی به‌صورت محلی ذخیره می‌شود.',
      'catalogReadOnly': 'معلومات ذخیره‌شدهٔ محلی · فقط نمایش. برای تازه‌سازی همگام‌سازی کنید.',
      'catalogPermissionDenied':
          'حساب شما اجازهٔ دیدن این اطلاعات دواخانه را ندارد.',
      'catalogRefresh': 'تازه‌سازی معلومات ذخیره‌شده',
      'catalogAllLocations': 'همه موقعیت‌ها',
      'catalogEmptyStock':
          'هنوز ادویه‌ای ذخیره نشده است. برای دریافت موجودی همگام‌سازی کنید.',
      'catalogNoBatches': 'هیچ بچ ذخیره‌شده‌ای برای این دوا وجود ندارد.',
      'catalogExpired': 'تاریخ‌گذشته',
      'catalogBatch': 'بچ',
      'catalogExpiry': 'انقضا',
      'catalogEmptyCustomers':
          'هنوز مشتری‌ای ذخیره نشده است. برای دریافت مشتریان همگام‌سازی کنید.',
      'catalogSearchCustomers': 'جستجوی مشتری با نام یا شماره تلفن',
      'catalogBalance': 'مانده',
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
      'receiptTitle': 'رسید فروش',
      'receiptPrintTitle': 'چاپ رسید',
      'receiptNumber': 'رسید',
      'receiptBusinessDate': 'تاریخ کاری',
      'receiptCompletedAt': 'زمان تکمیل',
      'receiptCashier': 'صندوقدار',
      'receiptCustomer': 'مشتری',
      'receiptWalkIn': 'مشتری حضوری',
      'receiptLocation': 'محل موجودی',
      'receiptSubtotal': 'جمع فرعی',
      'receiptDiscount': 'تخفیف',
      'receiptTotal': 'مجموع',
      'receiptPaid': 'پرداخت‌شده',
      'receiptChange': 'باقی',
      'receiptPendingSync': 'در انتظار همگام‌سازی',
      'receiptSynced': 'همگام‌شده',
      'receiptThankYou': 'تشکر',
      'receiptRecent': 'رسیدهای اخیر',
      'receiptPrint': 'چاپ',
      'receiptPaper58': '۵۸ میلی‌متر',
      'receiptPaper80': '۸۰ میلی‌متر',
      'receiptScanPrinters': 'جستجوی پرینتر',
      'receiptScanning': 'در حال جستجو…',
      'receiptNoPrinters': 'پرینتر بلوتوث/USB پیدا نشد.',
      'receiptUnnamedPrinter': 'پرینتر حرارتی',
      'receiptPrinted': 'رسید به پرینتر فرستاده شد.',
      'receiptPrinterConnectFailed': 'اتصال به پرینتر ممکن نشد.',
      'receiptPrintingHelp': 'چاپ از رسید ذخیره‌شده در دستگاه استفاده می‌کند و بدون اینترنت کار می‌کند. بلوتوث/BLE، USB و پرینتر شبکه تنظیم‌شده پشتیبانی می‌شود.',
      'alertsTitle': 'هشدارهای عملیاتی',
      'alertsRefresh': 'تازه‌سازی هشدارها',
      'alertsEnableNotifications': 'فعال‌سازی اعلان‌های محلی',
      'alertsNone': 'هشدار موجودی یا همگام‌سازی وجود ندارد.',
      'alertsSummary': '{low} موجودی کم · {near} نزدیک انقضا · {expired} منقضی · {rejected} مشکل همگام‌سازی',
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
      'dashboardWorkspace': 'د درملتون کاري چاپېریال',
      'dashboardWorkspaceSubtitle': 'افلاین پلور او زېرمه',
      'dashboardToday': 'نن په دې وسیله',
      'dashboardSales': 'پلور',
      'dashboardRevenue': 'عاید',
      'dashboardPendingSync': 'د همغږۍ په تمه',
      'dashboardInventoryAlerts': 'د ځايي زېرمې خبرتیاوې',
      'dashboardViewStock': 'زېرمه وګورئ',
      'dashboardOutOfStock': 'زېرمه نشته',
      'dashboardExpiredBatches': 'تېرې شوې بستې',
      'dashboardExpiringSoon': 'په ۳۰ ورځو کې ختمېږي',
      'dashboardMedicineCount': 'د {count} همغږو فعالو درملو پر بنسټ.',
      'dashboardTitle': 'ډشبورډ',
      'dashboardSubtitle': 'ستاسو د درملتون لنډه کتنه',
      'dashboardQuickAccess': 'چټک لاسرسی',
      'dashboardSearchHint': 'د درملتون برخه ولټوئ',
      'dashboardNoMatches': 'هیڅ برخه ونه موندل شوه',
      'dashboardLocalOverview': 'ځايي لنډیز',
      'dashboardRefresh': 'ځايي لنډیز تازه کړئ',
      'dashboardClearSearch': 'لټون پاک کړئ',
      'dashboardMatchingCount': '{count} برابرې برخې',
      'dashboardAvailableCount': '{count} د درملتون برخې شته',
      'home': 'کور',
      'medicines': 'درمل',
      'reports': 'راپورونه',
      'more': 'نور',
      'purchases': 'پېرودنې',
      'alerts': 'خبرتیاوې',
      'settings': 'تنظیمات',
      'appName': 'BusinessOS فارمسي',
      'foundation': 'د آفلاین کار لپاره چمتو موبایل بنسټ',
      'foundationBody': 'ځايي ډیټابیس، آفلاین پلور او همغږي په راتلونکو موبایل پړاوونو کې فعالېږي.',
      'pos': 'پلور',
      'stock': 'ذخیره',
      'customers': 'پېرودونکي',
      'sync': 'همغږي',
      'posTitle': 'آفلاین-لومړی پلور',
      'posBody': 'پلور لومړی په همدې وسیله خوندي کېږي او وروسته د خوندي همغږۍ لپاره په کتار کې ساتل کېږي.',
      'stockTitle': 'ذخیره او بچونه',
      'stockBody':
          'ځايي بچونه او ذخیره به د SQLite/Drift ډیټابیس له طبقې سره اضافه شي.',
      'customersTitle': 'پېرودونکي',
      'customersBody': 'د پېرودونکو معلومات به د آفلاین ډیټابیس او همغږۍ تړونونو له چمتو کېدو وروسته ځايي وساتل شي.',
      'catalogReadOnly':
          'ځايي خوندي شوي معلومات · یوازې کتل. د تازه کولو لپاره همغږي وکړئ.',
      'catalogPermissionDenied':
          'ستاسو حساب د دې فارمسي د معلوماتو د لیدلو اجازه نه لري.',
      'catalogRefresh': 'خوندي معلومات تازه کړئ',
      'catalogAllLocations': 'ټول ځایونه',
      'catalogEmptyStock':
          'لا تر اوسه درمل نه دي خوندي شوي. د ذخیرې لپاره همغږي وکړئ.',
      'catalogNoBatches': 'د دې درمل لپاره خوندي بچ نشته.',
      'catalogExpired': 'نېټه تېر',
      'catalogBatch': 'بچ',
      'catalogExpiry': 'د ختمېدو نېټه',
      'catalogEmptyCustomers':
          'لا پېرودونکي نه دي خوندي شوي. د پېرودونکو لپاره همغږي وکړئ.',
      'catalogSearchCustomers': 'پېرودونکی د نوم یا ټیلیفون له مخې ولټوئ',
      'catalogBalance': 'پاتې حساب',
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
      'receiptTitle': 'د پلور رسید',
      'receiptPrintTitle': 'رسید چاپ کړئ',
      'receiptNumber': 'رسید',
      'receiptBusinessDate': 'کاري نېټه',
      'receiptCompletedAt': 'بشپړ شوی',
      'receiptCashier': 'کیشیر',
      'receiptCustomer': 'پېرودونکی',
      'receiptWalkIn': 'حضوري پېرودونکی',
      'receiptLocation': 'د ذخیرې ځای',
      'receiptSubtotal': 'فرعي ټول',
      'receiptDiscount': 'تخفیف',
      'receiptTotal': 'ټول',
      'receiptPaid': 'ورکړل شوي',
      'receiptChange': 'باقي',
      'receiptPendingSync': 'همغږۍ ته په تمه',
      'receiptSynced': 'همغږي شوی',
      'receiptThankYou': 'مننه',
      'receiptRecent': 'وروستي رسیدونه',
      'receiptPrint': 'چاپ',
      'receiptPaper58': '۵۸ ملي متر',
      'receiptPaper80': '۸۰ ملي متر',
      'receiptScanPrinters': 'پرینټرونه ولټوئ',
      'receiptScanning': 'لټون روان دی…',
      'receiptNoPrinters': 'بلوتوث/USB پرینټر ونه موندل شو.',
      'receiptUnnamedPrinter': 'حرارتي پرینټر',
      'receiptPrinted': 'رسید پرینټر ته ولېږل شو.',
      'receiptPrinterConnectFailed': 'پرینټر سره نښلون ونه شو.',
      'receiptPrintingHelp': 'چاپ د وسیلې له ځايي خوندي شوي رسید څخه کار اخلي او انټرنېټ ته اړتیا نه لري. بلوتوث/BLE، USB او تنظیم شوي شبکوي پرینټرونه ملاتړ کېږي.',
      'alertsTitle': 'عملیاتي خبرتیاوې',
      'alertsRefresh': 'خبرتیاوې تازه کړئ',
      'alertsEnableNotifications': 'ځايي خبرتیاوې فعالې کړئ',
      'alertsNone': 'د ذخیرې یا همغږۍ کومه خبرتیا نشته.',
      'alertsSummary': '{low} کمه ذخیره · {near} ژر ختمېږي · {expired} ختم شوي · {rejected} د همغږۍ ستونزې',
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
