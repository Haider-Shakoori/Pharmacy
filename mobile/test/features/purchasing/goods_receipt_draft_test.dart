import 'package:businessos_pharmacy/features/purchasing/data/goods_receipt_draft.dart';
import 'package:flutter_test/flutter_test.dart';

GoodsReceiptDraftLine line({
  String ordered = '10',
  String previous = '4',
  String received = '6',
  String bonus = '0',
  String batch = 'B-1',
  String expiry = '2027-12-01',
  bool requiresBatch = true,
  bool requiresExpiry = true,
}) => GoodsReceiptDraftLine(
  purchaseOrderLineId: 'po-line-1',
  orderedQuantity: ordered,
  previouslyReceivedQuantity: previous,
  receivedQuantity: received,
  bonusQuantity: bonus,
  unitCost: '10.0000',
  batchNumber: batch,
  expiresAt: expiry,
  requiresBatch: requiresBatch,
  requiresExpiry: requiresExpiry,
);

void main() {
  test('allows valid partial receipt for an approved order', () {
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        line(received: '2'),
      ], orderStatus: 'approved'),
      isEmpty,
    );
  });

  test('rejects quantity beyond outstanding balance', () {
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        line(received: '7'),
      ], orderStatus: 'approved').join(' '),
      contains('exceeds'),
    );
  });

  test('requires batch and expiry for tracked medicines', () {
    final errors = GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
      line(batch: '', expiry: ''),
    ], orderStatus: 'approved').join(' ');
    expect(errors, contains('batch number'));
    expect(errors, contains('expiry date'));
  });

  test('rejects duplicate order line IDs', () {
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        line(),
        line(),
      ], orderStatus: 'approved').join(' '),
      contains('distinct'),
    );
  });

  test('rejects duplicate order line IDs with surrounding whitespace', () {
    final errors = GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
      line(),
      const GoodsReceiptDraftLine(
        purchaseOrderLineId: ' po-line-1 ',
        orderedQuantity: '10',
        previouslyReceivedQuantity: '0',
        receivedQuantity: '1',
        unitCost: '10',
      ),
    ], orderStatus: 'approved');
    expect(errors.join(' '), contains('distinct'));
  });

  test('receipt payload normalizes the order line identifier', () {
    const item = GoodsReceiptDraftLine(
      purchaseOrderLineId: ' line-1 ',
      orderedQuantity: '5',
      previouslyReceivedQuantity: '0',
      receivedQuantity: '2',
      unitCost: '10',
    );
    expect(item.toPayload()['purchase_order_line_id'], 'line-1');
  });

  test('rejects manufacturing dates later than expiry', () {
    const item = GoodsReceiptDraftLine(
      purchaseOrderLineId: 'line-dated',
      orderedQuantity: '5',
      previouslyReceivedQuantity: '0',
      receivedQuantity: '2',
      unitCost: '10',
      manufacturedAt: '2027-12-02',
      expiresAt: '2027-12-01',
    );
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        item,
      ], orderStatus: 'approved').join(' '),
      contains('Manufacturing date cannot be after expiry'),
    );
  });

  test('trims receipt metadata before submission', () {
    const item = GoodsReceiptDraftLine(
      purchaseOrderLineId: ' line-1 ',
      orderedQuantity: '5',
      previouslyReceivedQuantity: '0',
      receivedQuantity: '2',
      unitCost: '10',
      batchNumber: ' B-123 ',
      expiresAt: ' 2027-12-01 ',
      manufacturedAt: ' 2026-01-01 ',
      salePrice: ' 15 ',
    );
    expect(item.toPayload()['batch_number'], 'B-123');
    expect(item.toPayload()['expires_at'], '2027-12-01');
    expect(item.toPayload()['manufactured_at'], '2026-01-01');
    expect(item.toPayload()['sale_price'], '15');
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        item,
      ], orderStatus: 'approved'),
      isEmpty,
    );
  });

  test('normalizes receipt numeric fields and padded order status', () {
    const item = GoodsReceiptDraftLine(
      purchaseOrderLineId: ' line-1 ',
      orderedQuantity: '5',
      previouslyReceivedQuantity: '0',
      receivedQuantity: ' 2 ',
      bonusQuantity: ' 1 ',
      unitCost: ' 10.0000 ',
    );
    final payload = item.toPayload();
    expect(payload['received_quantity'], '2');
    expect(payload['bonus_quantity'], '1');
    expect(payload['unit_cost'], '10.0000');
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        item,
      ], orderStatus: ' approved '),
      isEmpty,
    );
  });

  test('omits whitespace-only optional receipt fields', () {
    const item = GoodsReceiptDraftLine(
      purchaseOrderLineId: 'line-blank',
      orderedQuantity: '2',
      previouslyReceivedQuantity: '0',
      receivedQuantity: '1',
      unitCost: '10',
      batchNumber: ' ',
      expiresAt: '',
      manufacturedAt: '   ',
      salePrice: ' ',
    );
    final payload = item.toPayload();
    for (final field in <String>[
      'batch_number',
      'expires_at',
      'manufactured_at',
      'sale_price',
    ]) {
      expect(payload[field], isNull);
    }
  });

  test('validates metadata on zero-received lines in a mixed receipt', () {
    const good = GoodsReceiptDraftLine(
      purchaseOrderLineId: 'line-good',
      orderedQuantity: '5',
      previouslyReceivedQuantity: '0',
      receivedQuantity: '1',
      unitCost: '10',
    );
    for (final invalid in <GoodsReceiptDraftLine>[
      const GoodsReceiptDraftLine(
        purchaseOrderLineId: 'line-zero',
        orderedQuantity: '5',
        previouslyReceivedQuantity: '0',
        receivedQuantity: '0',
        unitCost: '10',
        expiresAt: 'not-a-date',
      ),
      const GoodsReceiptDraftLine(
        purchaseOrderLineId: 'line-zero',
        orderedQuantity: '5',
        previouslyReceivedQuantity: '0',
        receivedQuantity: '0',
        unitCost: '10',
        manufacturedAt: 'invalid',
      ),
      const GoodsReceiptDraftLine(
        purchaseOrderLineId: 'line-zero',
        orderedQuantity: '5',
        previouslyReceivedQuantity: '0',
        receivedQuantity: '0',
        unitCost: '10',
        salePrice: '-1',
      ),
    ]) {
      final errors = GoodsReceiptDraftValidator.validate(
        <GoodsReceiptDraftLine>[good, invalid],
        orderStatus: 'approved',
      );
      expect(errors, isNotEmpty);
    }
  });

  test('rejects receipts for draft orders', () {
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        line(),
      ], orderStatus: 'draft').join(' '),
      contains('approved'),
    );
  });

  test('rejects empty receipt quantities and negative costs', () {
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        line(received: '0'),
      ], orderStatus: 'approved').join(' '),
      contains('positive'),
    );
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        GoodsReceiptDraftLine(
          purchaseOrderLineId: 'line-2',
          orderedQuantity: '5',
          previouslyReceivedQuantity: '0',
          receivedQuantity: '1',
          unitCost: '-1',
        ),
      ], orderStatus: 'approved').join(' '),
      contains('negative'),
    );
  });
  test('enforces Laravel 120-character batch number limit', () {
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        line(batch: 'B' * 120),
      ], orderStatus: 'approved'),
      isEmpty,
    );
    expect(
      GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
        line(batch: 'B' * 121),
      ], orderStatus: 'approved').join(' '),
      contains('120 characters'),
    );
  });

  test('rejects receipt values above server numeric ceilings', () {
    const item = GoodsReceiptDraftLine(
      purchaseOrderLineId: 'line-limits',
      orderedQuantity: '1000000001',
      previouslyReceivedQuantity: '0',
      receivedQuantity: '1000000000',
      bonusQuantity: '1000000000',
      unitCost: '1000000000000',
      salePrice: '1000000000000',
    );
    final errors = GoodsReceiptDraftValidator.validate(<GoodsReceiptDraftLine>[
      item,
    ], orderStatus: 'approved').join(' ');
    expect(errors, contains('999999999'));
    expect(errors, contains('Unit cost'));
    expect(errors, contains('Sale price'));
  });
}
