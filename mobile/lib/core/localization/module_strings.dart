import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:flutter/widgets.dart';

/// Translations for the screens added in the mobile redesign (dashboard,
/// stock workspace, reports, POS layout, the 12-module launcher and the
/// medicine scan review section).
///
/// English is the fallback for any missing key. Dari and Pashto are drafts
/// and should be reviewed by a native speaker before release.
class ModuleStrings {
  const ModuleStrings._(this.locale);

  final AppLocale locale;

  static ModuleStrings of(BuildContext context) {
    return ModuleStrings._(
      AppLocale.fromCode(Localizations.localeOf(context).languageCode),
    );
  }

  String _t(String key) {
    return _table[locale]?[key] ?? _table[AppLocale.english]![key] ?? key;
  }

  String _fill(String key, Map<String, String> values) {
    var text = _t(key);
    values.forEach((String name, String value) {
      text = text.replaceAll('{$name}', value);
    });
    return text;
  }

  // Dashboard
  String get dashboard => _t('dashboard');
  String get salesToday => _t('salesToday');
  String get invoices => _t('invoices');
  String get lowStock => _t('lowStock');
  String get expiringSoon => _t('expiringSoon');
  String get expiredBatches => _t('expiredBatches');
  String get pendingSync => _t('pendingSync');
  String get quickAccess => _t('quickAccess');
  String get refreshLocalOverview => _t('refreshLocalOverview');
  String get dashboardUnavailable => _t('dashboardUnavailable');
  String get dashboardLoadError => _t('dashboardLoadError');
  String get unableToLoadLocalData => _t('unableToLoadLocalData');

  // Modules
  String get moduleSales => _t('moduleSales');
  String get moduleMedicines => _t('moduleMedicines');
  String get moduleInventory => _t('moduleInventory');
  String get moduleReports => _t('moduleReports');
  String get modulePurchases => _t('modulePurchases');
  String get moduleCustomers => _t('moduleCustomers');
  String get moduleSuppliers => _t('moduleSuppliers');
  String get moduleFinance => _t('moduleFinance');
  String get moduleTransfers => _t('moduleTransfers');
  String get moduleAlerts => _t('moduleAlerts');
  String get moduleSync => _t('moduleSync');
  String get moduleSettings => _t('moduleSettings');
  String get soon => _t('soon');

  // Stock workspace
  String get stock => _t('stock');
  String get stockReadOnly => _t('stockReadOnly');
  String get metricMedicines => _t('metricMedicines');
  String get metricOutOfStock => _t('metricOutOfStock');
  String get metricExpiring30 => _t('metricExpiring30');
  String get searchMedicine => _t('searchMedicine');
  String get refresh => _t('refresh');
  String get location => _t('location');
  String get allLocations => _t('allLocations');
  String get tabStock => _t('tabStock');
  String get tabBatches => _t('tabBatches');
  String get tabExpiry => _t('tabExpiry');
  String get tabStorage => _t('tabStorage');
  String get registerToViewStock => _t('registerToViewStock');
  String get stockNotPermitted => _t('stockNotPermitted');
  String get noMedicinesMatch => _t('noMedicinesMatch');
  String get previousPage => _t('previousPage');
  String get nextPage => _t('nextPage');
  String pageNumber(int number) =>
      _fill('pageNumber', <String, String>{'n': '$number'});
  String get noExpiryMatches => _t('noExpiryMatches');
  String get noBatchMatches => _t('noBatchMatches');
  String get batch => _t('batch');
  String get expires => _t('expires');
  String get expired => _t('expired');
  String get storageTitle => _t('storageTitle');
  String get storageBody => _t('storageBody');
  String get addStorageLocation => _t('addStorageLocation');
  String get storageComingSoon => _t('storageComingSoon');

