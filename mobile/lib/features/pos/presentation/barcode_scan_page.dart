import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// Returns a scanned barcode to the POS search without changing stock.
class BarcodeScanPage extends StatefulWidget {
  const BarcodeScanPage({super.key});

  @override
  State<BarcodeScanPage> createState() => _BarcodeScanPageState();
}

class _BarcodeScanPageState extends State<BarcodeScanPage> {
  bool _handled = false;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Scan medicine barcode')),
    body: MobileScanner(
      onDetect: (BarcodeCapture capture) {
        if (_handled || capture.barcodes.isEmpty) return;
        final String? value = capture.barcodes
            .map((Barcode barcode) => barcode.rawValue)
            .whereType<String>()
            .where((String value) => value.trim().isNotEmpty)
            .firstOrNull;
        if (value == null) return;
        _handled = true;
        Navigator.of(context).pop(value.trim());
      },
    ),
  );
}
