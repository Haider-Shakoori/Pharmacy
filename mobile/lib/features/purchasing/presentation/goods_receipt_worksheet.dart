import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/purchasing/data/goods_receipt_draft.dart';
import 'package:businessos_pharmacy/features/purchasing/data/offline_goods_receipt_queue.dart';
import 'package:flutter/material.dart';

/// A local, non-posting receiving worksheet. Closing it discards entries.
class GoodsReceiptWorksheet extends StatefulWidget {
  const GoodsReceiptWorksheet({
    super.key,
    required this.database,
    required this.purchaseOrderId,
    required this.orderNumber,
    required this.orderStatus,
    required this.lines,
  });

  final PharmacyDatabase database;
  final String purchaseOrderId;
  final String orderNumber;
  final String orderStatus;
  final List<dynamic> lines;

  @override
  State<GoodsReceiptWorksheet> createState() => _GoodsReceiptWorksheetState();
}

class _GoodsReceiptWorksheetState extends State<GoodsReceiptWorksheet> {
  late final List<_LineInputs> inputs;
  List<String> errors = <String>[];
  bool saving = false;

  @override
  void initState() {
    super.initState();
    inputs = widget.lines.whereType<Map<dynamic, dynamic>>().map((line) {
      return _LineInputs(
        data: line,
        quantity: TextEditingController(text: '0'),
        bonus: TextEditingController(text: '0'),
        batch: TextEditingController(),
        expiry: TextEditingController(),
        cost: TextEditingController(text: line['unit_cost']?.toString() ?? '0'),
      );
    }).toList();
  }

  @override
  void dispose() {
    for (final line in inputs) {
      line.quantity.dispose();
      line.bonus.dispose();
      line.batch.dispose();
      line.expiry.dispose();
      line.cost.dispose();
    }
    super.dispose();
  }

  List<GoodsReceiptDraftLine> _draftLines() {
    final drafts = inputs
        .map(
          (line) => GoodsReceiptDraftLine(
            purchaseOrderLineId: line.data['id']?.toString() ?? '',
            orderedQuantity: line.data['ordered_quantity']?.toString() ?? '0',
            previouslyReceivedQuantity:
                line.data['received_quantity']?.toString() ?? '0',
            receivedQuantity: line.quantity.text.trim(),
            bonusQuantity: line.bonus.text.trim(),
            unitCost: line.cost.text.trim(),
            batchNumber: line.batch.text.trim(),
            expiresAt: line.expiry.text.trim(),
            requiresBatch: line.data['batch_tracking_required'] == true,
            requiresExpiry: line.data['expiry_tracking_required'] == true,
          ),
        )
        .toList();
    return drafts;
  }

  Future<void> _queueReceipt() async {
    if (saving) return;
    final drafts = _draftLines();
    final validation = GoodsReceiptDraftValidator.validate(
      drafts, orderStatus: widget.orderStatus,
    );
    if (validation.isNotEmpty) {
      setState(() => errors = validation);
      return;
    }
    setState(() { saving = true; errors = <String>[]; });
    try {
      await OfflineGoodsReceiptQueue(widget.database).enqueue(
        purchaseOrderId: widget.purchaseOrderId,
        orderStatus: widget.orderStatus,
        lines: drafts,
        receivedAt: DateTime.now(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Receipt queued offline. Stock is unchanged until server inventory posting.')),
      );
      Navigator.of(context).pop();
    } catch (error) {
      if (mounted) setState(() => errors = <String>['Unable to queue receipt: $error']);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Receiving • ${widget.orderNumber}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          const Text(
            'Save this receipt to the offline sync queue. The server will '
            'validate it before inventory can be posted.',
          ),
          const SizedBox(height: 12),
          for (final line in inputs)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      line.data['description']?.toString() ??
                          line.data['medicine_id']?.toString() ??
                          'Medicine',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Text(
                      'Ordered: ${line.data['ordered_quantity']} • '
                      'Previously received: ${line.data['received_quantity']}',
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: line.quantity,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(
                        labelText: 'Received quantity',
                      ),
                    ),
                    TextField(
                      controller: line.bonus,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(
                        labelText: 'Bonus quantity',
                      ),
                    ),
                    TextField(
                      controller: line.cost,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(labelText: 'Unit cost'),
                    ),
                    TextField(
                      controller: line.batch,
                      decoration: const InputDecoration(
                        labelText: 'Batch number',
                      ),
                    ),
                    TextField(
                      controller: line.expiry,
                      decoration: const InputDecoration(
                        labelText: 'Expiry date (YYYY-MM-DD)',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          for (final error in errors)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Text(
                error,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: saving ? null : _queueReceipt,
            icon: const Icon(Icons.save_outlined),
            label: Text(saving ? 'Saving...' : 'Queue goods receipt'),
          ),
        ],
      ),
    );
  }
}

class _LineInputs {
  const _LineInputs({
    required this.data,
    required this.quantity,
    required this.bonus,
    required this.batch,
    required this.expiry,
    required this.cost,
  });
  final Map<dynamic, dynamic> data;
  final TextEditingController quantity;
  final TextEditingController bonus;
  final TextEditingController batch;
  final TextEditingController expiry;
  final TextEditingController cost;
}
