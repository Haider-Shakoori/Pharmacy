import 'package:businessos_pharmacy/features/purchasing/data/goods_receipt_draft.dart';
import 'package:flutter/material.dart';

/// A local, non-posting receiving worksheet. Closing it discards entries.
class GoodsReceiptWorksheet extends StatefulWidget {
  const GoodsReceiptWorksheet({
    super.key,
    required this.orderNumber,
    required this.orderStatus,
    required this.lines,
  });

  final String orderNumber;
  final String orderStatus;
  final List<dynamic> lines;

  @override
  State<GoodsReceiptWorksheet> createState() => _GoodsReceiptWorksheetState();
}

class _GoodsReceiptWorksheetState extends State<GoodsReceiptWorksheet> {
  late final List<_LineInputs> inputs;
  List<String> errors = <String>[];

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

  void _validate() {
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
    setState(() {
      errors = GoodsReceiptDraftValidator.validate(
        drafts,
        orderStatus: widget.orderStatus,
      );
      if (errors.isEmpty) {
        errors = <String>[
          'Worksheet validated locally. Submission is unavailable until '
              'authenticated receipt synchronization and server-side checks '
              'are implemented. No inventory was changed.',
        ];
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Receiving • ${widget.orderNumber}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          const Text(
            'Local worksheet only. Entries are discarded on exit; '
            'no goods receipt or stock update will be submitted.',
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
            onPressed: _validate,
            icon: const Icon(Icons.fact_check_outlined),
            label: const Text('Validate worksheet'),
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
