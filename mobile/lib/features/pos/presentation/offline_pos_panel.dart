import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_authorizer.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_verifier.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/pos/data/offline_pos_catalog_repository.dart';
import 'package:businessos_pharmacy/features/pos/data/offline_pos_checkout_service.dart';
import 'package:businessos_pharmacy/features/pos/domain/offline_pos_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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
  final TextEditingController _searchController = TextEditingController();

  List<String> _locations = <String>[];
  List<OfflinePosCatalogItem> _results = <OfflinePosCatalogItem>[];
  final List<_CartEntry> _cart = <_CartEntry>[];
  String? _locationId;
  String _paymentMethod = 'cash';
  bool _loading = true;
  bool _checkingOut = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _catalog = OfflinePosCatalogRepository(widget.database);
    final OfflineLeaseAuthorizer leaseAuthorizer = OfflineLeaseAuthorizer(
      database: widget.database,
      registrationRepository: widget.registrationRepository,
    );
    _checkout = OfflinePosCheckoutService(
      widget.database,
      authorizeTransaction: () async {
        try {
          await leaseAuthorizer.assertCanTransact();
        } on OfflineLeaseException catch (error) {
          throw OfflinePosException(
            _leaseMessage(error, AppStrings.of(context)),
          );
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
      final String? selected = locations.isEmpty ? null : locations.first;

      if (!mounted) {
        return;
      }

      setState(() {
        _locations = locations;
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
    final String? locationId = _locationId;
    if (locationId == null) {
      return;
    }

    try {
      final List<OfflinePosCatalogItem> results = await _catalog.search(
        stockLocationId: locationId,
        query: _searchController.text,
      );

      if (!mounted) {
        return;
      }
      setState(() {
        _results = results;
        _error = null;
      });
    } on Object catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = error.toString();
      });
    }
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
    if (locationId == null || _cart.isEmpty) {
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

      if (!mounted) {
        return;
      }

      setState(() {
        _checkingOut = false;
        _cart.clear();
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${strings.posSaleSaved} ${result.saleLocalId.substring(0, 8)} • '
            '${strings.posSyncPending}',
          ),
        ),
      );
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
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);

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
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: <Widget>[
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
                  onChanged: (String? value) {
                    setState(() {
                      _locationId = value;
                    });
                    _search();
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _searchController,
                  decoration: InputDecoration(
                    labelText: strings.posSearch,
                    hintText: strings.posSearchHint,
                    suffixIcon: IconButton(
                      onPressed: _search,
                      icon: const Icon(Icons.search_rounded),
                    ),
                  ),
                  onSubmitted: (_) => _search(),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        ..._results.map(
          (OfflinePosCatalogItem item) => Card(
            child: ListTile(
              title: Text(item.brandName),
              subtitle: Text(
                '${item.genericName ?? ''} • '
                '${strings.posAvailable}: ${item.availableQuantity.compact} '
                '${item.saleUnit}',
              ),
              trailing: FilledButton(
                onPressed: () => _add(item),
                child: Text(
                  '${strings.posAdd} • ${item.salePrice.compact} AFN',
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          strings.posCart,
          style: Theme.of(context).textTheme.titleMedium
              ?.copyWith(fontWeight: FontWeight.w800),
        ),
        if (_cart.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Text(strings.posEmptyCart),
          ),
        ..._cart.map(
          (_CartEntry entry) => Card(
            child: ListTile(
              title: Text(entry.item.brandName),
              subtitle: Text(
                '${strings.posQuantity}: '
                '${FixedDecimal.parse(entry.quantity).compact} • '
                '${strings.posUnitPrice}: '
                '${FixedDecimal.parse(entry.unitPrice).compact} AFN',
              ),
              onTap: () => _edit(entry),
              trailing: IconButton(
                tooltip: strings.posRemove,
                onPressed: () {
                  setState(() {
                    _cart.remove(entry);
                  });
                },
                icon: const Icon(Icons.delete_outline_rounded),
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
          Text(
            '${strings.posTotal}: ${_total.compact} AFN',
            textAlign: TextAlign.end,
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: _checkingOut ? null : _complete,
            icon: const Icon(Icons.check_circle_outline_rounded),
            label: Text(
              _checkingOut ? strings.saving : strings.posCompleteSale,
            ),
          ),
        ],
      ],
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
