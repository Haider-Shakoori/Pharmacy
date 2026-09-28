import 'dart:async';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/receipt/domain/offline_receipt.dart';
import 'package:businessos_pharmacy/features/receipt/presentation/offline_receipt_paper.dart';
import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_thermal_printer/flutter_thermal_printer.dart';
import 'package:flutter_thermal_printer/utils/printer.dart';

class ReceiptPrintSheet extends StatefulWidget {
  const ReceiptPrintSheet({
    required this.receipt,
    required this.registration,
    super.key,
  });

  final OfflineReceipt receipt;
  final MobileRegistration registration;

  @override
  State<ReceiptPrintSheet> createState() => _ReceiptPrintSheetState();
}

class _ReceiptPrintSheetState extends State<ReceiptPrintSheet> {
  final FlutterThermalPrinter _printer = FlutterThermalPrinter.instance;
  StreamSubscription<List<Printer>>? _subscription;
  List<Printer> _printers = <Printer>[];
  ReceiptPaperWidth _paperWidth = ReceiptPaperWidth.mm80;
  String? _workingPrinterId;
  String? _error;
  bool _scanning = false;

  @override
  void initState() {
    super.initState();
    _subscription = _printer.devicesStream.listen((List<Printer> printers) {
      if (!mounted) {
        return;
      }

      setState(() {
        _printers = printers
            .where(
              (Printer printer) =>
                  (printer.name?.trim().isNotEmpty ?? false) ||
                  (printer.address?.trim().isNotEmpty ?? false),
            )
            .toList(growable: false);
      });
    });
    WidgetsBinding.instance.addPostFrameCallback((_) => _scan());
  }

  @override
  void dispose() {
    _subscription?.cancel();
    _printer.stopScan();
    super.dispose();
  }

  Future<void> _scan() async {
    if (_scanning) {
      return;
    }

    setState(() {
      _scanning = true;
      _error = null;
    });

    try {
      await _printer.getPrinters(
        connectionTypes: <ConnectionType>[
          ConnectionType.USB,
          ConnectionType.BLE,
          ConnectionType.NETWORK,
        ],
        androidUsesFineLocation: false,
      );
    } on Object catch (error) {
      if (mounted) {
        setState(() {
          _error = error.toString();
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _scanning = false;
        });
      }
    }
  }

  Future<void> _print(Printer printer) async {
    final AppStrings strings = AppStrings.of(context);
    setState(() {
      _workingPrinterId = printer.deviceId;
      _error = null;
    });

    try {
      final bool connected = await _printer.connect(
        printer,
        connectionStabilizationDelay: const Duration(seconds: 2),
      );

      if (!connected) {
        throw Exception(strings.receiptPrinterConnectFailed);
      }

      if (!mounted) {
        return;
      }

      await _printer.printWidget(
        context,
        printer: printer,
        widget: OfflineReceiptPaper(
          receipt: widget.receipt,
          pharmacyName: widget.registration.tenantName,
          cashierName: widget.registration.userName,
          paperWidth: _paperWidth,
        ),
        paperSize: _paperWidth == ReceiptPaperWidth.mm58
            ? PaperSize.mm58
            : PaperSize.mm80,
        printOnBle: true,
        cutAfterPrinted: true,
      );

      await _printer.disconnect(printer);

      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(strings.receiptPrinted)));
      }
    } on Object catch (error) {
      if (mounted) {
        setState(() {
          _error = error.toString();
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _workingPrinterId = null;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          16,
          16,
          16 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                strings.receiptPrintTitle,
                style: Theme.of(context).textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              Text('${strings.receiptNumber}: ${widget.receipt.displayNumber}'),
              const SizedBox(height: 16),
              SegmentedButton<ReceiptPaperWidth>(
                segments: <ButtonSegment<ReceiptPaperWidth>>[
                  ButtonSegment<ReceiptPaperWidth>(
                    value: ReceiptPaperWidth.mm58,
                    label: Text(strings.receiptPaper58),
                  ),
                  ButtonSegment<ReceiptPaperWidth>(
                    value: ReceiptPaperWidth.mm80,
                    label: Text(strings.receiptPaper80),
                  ),
                ],
                selected: <ReceiptPaperWidth>{_paperWidth},
                onSelectionChanged: (Set<ReceiptPaperWidth> value) {
                  setState(() {
                    _paperWidth = value.first;
                  });
                },
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _scanning ? null : _scan,
                icon: const Icon(Icons.refresh_rounded),
                label: Text(
                  _scanning
                      ? strings.receiptScanning
                      : strings.receiptScanPrinters,
                ),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 8),
              if (_printers.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 20),
                  child: Text(strings.receiptNoPrinters),
                )
              else
                ..._printers.map(
                  (Printer printer) => Card(
                    child: ListTile(
                      leading: const Icon(Icons.print_rounded),
                      title: Text(
                        printer.name?.trim().isNotEmpty == true
                            ? printer.name!
                            : strings.receiptUnnamedPrinter,
                      ),
                      subtitle: Text(
                        <String>[
                          printer.connectionTypeString,
                          if (printer.address?.trim().isNotEmpty == true)
                            printer.address!,
                        ].join(' • '),
                      ),
                      trailing: FilledButton(
                        onPressed: _workingPrinterId == null
                            ? () => _print(printer)
                            : null,
                        child: _workingPrinterId == printer.deviceId
                            ? const SizedBox.square(
                                dimension: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(strings.receiptPrint),
                      ),
                    ),
                  ),
                ),
              const SizedBox(height: 8),
              Text(
                strings.receiptPrintingHelp,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
