import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// Scans a medicine's stored code. The POS resolves the code against the
/// existing tenant-scoped medicine catalog; scanning never creates stock.
class MedicineCodeScanner extends StatefulWidget {
  const MedicineCodeScanner({super.key});

  @override
  State<MedicineCodeScanner> createState() => _MedicineCodeScannerState();
}

class _MedicineCodeScannerState extends State<MedicineCodeScanner> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) {
      return;
    }
    for (final Barcode barcode in capture.barcodes) {
      final String? code = barcode.rawValue?.trim();
      if (code != null && code.isNotEmpty) {
        _handled = true;
        Navigator.of(context).pop(code);
        return;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocale locale = AppLocale.fromCode(
      Localizations.localeOf(context).languageCode,
    );
    final String title = switch (locale) {
      AppLocale.dari => 'اسکن کود دوا',
      AppLocale.pashto => 'د درملو کوډ سکن',
      _ => 'Scan medicine code',
    };
    final String help = switch (locale) {
      AppLocale.dari =>
        'بارکود را در چارچوب قرار دهید. کود باید در فهرست ادویه ثبت باشد.',
      AppLocale.pashto =>
        'بارکوډ په چوکاټ کې ونیسئ. کوډ باید د درملو په لست کې ثبت وي.',
      _ => 'Point at a barcode saved as a medicine code in your pharmacy catalog.',
    };

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: <Widget>[
                  MobileScanner(
                    controller: _controller,
                    onDetect: _onDetect,
                    errorBuilder: (BuildContext context, MobileScannerException error) {
                      return Center(child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(error.errorDetails?.message ??
                            error.errorCode.toString()),
                      ));
                    },
                  ),
                  IgnorePointer(
                    child: Center(
                      child: Container(
                        width: 258,
                        height: 172,
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.white, width: 3),
                          borderRadius: BorderRadius.circular(18),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(20),
              child: Text(help, textAlign: TextAlign.center),
            ),
          ],
        ),
      ),
    );
  }
}
