import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_authorizer.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_verifier.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/core/localization/module_strings.dart';
import 'package:businessos_pharmacy/core/localization/scanner_strings.dart';
import 'package:businessos_pharmacy/features/pos/data/offline_pos_catalog_repository.dart';
import 'package:businessos_pharmacy/features/pos/data/offline_pos_checkout_service.dart';
import 'package:businessos_pharmacy/features/pos/domain/offline_pos_models.dart';
import 'package:businessos_pharmacy/features/pos/domain/pos_search_gate.dart';
import 'package:businessos_pharmacy/features/receipt/data/offline_receipt_repository.dart';
import 'package:businessos_pharmacy/features/receipt/domain/offline_receipt.dart';
import 'package:businessos_pharmacy/features/receipt/presentation/receipt_print_sheet.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

class OfflinePosPanel extends ConsumerWidget {
  const OfflinePosPanel({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppStrings strings = AppStrings.of(context);
    final AsyncValue<MobileRegistration?> registration = ref.watch(
      mobileRegistrationProvider,
    );
    final AsyncValue<PharmacyDatabase> database = ref.watch(
      pharmacyDatabaseProvider,
    );

    return registration.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object error, StackTrace stackTrace) => Text(error.toString()),
      data: (MobileRegistration? mobileRegistration) {
        if (mobileRegistration == null) {
          return Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Text(strings.registrationRequired),
            ),
          );
        }

        if (!mobileRegistration.permissions.contains('pos.sell')) {
          return Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Text(strings.posPermissionDenied),
            ),
          );
        }

        return database.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (Object error, StackTrace stackTrace) =>
              Text(error.toString()),
          data: (PharmacyDatabase pharmacyDatabase) => _OfflinePosBody(
            database: pharmacyDatabase,
            registration: mobileRegistration,
            registrationRepository: ref.read(
              mobileRegistrationRepositoryProvider,
            ),
          ),
        );
      },
    );
  }
}

class _OfflinePosBody extends StatefulWidget {
  const _OfflinePosBody({
    required this.database,
    required this.registration,
    required this.registrationRepository,
  });

  final PharmacyDatabase database;
  final MobileRegistration registration;
  final MobileRegistrationRepository registrationRepository;

  @override
  State<_OfflinePosBody> createState() => _OfflinePosBodyState();
}

class _OfflinePosBodyState extends State<_OfflinePosBody> {
  late final OfflinePosCatalogRepository _catalog;
  late final OfflinePosCheckoutService _checkout;
  late final OfflineReceiptRepository _receipts;
  final TextEditingController _searchController = TextEditingController();
  final PosSearchGate _searchGate = PosSearchGate();

