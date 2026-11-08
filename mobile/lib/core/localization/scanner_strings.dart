import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:flutter/widgets.dart';

/// Labels for the POS barcode scanner and receipt list.
///
/// English is the fallback. Dari and Pashto are drafts for native review.
class ScannerStrings {
  const ScannerStrings._(this.locale);

  final AppLocale locale;

  static ScannerStrings of(BuildContext context) {
    return ScannerStrings._(
      AppLocale.fromCode(Localizations.localeOf(context).languageCode),
    );
  }

  String _t(String key) {
    return _table[locale]?[key] ?? _table[AppLocale.english]![key] ?? key;
  }

  String get scanBarcodeTooltip => _t('scanBarcodeTooltip');
  String get scanTitle => _t('scanTitle');
  String get toggleFlash => _t('toggleFlash');
  String get enterCodeManually => _t('enterCodeManually');
  String get enterCodeTitle => _t('enterCodeTitle');
  String get barcodeOrQrValue => _t('barcodeOrQrValue');
  String get cancel => _t('cancel');
  String get search => _t('search');
  String get alignBarcode => _t('alignBarcode');
  String get refreshReceipts => _t('refreshReceipts');

  String cameraUnavailable(String code) => _t('cameraUnavailable').replaceAll(
    '{code}',
    code,
  );

  static const Map<AppLocale, Map<String, String>> _table =
      <AppLocale, Map<String, String>>{
        AppLocale.english: <String, String>{
          'scanBarcodeTooltip': 'Scan barcode or QR code',
          'scanTitle': 'Scan medicine code',
          'toggleFlash': 'Toggle flashlight',
          'enterCodeManually': 'Enter code manually',
          'enterCodeTitle': 'Enter medicine code',
          'barcodeOrQrValue': 'Barcode or QR value',
          'cancel': 'Cancel',
          'search': 'Search',
          'alignBarcode':
              'Align the medicine barcode or QR code within the camera view.',
          'refreshReceipts': 'Refresh receipts',
          'cameraUnavailable':
              'Camera unavailable: {code}. Check camera permissions in iOS Settings.',
        },
        AppLocale.dari: <String, String>{
          'scanBarcodeTooltip': 'اسکن بارکد یا QR',
          'scanTitle': 'اسکن کد دوا',
          'toggleFlash': 'روشن یا خاموش کردن چراغ',
          'enterCodeManually': 'واردکردن دستی کد',
          'enterCodeTitle': 'واردکردن کد دوا',
          'barcodeOrQrValue': 'مقدار بارکد یا QR',
          'cancel': 'لغو',
          'search': 'جستجو',
          'alignBarcode':
              'بارکد یا QR دوا را داخل نمای دوربین قرار دهید.',
          'refreshReceipts': 'تازه‌سازی رسیدها',
          'cameraUnavailable':
              'دوربین در دسترس نیست: {code}. مجوز دوربین را در تنظیمات iOS بررسی کنید.',
        },
        AppLocale.pashto: <String, String>{
          'scanBarcodeTooltip': 'بارکوډ یا QR سکن کړئ',
          'scanTitle': 'د درملو کوډ سکن کړئ',
          'toggleFlash': 'فلش لایټ بدل کړئ',
          'enterCodeManually': 'کوډ په لاس ولیکئ',
          'enterCodeTitle': 'د درملو کوډ ولیکئ',
          'barcodeOrQrValue': 'بارکوډ یا QR ارزښت',
          'cancel': 'لغوه کول',
          'search': 'لټون',
          'alignBarcode':
              'د درملو بارکوډ یا QR کوډ د کمرې په لید کې ځای پر ځای کړئ.',
          'refreshReceipts': 'رسیدونه تازه کړئ',
          'cameraUnavailable':
              'کمره شتون نه لري: {code}. د iOS تنظیماتو کې د کمرې اجازې وګورئ.',
        },
      };
}