  // Reports
  String get reports => _t('reports');
  String get reportsSubtitle => _t('reportsSubtitle');
  String get registerToViewReports => _t('registerToViewReports');
  String get back => _t('back');
  String get invoicesCount => _t('invoicesCount');
  String get revenueAfn => _t('revenueAfn');
  String get pendingSyncShort => _t('pendingSyncShort');
  String get dailyInvoices => _t('dailyInvoices');
  String get unableToReadSales => _t('unableToReadSales');
  String get unableToReadInventory => _t('unableToReadInventory');
  String get unableToReadExpiry => _t('unableToReadExpiry');
  String get unableToReadCustomers => _t('unableToReadCustomers');
  String get noExpiryOnDevice => _t('noExpiryOnDevice');
  String get noCustomersOnDevice => _t('noCustomersOnDevice');
  String get acquisitionValue => _t('acquisitionValue');
  String get acquisitionSubtitle => _t('acquisitionSubtitle');
  String get reportNotAvailable => _t('reportNotAvailable');
  String get salesReport => _t('salesReport');
  String get salesReportDetail => _t('salesReportDetail');
  String get inventoryReport => _t('inventoryReport');
  String get inventoryReportDetail => _t('inventoryReportDetail');
  String get expiryReport => _t('expiryReport');
  String get expiryReportDetail => _t('expiryReportDetail');
  String get customerReport => _t('customerReport');
  String get customerReportDetail => _t('customerReportDetail');
  String get profitLoss => _t('profitLoss');
  String get profitLossDetail => _t('profitLossDetail');
  String get purchaseReport => _t('purchaseReport');
  String get purchaseReportDetail => _t('purchaseReportDetail');
  String get financialReport => _t('financialReport');
  String get financialReportDetail => _t('financialReportDetail');
  String get today => _t('today');
  String get days7 => _t('days7');
  String get days30 => _t('days30');

  // POS
  String get subtotal => _t('subtotal');
  String get discount => _t('discount');
  String get hold => _t('hold');
  String get holdNotAvailable => _t('holdNotAvailable');
  String get pay => _t('pay');
  String get decreaseQuantity => _t('decreaseQuantity');
  String get increaseQuantity => _t('increaseQuantity');
  String get eachSuffix => _t('eachSuffix');

  // Medicine scan review
  String get scanLabel => _t('scanLabel');
  String get scanDraftNote => _t('scanDraftNote');
  String get registerReviewed => _t('registerReviewed');
  String get registerNeedsApproval => _t('registerNeedsApproval');
  String get checkingDuplicates => _t('checkingDuplicates');
  String get duplicateUnavailable => _t('duplicateUnavailable');
  String get doNotRegister => _t('doNotRegister');
  String get noLocalMatches => _t('noLocalMatches');
  String get serverVerifyNeeded => _t('serverVerifyNeeded');
  String get approveHandoff => _t('approveHandoff');
  String get approveHandoffBody => _t('approveHandoffBody');
  String get prepareHandoff => _t('prepareHandoff');
  String get prepareHandoffBody => _t('prepareHandoffBody');
  String get copyHandoffTooltip => _t('copyHandoffTooltip');
  String get registrationDisabled => _t('registrationDisabled');
  String get registrationDisabledBody => _t('registrationDisabledBody');
  String get recheckTooltip => _t('recheckTooltip');
  String get reviewedDraftOnly => _t('reviewedDraftOnly');
  String get copyReviewedTooltip => _t('copyReviewedTooltip');
  String get clearDraftTooltip => _t('clearDraftTooltip');
  String get registeredSynced => _t('registeredSynced');
  String get handoffCopied => _t('handoffCopied');
  String get reviewedCopied => _t('reviewedCopied');
  String possibleDuplicatesCount(int count) =>
      _fill('possibleDuplicatesCount', <String, String>{'count': '$count'});
  String catalogSyncFailed(String error) =>
      _fill('catalogSyncFailed', <String, String>{'error': error});
  String handoffBlocked(String error) =>
      _fill('handoffBlocked', <String, String>{'error': error});
  String serverDuplicates(String list) =>
      _fill('serverDuplicates', <String, String>{'list': list});

