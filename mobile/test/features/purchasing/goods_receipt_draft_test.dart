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
    final errors = GoodsReceiptDraftValidator.validate(
      <GoodsReceiptDraftLine>[
        line(),
        const GoodsReceiptDraftLine(
          purchaseOrderLineId: ' po-line-1 ',
          orderedQuantity: '10',
          previouslyReceivedQuantity: '0',
          receivedQuantity: '1',
          unitCost: '10',
        ),
      ],
      orderStatus: 'approved',
    );
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
      GoodsReceiptDraftValidator.validate(
        <GoodsReceiptDraftLine>[item],
        orderStatus: 'approved',
      ).join(' '),
      contains('Manufacturing date cannot be after expiry'),
    );
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
}
