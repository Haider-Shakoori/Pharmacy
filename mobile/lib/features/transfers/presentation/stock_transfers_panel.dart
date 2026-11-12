import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/catalog/data/mobile_stock_location_api.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:uuid/uuid.dart';

/// Online-only transfers. A request ID is retained after uncertain responses.
class StockTransfersPanel extends ConsumerStatefulWidget {
  const StockTransfersPanel({super.key});

  @override
  ConsumerState<StockTransfersPanel> createState() =>
      _StockTransfersPanelState();
}

class _StockTransfersPanelState extends ConsumerState<StockTransfersPanel> {
  final _quantity = TextEditingController();
  List<MobileStockLocation> _locations = [];
  List<MobileStockBatch> _batches = [];
  String? _sourceId;
  String? _destinationId;
  String? _batchId;
  String? _pendingRequestId;
  String? _pendingFingerprint;
  bool _busy = false;
  String? _message;

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final registration = ref.read(mobileRegistrationProvider).asData?.value;
    if (registration == null || _busy) return;
    setState(() {
      _busy = true;
      _message = null;
    });
    final client = http.Client();
    try {
      final locations = await MobileStockLocationApi(
        environment: AppEnvironment.current,
        client: client,
      ).list(registration);
      if (!mounted) return;
      setState(() {
        _locations = locations;
        _sourceId = null;
        _destinationId = null;
        _batchId = null;
        _batches = [];
      });
    } catch (error) {
      if (mounted) setState(() => _message = '$error');
    } finally {
      client.close();
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _loadBatches(String sourceId) async {
    final registration = ref.read(mobileRegistrationProvider).asData?.value;
    if (registration == null || _busy) return;
    setState(() {
      _busy = true;
      _sourceId = sourceId;
      _batchId = null;
      _batches = [];
      _message = null;
    });
    final client = http.Client();
    try {
      final batches = await MobileStockLocationApi(
        environment: AppEnvironment.current,
        client: client,
      ).allBatches(registration: registration, stockLocationId: sourceId);
      if (mounted) setState(() => _batches = batches);
    } catch (error) {
      if (mounted) setState(() => _message = '$error');
    } finally {
      client.close();
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _transfer() async {
    final registration = ref.read(mobileRegistrationProvider).asData?.value;
    final source = _sourceId;
    final destination = _destinationId;
    final batch = _batchId;
    final quantity = _quantity.text.trim();
    if (registration == null || source == null || destination == null ||
        batch == null || source == destination || _busy ||
        !RegExp(r'^\d{1,12}(?:\.\d{1,4})?$').hasMatch(quantity) ||
      setState(() => _message = 'Select distinct locations, a batch, and a positive quantity.');
      return;
    }
    final fingerprint = '$source|$destination|$batch|$quantity';
    if (_pendingRequestId != null && _pendingFingerprint != fingerprint) {
      setState(() => _message = 'Resolve the previous uncertain transfer before changing details.');
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm stock transfer'),
        content: Text('Move $quantity units of batch $batch from '
            '${_locations.firstWhere((item) => item.id == source).name} to '
            '${_locations.firstWhere((item) => item.id == destination).name}? '
            'This changes stock quantities on the server.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true),
              child: const Text('Confirm transfer')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final requestId = _pendingRequestId ?? const Uuid().v4();
    setState(() {
      _pendingRequestId = requestId;
      _pendingFingerprint = fingerprint;
      _busy = true;
      _message = null;
    });
    final client = http.Client();
    try {
      final result = await MobileStockLocationApi(
        environment: AppEnvironment.current,
        client: client,
      ).transfer(
        registration: registration,
        batchId: batch,
        destinationId: destination,
        quantity: quantity,
        requestId: requestId,
      );
      if (!mounted) return;
      setState(() {
        _pendingRequestId = null;
        _pendingFingerprint = null;
        _message = result.replayed
            ? 'Transfer already recorded; no duplicate movement created.'
            : 'Transfer completed successfully.';
        _batchId = null;
        _batches = [];
      });
    } on StockTransferUncertainException catch (error) {
      if (mounted) setState(() => _message = '$error The same request ID will be reused.');
    } catch (error) {
      if (mounted) {
        setState(() {
          _pendingRequestId = null;
          _pendingFingerprint = null;
          _message = 'Transfer not confirmed: $error';
        });
      }
    } finally {
      client.close();
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final registration = ref.watch(mobileRegistrationProvider).asData?.value;
    if (registration == null ||
        !registration.permissions.contains('inventory.manage')) {
      return const ListTile(
        leading: Icon(Icons.lock_outline),
        title: Text('Stock transfers'),
        subtitle: Text('Inventory management permission is required.'),
      );
    }
    final destinations = _locations.where((item) => item.id != _sourceId).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Stock transfers', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        const Text('Online only. Transfers require explicit confirmation.'),
        OutlinedButton.icon(
          onPressed: _busy || _pendingRequestId != null ? null : _load,
          icon: const Icon(Icons.refresh),
          label: const Text('Load locations'),
        ),
        DropdownButtonFormField<String>(
          key: ValueKey('source-$_sourceId'),
          initialValue: _sourceId,
          decoration: const InputDecoration(labelText: 'Source location'),
          items: _locations.map((item) => DropdownMenuItem(
            value: item.id, child: Text('${item.name} (${item.code})'),
          )).toList(),
          onChanged: _busy || _pendingRequestId != null ? null : (value) {
            if (value != null) _loadBatches(value);
          },
        ),
        DropdownButtonFormField<String>(
          key: ValueKey('destination-$_sourceId'),
          initialValue: destinations.any((item) => item.id == _destinationId)
              ? _destinationId : null,
          decoration: const InputDecoration(labelText: 'Destination location'),
          items: destinations.map((item) => DropdownMenuItem(
            value: item.id, child: Text('${item.name} (${item.code})'),
          )).toList(),
          onChanged: _busy || _pendingRequestId != null ? null :
              (value) => setState(() => _destinationId = value),
        ),
        DropdownButtonFormField<String>(
          key: ValueKey('batch-$_sourceId-${_batches.length}'),
          initialValue: _batchId,
          decoration: const InputDecoration(labelText: 'Source batch'),
          items: _batches.map((item) => DropdownMenuItem(
            value: item.id,
            child: Text('${item.medicineId} — available: ${item.availableQuantity}'),
          )).toList(),
          onChanged: _busy || _pendingRequestId != null ? null :
              (value) => setState(() => _batchId = value),
        ),
        TextField(
          controller: _quantity,
          enabled: !_busy && _pendingRequestId == null,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'Quantity to transfer'),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: _busy ? null : _transfer,
          icon: const Icon(Icons.swap_horiz),
          label: Text(_pendingRequestId == null ? 'Review transfer' : 'Retry same transfer'),
        ),
        if (_busy) const LinearProgressIndicator(),
        if (_message != null) Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text(_message!),
        ),
      ],
    );
  }
}