  static const Map<AppLocale, Map<String, String>> _table =
      <AppLocale, Map<String, String>>{
        AppLocale.english: <String, String>{
          'dashboard': 'Dashboard',
          'salesToday': 'Sales today',
          'invoices': 'Invoices',
          'lowStock': 'Low stock',
          'expiringSoon': 'Expiring soon',
          'expiredBatches': 'Expired batches',
          'pendingSync': 'Pending sync',
          'quickAccess': 'Quick access',
          'refreshLocalOverview': 'Refresh local overview',
          'dashboardUnavailable': 'Local dashboard unavailable',
          'dashboardLoadError': 'Unable to load dashboard',
          'unableToLoadLocalData': 'Unable to load local data.',
          'moduleSales': 'POS',
          'moduleMedicines': 'Medicines',
          'moduleInventory': 'Inventory',
          'moduleReports': 'Reports',
          'modulePurchases': 'Purchases',
          'moduleCustomers': 'Customers',
          'moduleSuppliers': 'Suppliers',
          'moduleFinance': 'Finance',
          'moduleTransfers': 'Transfers',
          'moduleAlerts': 'Alerts',
          'moduleSync': 'Sync',
          'moduleSettings': 'Settings',
          'soon': 'Soon',
          'stock': 'Stock',
          'stockReadOnly': 'Read-only. Based on locally synced data.',
          'metricMedicines': 'Medicines',
          'metricOutOfStock': 'Out of stock',
          'metricExpiring30': 'Expiring 30 days',
          'searchMedicine': 'Search medicine or code',
          'refresh': 'Refresh',
          'location': 'Location',
          'allLocations': 'All locations',
          'tabStock': 'Stock',
          'tabBatches': 'Batches',
          'tabExpiry': 'Expiry',
          'tabStorage': 'Storage',
          'registerToViewStock': 'Register this device to view stock.',
          'stockNotPermitted': 'Stock access is not permitted for this user.',
          'noMedicinesMatch': 'No medicines match this search.',
          'previousPage': 'Previous page',
          'nextPage': 'Next page',
          'pageNumber': 'Page {n}',
          'noExpiryMatches': 'No expired or expiring batches.',
          'noBatchMatches': 'No batches match this search.',
          'batch': 'Batch',
          'expires': 'Expires',
          'expired': 'Expired',
          'storageTitle': 'Medicine storage',
          'storageBody':
              'Storage locations (branch, room, rack, shelf and bin) are not available on this device yet. Batches stay in their recorded location until this feature is enabled.',
          'addStorageLocation': 'Add storage location',
          'storageComingSoon':
              'Coming soon: create locations, assign batches, stock counts and transfers.',
          'reports': 'Reports',
          'reportsSubtitle':
              'Totals on this device. Company-wide reports need server data.',
          'registerToViewReports': 'Register this device to view reports.',
          'back': 'Back',
          'invoicesCount': 'Invoices',
          'revenueAfn': 'Revenue (AFN)',
          'pendingSyncShort': 'Pending sync',
          'dailyInvoices': 'Daily invoices',
          'unableToReadSales': 'Unable to read local sales.',
          'unableToReadInventory': 'Unable to read local inventory.',
          'unableToReadExpiry': 'Unable to read local expiry data.',
          'unableToReadCustomers': 'Unable to read local customers.',
          'noExpiryOnDevice': 'No expired or expiring batches on this device.',
          'noCustomersOnDevice': 'No customers synced to this device.',
          'acquisitionValue': 'Remaining stock acquisition value',
          'acquisitionSubtitle':
              'active, non-expired batches · based on recorded unit costs',
          'reportNotAvailable': 'This report is not available yet.',
          'salesReport': 'Sales Report',
          'salesReportDetail': 'Invoices, revenue and daily trend on this device',
          'inventoryReport': 'Inventory Report',
          'inventoryReportDetail': 'Stock levels and acquisition value',
          'expiryReport': 'Expiry Report',
          'expiryReportDetail': 'Batches expired or expiring within 30 days',
          'customerReport': 'Customer Report',
          'customerReportDetail': 'Customer contacts and balances',
          'profitLoss': 'Profit & Loss',
          'profitLossDetail': 'Needs cost and expense data from the server',
          'purchaseReport': 'Purchase Report',
          'purchaseReportDetail': 'Needs supplier purchase history from the server',
          'financialReport': 'Financial Report',
          'financialReportDetail': 'Needs receivables and payables from the server',
          'today': 'Today',
          'days7': '7 days',
          'days30': '30 days',
          'subtotal': 'Subtotal',
          'discount': 'Discount',
          'hold': 'Hold',
          'holdNotAvailable': 'Hold is not available yet',
          'pay': 'Pay',
          'decreaseQuantity': 'Decrease quantity',
          'increaseQuantity': 'Increase quantity',
          'eachSuffix': 'each',
          'scanLabel': 'Scan medicine label',
          'scanDraftNote': 'Offline draft — pharmacist review required',
          'registerReviewed': 'Register reviewed medicine',
          'registerNeedsApproval': 'Online registration requires pharmacist approval.',
          'checkingDuplicates': 'Checking local catalog for duplicates...',
          'duplicateUnavailable': 'Duplicate check unavailable',
          'doNotRegister': 'Do not register until catalog duplicates are checked.',
          'noLocalMatches': 'No matches in locally synced catalog',
          'serverVerifyNeeded': 'Server-side verification is still required.',
          'approveHandoff': 'Approve registration handoff',
          'approveHandoffBody':
              'I have checked the reviewed details. This does not authorize saving to the server.',
          'prepareHandoff': 'Prepare registration handoff',
          'prepareHandoffBody':
              'Copies verified draft fields only. No server submission.',
          'copyHandoffTooltip': 'Copy registration handoff',
          'registrationDisabled': 'Medicine registration is not enabled',
          'registrationDisabledBody':
              'A pharmacist must verify the current server catalog and resolve possible duplicates before saving. This scan remains a draft and does not change stock.',
          'recheckTooltip': 'Recheck local catalog',
          'reviewedDraftOnly': 'Reviewed draft only. No medicine or stock saved.',
          'copyReviewedTooltip': 'Copy reviewed medicine details',
          'clearDraftTooltip': 'Clear reviewed draft',
          'registeredSynced': 'Medicine registered and catalog synchronized.',
          'handoffCopied':
              'Handoff copied. Server validation and pharmacist registration are still required.',
          'reviewedCopied':
              'Reviewed details copied. Register medicine manually; nothing was saved.',
          'possibleDuplicatesCount': 'Possible duplicates: {count}',
          'catalogSyncFailed':
              'Medicine saved online, but catalog sync failed: {error}. Use Sync Now to retry.',
          'handoffBlocked': 'Handoff blocked: {error}',
          'serverDuplicates': 'Possible server duplicates: {list}',
        },
        AppLocale.dari: <String, String>{
          'dashboard': 'داشبورد',
          'salesToday': 'فروش امروز',
          'invoices': 'فاکتورها',
          'lowStock': 'موجودی کم',
          'expiringSoon': 'نزدیک انقضا',
          'expiredBatches': 'بچ‌های منقضی',
          'pendingSync': 'در انتظار همگام‌سازی',
          'quickAccess': 'دسترسی سریع',
          'refreshLocalOverview': 'تازه‌سازی نمای محلی',
          'dashboardUnavailable': 'داشبورد محلی در دسترس نیست',
          'dashboardLoadError': 'بارگذاری داشبورد ممکن نشد',
          'unableToLoadLocalData': 'بارگذاری داده‌های محلی ممکن نشد.',
          'moduleSales': 'فروش',
          'moduleMedicines': 'دواها',
          'moduleInventory': 'موجودی',
          'moduleReports': 'گزارش‌ها',
          'modulePurchases': 'خریدها',
          'moduleCustomers': 'مشتریان',
          'moduleSuppliers': 'تأمین‌کنندگان',
          'moduleFinance': 'مالی',
          'moduleTransfers': 'انتقالات',
          'moduleAlerts': 'هشدارها',
          'moduleSync': 'همگام‌سازی',
          'moduleSettings': 'تنظیمات',
          'soon': 'به‌زودی',
          'stock': 'موجودی',
          'stockReadOnly': 'فقط خواندنی. بر اساس داده‌های همگام‌شدهٔ محلی.',
          'metricMedicines': 'دواها',
          'metricOutOfStock': 'ناموجود',
          'metricExpiring30': 'انقضا در ۳۰ روز',
          'searchMedicine': 'جستجوی دوا یا کد',
          'refresh': 'تازه‌سازی',
          'location': 'محل',
          'allLocations': 'همه محل‌ها',
          'tabStock': 'موجودی',
          'tabBatches': 'بچ‌ها',
          'tabExpiry': 'انقضا',
          'tabStorage': 'انبار',
          'registerToViewStock': 'برای مشاهدهٔ موجودی، این دستگاه را ثبت کنید.',
          'stockNotPermitted': 'دسترسی به موجودی برای این کاربر مجاز نیست.',
          'noMedicinesMatch': 'هیچ دوایی با این جستجو پیدا نشد.',
          'previousPage': 'صفحهٔ قبلی',
          'nextPage': 'صفحهٔ بعدی',
          'pageNumber': 'صفحه {n}',
          'noExpiryMatches': 'بچ منقضی یا نزدیک انقضا وجود ندارد.',
          'noBatchMatches': 'هیچ بچی با این جستجو پیدا نشد.',
          'batch': 'بچ',
          'expires': 'انقضا',
          'expired': 'منقضی',
          'storageTitle': 'انبار دوا',
          'storageBody':
              'محل‌های انبار (شاخه، اتاق، قفسه، طبقه و جعبه) هنوز روی این دستگاه فعال نیستند. تا فعال شدن این قابلیت، بچ‌ها در محل ثبت‌شدهٔ خود باقی می‌مانند.',
          'addStorageLocation': 'افزودن محل انبار',
          'storageComingSoon':
              'به‌زودی: ایجاد محل، تخصیص بچ‌ها، شمارش موجودی و انتقال.',
          'reports': 'گزارش‌ها',
          'reportsSubtitle':
              'مجموع‌ها روی این دستگاه. گزارش‌های سراسری به داده‌های سرور نیاز دارند.',
          'registerToViewReports': 'برای مشاهدهٔ گزارش‌ها، این دستگاه را ثبت کنید.',
          'back': 'بازگشت',
          'invoicesCount': 'فاکتورها',
          'revenueAfn': 'عواید (افغانی)',
          'pendingSyncShort': 'در انتظار همگام‌سازی',
          'dailyInvoices': 'فاکتورهای روزانه',
          'unableToReadSales': 'خواندن فروش‌های محلی ممکن نشد.',
          'unableToReadInventory': 'خواندن موجودی محلی ممکن نشد.',
          'unableToReadExpiry': 'خواندن اطلاعات انقضای محلی ممکن نشد.',
          'unableToReadCustomers': 'خواندن مشتریان محلی ممکن نشد.',
          'noExpiryOnDevice': 'روی این دستگاه بچ منقضی یا نزدیک انقضا نیست.',
          'noCustomersOnDevice': 'مشتری‌ای روی این دستگاه همگام نشده است.',
          'acquisitionValue': 'ارزش خرید موجودی باقی‌مانده',
          'acquisitionSubtitle':
              'بچ‌های فعال و غیرمنقضی · بر اساس قیمت خرید ثبت‌شده',
          'reportNotAvailable': 'این گزارش هنوز در دسترس نیست.',
          'salesReport': 'گزارش فروش',
          'salesReportDetail': 'فاکتورها، عواید و روند روزانه روی این دستگاه',
          'inventoryReport': 'گزارش موجودی',
          'inventoryReportDetail': 'سطح موجودی و ارزش خرید',
          'expiryReport': 'گزارش انقضا',
          'expiryReportDetail': 'بچ‌های منقضی یا نزدیک انقضا تا ۳۰ روز',
          'customerReport': 'گزارش مشتریان',
          'customerReportDetail': 'تماس‌ها و مانده‌های مشتریان',
          'profitLoss': 'سود و زیان',
          'profitLossDetail': 'به داده‌های بهای تمام‌شده و هزینه از سرور نیاز دارد',
          'purchaseReport': 'گزارش خرید',
          'purchaseReportDetail': 'به تاریخچهٔ خرید تأمین‌کننده از سرور نیاز دارد',
          'financialReport': 'گزارش مالی',
          'financialReportDetail': 'به طلب‌ها و بدهی‌ها از سرور نیاز دارد',
          'today': 'امروز',
          'days7': '۷ روز',
          'days30': '۳۰ روز',
          'subtotal': 'جمع فرعی',
          'discount': 'تخفیف',
          'hold': 'تعلیق',
          'holdNotAvailable': 'تعلیق فروش هنوز فعال نیست',
          'pay': 'پرداخت',
          'decreaseQuantity': 'کاهش تعداد',
          'increaseQuantity': 'افزایش تعداد',
          'eachSuffix': 'هر کدام',
          'scanLabel': 'اسکن برچسب دوا',
          'scanDraftNote': 'پیش‌نویس آفلاین — بررسی توسط داروساز لازم است',
          'registerReviewed': 'ثبت دوای بررسی‌شده',
          'registerNeedsApproval': 'ثبت آنلاین به تأیید داروساز نیاز دارد.',
          'checkingDuplicates': 'در حال بررسی فهرست محلی برای موارد تکراری...',
          'duplicateUnavailable': 'بررسی موارد تکراری در دسترس نیست',
          'doNotRegister': 'تا بررسی موارد تکراری فهرست، ثبت نکنید.',
          'noLocalMatches': 'هیچ موردی در فهرست همگام‌شدهٔ محلی یافت نشد',
          'serverVerifyNeeded': 'تأیید از سمت سرور هنوز لازم است.',
          'approveHandoff': 'تأیید انتقال ثبت',
          'approveHandoffBody':
              'جزئیات بررسی‌شده را کنترل کرده‌ام. این تأیید ذخیره در سرور را مجاز نمی‌کند.',
          'prepareHandoff': 'آماده‌سازی انتقال ثبت',
          'prepareHandoffBody':
              'فقط فیلدهای تأییدشدهٔ پیش‌نویس کپی می‌شود. ارسال به سرور انجام نمی‌شود.',
          'copyHandoffTooltip': 'کپی انتقال ثبت',
          'registrationDisabled': 'ثبت دوا فعال نیست',
          'registrationDisabledBody':
              'داروساز باید فهرست فعلی سرور را تأیید کند و موارد تکراری را پیش از ذخیره برطرف کند. این اسکن پیش‌نویس می‌ماند و موجودی را تغییر نمی‌دهد.',
          'recheckTooltip': 'بررسی مجدد فهرست محلی',
          'reviewedDraftOnly': 'فقط پیش‌نویس بررسی‌شده. هیچ دوا یا موجودی ذخیره نشد.',
          'copyReviewedTooltip': 'کپی جزئیات دوای بررسی‌شده',
          'clearDraftTooltip': 'پاک کردن پیش‌نویس بررسی‌شده',
          'registeredSynced': 'دوا ثبت شد و فهرست همگام‌سازی شد.',
          'handoffCopied':
              'انتقال کپی شد. اعتبارسنجی سرور و ثبت توسط داروساز هنوز لازم است.',
          'reviewedCopied':
              'جزئیات بررسی‌شده کپی شد. دوا را به‌صورت دستی ثبت کنید؛ چیزی ذخیره نشد.',
          'possibleDuplicatesCount': 'موارد احتمالی تکراری: {count}',
          'catalogSyncFailed':
              'دوا به‌صورت آنلاین ذخیره شد، اما همگام‌سازی فهرست ناموفق بود: {error}. برای تلاش دوباره همگام‌سازی اکنون را بزنید.',
          'handoffBlocked': 'انتقال مسدود شد: {error}',
          'serverDuplicates': 'موارد احتمالی تکراری در سرور: {list}',
        },
        AppLocale.pashto: <String, String>{
          'dashboard': 'ډشبورډ',
          'salesToday': 'نن پلور',
          'invoices': 'رسیدونه',
          'lowStock': 'کمه ذخیره',
          'expiringSoon': 'ژر ختمېږي',
          'expiredBatches': 'تېرې شوې بستې',
          'pendingSync': 'همغږۍ ته په تمه',
          'quickAccess': 'چټک لاسرسی',
          'refreshLocalOverview': 'ځايي لنډیز تازه کړئ',
          'dashboardUnavailable': 'ځايي ډشبورډ شتون نه لري',
          'dashboardLoadError': 'ډشبورډ پورته کېدای نشو',
          'unableToLoadLocalData': 'ځايي معلومات پورته کېدای نشول.',
          'moduleSales': 'پلور',
          'moduleMedicines': 'درمل',
          'moduleInventory': 'ذخیره',
          'moduleReports': 'راپورونه',
          'modulePurchases': 'پېرودنې',
          'moduleCustomers': 'پېرودونکي',
          'moduleSuppliers': 'تهیه‌کوونکي',
          'moduleFinance': 'مالي',
          'moduleTransfers': 'لېږدونه',
          'moduleAlerts': 'خبرتیاوې',
          'moduleSync': 'همغږي',
          'moduleSettings': 'تنظیمات',
          'soon': 'ژر راځي',
          'stock': 'ذخیره',
          'stockReadOnly': 'یوازې کتل. د ځايي همغږي شوو معلوماتو پر بنسټ.',
          'metricMedicines': 'درمل',
          'metricOutOfStock': 'زېرمه نشته',
          'metricExpiring30': 'په ۳۰ ورځو کې ختمېږي',
          'searchMedicine': 'د درملو یا کوډ لټون',
          'refresh': 'تازه کول',
          'location': 'ځای',
          'allLocations': 'ټول ځایونه',
          'tabStock': 'ذخیره',
          'tabBatches': 'بستې',
          'tabExpiry': 'ختمېدل',
          'tabStorage': 'ذخیره ځای',
          'registerToViewStock': 'د ذخیرې د لیدلو لپاره دا وسیله ثبت کړئ.',
          'stockNotPermitted': 'د دې کارن لپاره د ذخیرې لاسرسی اجازه نه لري.',
          'noMedicinesMatch': 'د دې لټون سره سم درمل ونه موندل شول.',
          'previousPage': 'تېر مخ',
          'nextPage': 'بل مخ',
          'pageNumber': 'مخ {n}',
          'noExpiryMatches': 'تېرې شوې یا ژر ختمېدونکې بستې نشته.',
          'noBatchMatches': 'د دې لټون سره سمې بستې ونه موندل شوې.',
          'batch': 'بسته',
          'expires': 'ختمېږي',
          'expired': 'تېر شوی',
          'storageTitle': 'د درملو ذخیره ځای',
          'storageBody':
              'د ذخیرې ځايونه (څانګه، کوټه، ریک، رف او بکس) تر اوسه پدې وسیله کې شتون نه لري. تر هغې چې دا ځانګړتیا فعاله شي، بستې په خپل ثبت شوي ځای کې پاتې کېږي.',
          'addStorageLocation': 'د ذخیرې ځای زیات کړئ',
          'storageComingSoon':
              'ژر راځي: ځایونه جوړول، بستې ټاکل، د ذخیرې شمېرل او لېږدول.',
          'reports': 'راپورونه',
          'reportsSubtitle':
              'پدې وسیله کې ټولې. د ټولې شرکت راپورونه د سرور معلومات غواړي.',
          'registerToViewReports': 'د راپورونو د لیدلو لپاره دا وسیله ثبت کړئ.',
          'back': 'شاته',
          'invoicesCount': 'رسیدونه',
          'revenueAfn': 'عاید (افغانۍ)',
          'pendingSyncShort': 'همغږۍ ته په تمه',
          'dailyInvoices': 'ورځنی رسیدونه',
          'unableToReadSales': 'ځايي پلور لوستل کېږي نه.',
          'unableToReadInventory': 'ځايي ذخیره لوستل کېږي نه.',
          'unableToReadExpiry': 'ځايي ختمېدنې معلومات لوستل کېږي نه.',
          'unableToReadCustomers': 'ځايي پېرودونکي لوستل کېږي نه.',
          'noExpiryOnDevice': 'پدې وسیله کې تېرې شوې یا ژر ختمېدونکې بستې نشته.',
          'noCustomersOnDevice': 'پدې وسیله کې پېرودونکي همغږي شوي نه دي.',
          'acquisitionValue': 'د پاتې ذخیرې د پېرود ارزښت',
          'acquisitionSubtitle':
              'فعالې، نه تېرې شوې بستې · د ثبت شوو واحد لګښتونو پر بنسټ',
          'reportNotAvailable': 'دا راپور تر اوسه شتون نه لري.',
          'salesReport': 'د پلور راپور',
          'salesReportDetail': 'رسیدونه، عاید او ورځنی بڼه پدې وسیله کې',
          'inventoryReport': 'د ذخیرې راپور',
          'inventoryReportDetail': 'د ذخیرې کچې او د پېرود ارزښت',
          'expiryReport': 'د ختمېدنې راپور',
          'expiryReportDetail': 'تېرې شوې یا په ۳۰ ورځو کې ختمېدونکې بستې',
          'customerReport': 'د پېرودونکو راپور',
          'customerReportDetail': 'د پېرودونکو اړیکې او پاتې حسابونه',
          'profitLoss': 'ګټه او زیان',
          'profitLossDetail': 'د سرور څخه د لګښت او مصارفو معلومات غواړي',
          'purchaseReport': 'د پېرود راپور',
          'purchaseReportDetail': 'د سرور څخه د تهیه‌کوونکي د پېرود تاریخچه غواړي',
          'financialReport': 'مالي راپور',
          'financialReportDetail': 'د سرور څخه د ترلاسه کېدونکو او ورکړې وړو حسابونو معلومات غواړي',
          'today': 'نن',
          'days7': '۷ ورځې',
          'days30': '۳۰ ورځې',
          'subtotal': 'فرعي ټول',
          'discount': 'تخفیف',
          'hold': 'ځنډول',
          'holdNotAvailable': 'ځنډول تر اوسه شتون نه لري',
          'pay': 'تادیه',
          'decreaseQuantity': 'مقدار کم کړئ',
          'increaseQuantity': 'مقدار زیات کړئ',
          'eachSuffix': 'هر یو',
          'scanLabel': 'د درملو لیبل سکن کړئ',
          'scanDraftNote': 'آفلاین مسوده — د درماسټ بیاکتنه اړینه ده',
          'registerReviewed': 'بیاکتل شوې درمل ثبت کړئ',
          'registerNeedsApproval': 'آنلاین ثبت د درماسټ تایید ته اړتیا لري.',
          'checkingDuplicates': 'د ځايي لیست کې د تکراري توکو کتنه کېږي...',
          'duplicateUnavailable': 'د تکراري توکو کتنه شتون نه لري',
          'doNotRegister': 'تر هغې چې د لیست تکراري توکي وکتل شي، ثبت مه کوئ.',
          'noLocalMatches': 'په همغږي شوي ځايي لیست کې هیڅ سمون نه دی موندل شوی',
          'serverVerifyNeeded': 'د سرور اړخ تایید لا اړین دی.',
          'approveHandoff': 'د ثبت لیږد تایید کړئ',
          'approveHandoffBody':
              'ما بیاکتل شوي توضیحات وکتل. دا تایید د سرور ساتنې اجازه نه ورکوي.',
          'prepareHandoff': 'د ثبت لیږد چمتو کړئ',
          'prepareHandoffBody':
              'یوازې تایید شوي مسوده ساحې کاپي کېږي. سرور ته نه لېږل کېږي.',
          'copyHandoffTooltip': 'د ثبت لیږد کاپي کړئ',
          'registrationDisabled': 'د درملو ثبت فعال نه دی',
          'registrationDisabledBody':
              'درماسټ باید اوسنی د سرور لیست تایید کړي او ساتلو دمخه تکراري توکي حل کړي. دا سکن مسوده پاتې کېږي او زېرمه نه بدلوي.',
          'recheckTooltip': 'ځايي لیست بیا وکتل شي',
          'reviewedDraftOnly': 'یوازې بیاکتل شوې مسوده. هیڅ درمل یا زېرمه نه ده ساتل شوې.',
          'copyReviewedTooltip': 'بیاکتل شوي درمل توضیحات کاپي کړئ',
          'clearDraftTooltip': 'بیاکتل شوې مسوده پاکه کړئ',
          'registeredSynced': 'درمل ثبت شو او لیست همغږي شو.',
          'handoffCopied':
              'لیږد کاپي شو. د سرور تایید او د درماسټ ثبت لا اړین دي.',
          'reviewedCopied':
              'بیاکتل شوي توضیحات کاپي شول. درمل په لاس ثبت کړئ؛ هیڅ شی ونه ساتل شو.',
          'possibleDuplicatesCount': 'احتمالي تکراري توکي: {count}',
          'catalogSyncFailed':
              'درمل آنلاین خوندي شو، خو د لیست همغږي ناکامه شوه: {error}. بیا هڅې لپاره اوس همغږي کړئ وکاروئ.',
          'handoffBlocked': 'لیږد بند شو: {error}',
          'serverDuplicates': 'د سرور احتمالي تکراري توکي: {list}',
        },
      };
}