  List<String> _locations = <String>[];
  List<OfflinePosCatalogItem> _results = <OfflinePosCatalogItem>[];
  final List<_CartEntry> _cart = <_CartEntry>[];
  List<OfflineReceipt> _recentReceipts = <OfflineReceipt>[];
  String? _locationId;
  String _paymentMethod = 'cash';
  bool _loading = true;
  bool _checkingOut = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _catalog = OfflinePosCatalogRepository(widget.database);
    _receipts = OfflineReceiptRepository(widget.database);
    final OfflineLeaseAuthorizer leaseAuthorizer = OfflineLeaseAuthorizer(
      database: widget.database,
      registrationRepository: widget.registrationRepository,
    );
    _checkout = OfflinePosCheckoutService(
      widget.database,
      authorizeTransaction: () async {
        final AppStrings strings = AppStrings.of(context);
        try {
          await leaseAuthorizer.assertCanTransact();
        } on OfflineLeaseException catch (error) {
          throw OfflinePosException(_leaseMessage(error, strings));
        }
      },
    );
    _load();
  }

  String _leaseMessage(OfflineLeaseException error, AppStrings strings) {
    return switch (error.code) {
      OfflineLeaseFailure.missing => strings.offlineLeaseMissing,
      OfflineLeaseFailure.invalid => strings.offlineLeaseInvalid,
      OfflineLeaseFailure.expired => strings.offlineLeaseExpired,
      OfflineLeaseFailure.clockRollback => strings.offlineLeaseClockError,
    };
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final List<String> locations = await _catalog.stockLocationIds();
      final List<OfflineReceipt> recent = await _receipts.recentCompleted();
      final String? selected = locations.isEmpty ? null : locations.first;

      if (!mounted) {
        return;
      }

      setState(() {
        _locations = locations;
        _recentReceipts = recent;
        _locationId = selected;
        _loading = false;
      });

      if (selected != null) {
        await _search();
      }
    } on Object catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error.toString();
      });
    }
  }

  Future<void> _search() async {
    final int generation = _searchGate.begin();
    final String? locationId = _locationId;
    if (locationId == null) {
      if (mounted) setState(() => _results = <OfflinePosCatalogItem>[]);
      return;
    }

    try {
      final List<OfflinePosCatalogItem> results = await _catalog.search(
        stockLocationId: locationId,
        query: _searchController.text,
      );

      if (!mounted || !_searchGate.isCurrent(generation)) {
        return;
      }
      setState(() {
        _results = results;
        _error = null;
      });
    } on Object catch (error) {
      if (!mounted || !_searchGate.isCurrent(generation)) {
        return;
      }
      setState(() {
        _error = error.toString();
      });
    }
  }

  Future<void> _scanBarcode() async {
    final String? code = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(
        builder: (BuildContext context) => const _PosBarcodeScanner(),
      ),
    );
    if (!mounted || code == null || code.isEmpty) return;
    _searchController.text = code;
    await _search();
  }

  void _add(OfflinePosCatalogItem item) {
    final int index = _cart.indexWhere(
      (_CartEntry entry) => entry.item.medicineId == item.medicineId,
    );

    setState(() {
      if (index >= 0) {
        final _CartEntry existing = _cart[index];
        final FixedDecimal next =
            FixedDecimal.parse(existing.quantity) + FixedDecimal.parse('1');
        existing.quantity = next.toString();
      } else {
        _cart.add(
          _CartEntry(
            item: item,
            quantity: '1.0000',
            unitPrice: item.salePrice.toString(),
            discount: '0.0000',
          ),
        );
      }
    });
  }

  void _changeQuantity(_CartEntry entry, bool increase) {
    final FixedDecimal one = FixedDecimal.parse('1');
    final FixedDecimal current = FixedDecimal.parse(entry.quantity);
    final FixedDecimal next = increase ? current + one : current - one;
    setState(() {
      if (!next.isPositive) {
        _cart.remove(entry);
      } else {
        entry.quantity = next.toString();
      }
    });
  }

  Future<void> _edit(_CartEntry entry) async {
    final AppStrings strings = AppStrings.of(context);
    final TextEditingController quantity = TextEditingController(
      text: entry.quantity,
    );
    final TextEditingController price = TextEditingController(
      text: entry.unitPrice,
    );
    final TextEditingController discount = TextEditingController(
      text: entry.discount,
    );

    final bool? save = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: Text(entry.item.brandName),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              TextField(
                controller: quantity,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(labelText: strings.posQuantity),
              ),
              TextField(
                controller: price,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(labelText: strings.posUnitPrice),
              ),
              TextField(
                controller: discount,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(labelText: strings.posDiscount),
              ),
            ],
          ),
          actions: <Widget>[
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(MaterialLocalizations.of(context).cancelButtonLabel),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(strings.posEdit),
            ),
          ],
        );
      },
    );

    if (save == true) {
      try {
        final FixedDecimal parsedQuantity = FixedDecimal.parse(quantity.text);
        final FixedDecimal parsedPrice = FixedDecimal.parse(price.text);
        final FixedDecimal parsedDiscount = FixedDecimal.parse(discount.text);

        if (!parsedQuantity.isPositive ||
            parsedPrice.isNegative ||
            parsedDiscount.isNegative) {
          throw const FormatException('Invalid value.');
        }

        setState(() {
          entry.quantity = parsedQuantity.toString();
          entry.unitPrice = parsedPrice.toString();
          entry.discount = parsedDiscount.toString();
          _error = null;
        });
      } on FormatException {
        setState(() {
          _error = strings.posInvalidValue;
        });
      }
    }

    quantity.dispose();
    price.dispose();
    discount.dispose();
  }

  FixedDecimal get _subtotal {
    FixedDecimal value = FixedDecimal.zero;

    for (final _CartEntry entry in _cart) {
      try {
        value += FixedDecimal.parse(entry.quantity)
            .multiply(FixedDecimal.parse(entry.unitPrice));
      } on FormatException {
        return FixedDecimal.zero;
      }
    }

    return value;
  }

  FixedDecimal get _discountTotal {
    FixedDecimal value = FixedDecimal.zero;

    for (final _CartEntry entry in _cart) {
      try {
        value += FixedDecimal.parse(entry.discount);
      } on FormatException {
        return FixedDecimal.zero;
      }
    }

    return value;
  }

  FixedDecimal get _total {
    FixedDecimal value = FixedDecimal.zero;

    for (final _CartEntry entry in _cart) {
      try {
        final FixedDecimal quantity = FixedDecimal.parse(entry.quantity);
        final FixedDecimal unitPrice = FixedDecimal.parse(entry.unitPrice);
        final FixedDecimal discount = FixedDecimal.parse(entry.discount);
        value += quantity.multiply(unitPrice) - discount;
      } on FormatException {
        return FixedDecimal.zero;
      }
    }

    return value;
  }

  Future<void> _complete() async {
    final AppStrings strings = AppStrings.of(context);
    final String? locationId = _locationId;
    if (_checkingOut || locationId == null || _cart.isEmpty) {
      return;
    }

    setState(() {
      _checkingOut = true;
      _error = null;
    });

    try {
      final FixedDecimal total = _total;
      final OfflinePosCheckoutResult result = await _checkout.checkout(
        OfflinePosCheckoutRequest(
          stockLocationId: locationId,
          cashierUserId: widget.registration.userId,
          permissions: widget.registration.permissions.toSet(),
          lines: _cart
              .map(
                (_CartEntry entry) => OfflinePosLineInput(
                  medicineId: entry.item.medicineId,
                  quantity: entry.quantity,
                  unitPrice: entry.unitPrice,
                  discountAmount: entry.discount,
                ),
              )
              .toList(growable: false),
          payments: <OfflinePosPaymentInput>[
            OfflinePosPaymentInput(
              method: _paymentMethod,
              amount: total.toString(),
            ),
          ],
        ),
      );
      final OfflineReceipt? receipt = await _receipts.find(result.saleLocalId);
      final List<OfflineReceipt> recent = await _receipts.recentCompleted();

      if (!mounted) {
        return;
      }

      setState(() {
        _checkingOut = false;
        _cart.clear();
        _recentReceipts = recent;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${strings.posSaleSaved} ${result.saleLocalId.substring(0, 8)} • '
            '${strings.posSyncPending}',
          ),
        ),
      );

      if (receipt != null && mounted) {
        await _showReceipt(receipt);
      }
    } on OfflinePosException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _checkingOut = false;
        _error = error.message;
      });
    } on FormatException {
      if (!mounted) {
        return;
      }
      setState(() {
        _checkingOut = false;
        _error = strings.posInvalidValue;
      });
    } on Object catch (error) {
      // Checkout is atomic. Keep the cart if persistence fails.
      if (!mounted) return;
      setState(() {
        _checkingOut = false;
        _error = 'Unable to complete the offline sale: $error';
      });
    }
  }

  Future<void> _showReceipt(OfflineReceipt receipt) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (BuildContext context) => ReceiptPrintSheet(
        receipt: receipt,
        registration: widget.registration,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    final ModuleStrings s = ModuleStrings.of(context);
    final ScannerStrings sc = ScannerStrings.of(context);

    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_locations.isEmpty) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Text(strings.posNoLocalStock),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Icon(
              Icons.point_of_sale_rounded,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                strings.pos,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: <Widget>[
                TextField(
                  controller: _searchController,
                  decoration: InputDecoration(
                    labelText: strings.posSearch,
                    hintText: strings.posSearchHint,
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: IconButton(
                      tooltip: sc.scanBarcodeTooltip,
                      onPressed: _scanBarcode,
                      icon: const Icon(Icons.qr_code_scanner_rounded),
                    ),
                  ),
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _search(),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _locationId,
                  decoration: InputDecoration(labelText: strings.posLocation),
                  items: _locations
                      .map(
                        (String id) => DropdownMenuItem<String>(
                          value: id,
                          child: Text(id),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: _checkingOut
                      ? null
                      : (String? value) {
                          if (value == _locationId) return;
                          _searchGate.invalidate();
                          setState(() {
                            _locationId = value;
                            // Cart lines must never be checked out against another
                            // location's batch inventory.
                            _cart.clear();
                            _results = <OfflinePosCatalogItem>[];
                            _error = null;
                          });
                          _search();
                        },
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        ..._results.map(
          (OfflinePosCatalogItem item) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text(
                    item.brandName,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${item.genericName ?? ''} • '
                    '${strings.posAvailable}: ${item.availableQuantity.compact} '
                    '${item.saleUnit}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: FilledButton.icon(
                      onPressed: _checkingOut ? null : () => _add(item),
                      icon: const Icon(Icons.add_shopping_cart_outlined),
                      label: Text(
                        '${strings.posAdd} • ${item.salePrice.compact} AFN',
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        if (_recentReceipts.isNotEmpty) ...<Widget>[
          const SizedBox(height: 16),
          Text(
            strings.receiptRecent,
            style: Theme.of(context).textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: () async {
                try {
                  final recent = await _receipts.recentCompleted();
                  if (mounted) setState(() => _recentReceipts = recent);
                } on Object catch (error) {
                  if (mounted) setState(() => _error = error.toString());
                }
              },
              icon: const Icon(Icons.refresh),
              label: Text(sc.refreshReceipts),
            ),
          ),
          ..._recentReceipts.map(
            (OfflineReceipt receipt) => Card(
              child: ListTile(
                leading: const Icon(Icons.receipt_long_rounded),
                title: Text(receipt.displayNumber),
                subtitle: Text(
                  '${receipt.total.compact} AFN • ${receipt.businessDate} • ${receipt.syncState}',
                ),
                trailing: IconButton(
                  tooltip: strings.receiptPrint,
                  onPressed: () => _showReceipt(receipt),
                  icon: const Icon(Icons.print_rounded),
                ),
              ),
            ),
          ),
        ],
        const SizedBox(height: 16),
        Text(
          strings.posCart,
          style: Theme.of(context).textTheme.titleMedium
              ?.copyWith(fontWeight: FontWeight.w800),
        ),
        if (_cart.isEmpty)
          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: 20,
                vertical: 24,
              ),
              child: Column(
                children: <Widget>[
                  Icon(
                    Icons.shopping_cart_outlined,
                    size: 32,
                    color: Theme.of(context).colorScheme.outline,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    strings.posEmptyCart,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              ),
            ),
          ),
        ..._cart.map(
          (_CartEntry entry) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: InkWell(
                          onTap: _checkingOut ? null : () => _edit(entry),
                          child: Text(
                            entry.item.brandName,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                      IconButton(
                        tooltip: strings.posRemove,
                        onPressed: _checkingOut
                            ? null
                            : () {
                                setState(() {
                                  _cart.remove(entry);
                                });
                              },
                        icon: const Icon(Icons.delete_outline_rounded),
                      ),
                    ],
                  ),
                  Row(
                    children: <Widget>[
                      IconButton.outlined(
                        tooltip: s.decreaseQuantity,
                        onPressed: _checkingOut
                            ? null
                            : () => _changeQuantity(entry, false),
                        icon: const Icon(Icons.remove),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        child: Text(
                          FixedDecimal.parse(entry.quantity).compact,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                      IconButton.outlined(
                        tooltip: s.increaseQuantity,
                        onPressed: _checkingOut
                            ? null
                            : () => _changeQuantity(entry, true),
                        icon: const Icon(Icons.add),
                      ),
                      const Spacer(),
                      Text(
                        '${FixedDecimal.parse(entry.unitPrice).compact} AFN ${s.eachSuffix}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
        if (_error != null) ...<Widget>[
          const SizedBox(height: 8),
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ],
        if (_cart.isNotEmpty) ...<Widget>[
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: <Widget>[
                  _TotalRow(
                    label: s.subtotal,
                    value: '${_subtotal.compact} AFN',
                  ),
                  _TotalRow(
                    label: s.discount,
                    value: '− ${_discountTotal.compact} AFN',
                    valueColor: const Color(0xFFD93A3A),
                  ),
                  const Divider(),
                  _TotalRow(
                    label: strings.posTotal,
                    value: '${_total.compact} AFN',
                    bold: true,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _paymentMethod,
            decoration: InputDecoration(labelText: strings.posPaymentMethod),
            items: <DropdownMenuItem<String>>[
              DropdownMenuItem<String>(
                value: 'cash',
                child: Text(strings.posCash),
              ),
              DropdownMenuItem<String>(
                value: 'bank',
                child: Text(strings.posBank),
              ),
              DropdownMenuItem<String>(
                value: 'mobile',
                child: Text(strings.posMobile),
              ),
            ],
            onChanged: (String? value) {
              if (value != null) {
                setState(() {
                  _paymentMethod = value;
                });
              }
            },
          ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              Expanded(
                child: Tooltip(
                  message: s.holdNotAvailable,
                  child: OutlinedButton.icon(
                    onPressed: null,
                    icon: const Icon(Icons.pause_circle_outline_rounded),
                    label: Text(s.hold),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: FilledButton.icon(
                  onPressed: _checkingOut ? null : _complete,
                  icon: const Icon(Icons.payments_outlined),
                  label: Text(
                    _checkingOut ? strings.saving : strings.posCompleteSale,
                  ),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({
    required this.label,
    required this.value,
    this.valueColor,
    this.bold = false,
  });

  final String label;
  final String value;
  final Color? valueColor;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final TextStyle style = TextStyle(
      fontWeight: bold ? FontWeight.w900 : FontWeight.w500,
      fontSize: bold ? 18 : 14,
      color: valueColor,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: <Widget>[
          Expanded(child: Text(label, style: style)),
          Text(value, style: style),
        ],
      ),
    );
  }
}

class _CartEntry {
  _CartEntry({
    required this.item,
    required this.quantity,
    required this.unitPrice,
    required this.discount,
  });

  final OfflinePosCatalogItem item;
  String quantity;
  String unitPrice;
  String discount;
}

class _PosBarcodeScanner extends StatefulWidget {
  const _PosBarcodeScanner();

  @override
  State<_PosBarcodeScanner> createState() => _PosBarcodeScannerState();
}

class _PosBarcodeScannerState extends State<_PosBarcodeScanner> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _handled = false;

  Future<void> _enterCodeManually() async {
    final ScannerStrings sc = ScannerStrings.of(context);
    final TextEditingController input = TextEditingController();
    final String? code = await showDialog<String>(
      context: context,
      builder: (BuildContext dialogContext) => AlertDialog(
        title: Text(sc.enterCodeTitle),
        content: TextField(
          controller: input,
          autofocus: true,
          textInputAction: TextInputAction.done,
          decoration: InputDecoration(labelText: sc.barcodeOrQrValue),
          onSubmitted: (String value) =>
              Navigator.of(dialogContext).pop(value.trim()),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: Text(sc.cancel),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(input.text.trim()),
            child: Text(sc.search),
          ),
        ],
      ),
    );
    input.dispose();
    if (!mounted || _handled || code == null || code.isEmpty) return;
    _handled = true;
    Navigator.of(context).pop(code);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (!mounted || _handled || capture.barcodes.isEmpty) return;
    String? value;
    for (final Barcode barcode in capture.barcodes) {
      final String? candidate = barcode.rawValue;
      if (candidate != null && candidate.trim().isNotEmpty) {
        value = candidate.trim();
        break;
      }
    }
    if (value == null) return;
    _handled = true;
    Navigator.of(context).pop(value);
  }

  @override
  Widget build(BuildContext context) {
    final ScannerStrings sc = ScannerStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(sc.scanTitle),
        actions: <Widget>[
          IconButton(
            tooltip: sc.toggleFlash,
            onPressed: () => _controller.toggleTorch(),
            icon: const Icon(Icons.flashlight_on_outlined),
          ),
          IconButton(
            tooltip: sc.enterCodeManually,
            onPressed: _enterCodeManually,
            icon: const Icon(Icons.keyboard_outlined),
          ),
        ],
      ),
      body: Stack(
        children: <Widget>[
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder:
                (BuildContext context, MobileScannerException error) => Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(sc.cameraUnavailable(error.errorCode.name)),
                  ),
                ),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Text(sc.alignBarcode),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
