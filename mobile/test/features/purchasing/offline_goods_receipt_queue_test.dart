import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_repository.dart';
import 'package:businessos_pharmacy/features/purchasing/data/goods_receipt_draft.dart';
import 'package:businessos_pharmacy/features/purchasing/data/offline_goods_receipt_queue.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;

  setUp(() {
    database = PharmacyDatabase(NativeDatabase.memory());
  });

  tearDown(() async {
    await database.close();
  });

  test('queued receipt survives in durable outbox with matching key', () async {
    final key = await OfflineGoodsReceiptQueue(database).enqueue(
      purchaseOrderId: '01KABCDEABCDEABCDEABCDEABC',
      orderStatus: 'approved',
      receivedAt: DateTime.utc(2026, 11, 3),
      lines: const <GoodsReceiptDraftLine>[
        GoodsReceiptDraftLine(
          purchaseOrderLineId: '01KABCDEABCDEABCDEABCDEABD',
          orderedQuantity: '10',
          previouslyReceivedQuantity: '2',
          receivedQuantity: '3.0000',
          unitCost: '12.5',
        ),
      ],
    );
    final events = await MobileSyncRepository(database).pendingOutbox();
    expect(events, hasLength(1));
    expect(events.single.eventType, 'goods_receipt.create');
    expect(events.single.idempotencyKey, key);
    expect(events.single.payload['idempotency_key'], key);
    expect(events.single.payload['purchase_order_id'], isNotEmpty);
    expect((events.single.payload['lines'] as List<dynamic>), hasLength(1));
  });

  test('invalid receipt never creates an outbox event', () async {
    await expectLater(
      OfflineGoodsReceiptQueue(database).enqueue(
        purchaseOrderId: 'order',
        orderStatus: 'draft',
        receivedAt: DateTime.utc(2026, 11, 3),
        lines: const <GoodsReceiptDraftLine>[
          GoodsReceiptDraftLine(
            purchaseOrderLineId: 'line',
            orderedQuantity: '1',
            previouslyReceivedQuantity: '0',
            receivedQuantity: '2',
            unitCost: '1',
          ),
        ],
      ),
      throwsFormatException,
    );
    expect(await MobileSyncRepository(database).pendingOutbox(), isEmpty);
  });
  test(
    'duplicate unresolved receipt is blocked even with padded order ID',
    () async {
      final queue = OfflineGoodsReceiptQueue(database);
      const lines = <GoodsReceiptDraftLine>[
        GoodsReceiptDraftLine(
          purchaseOrderLineId: 'line-1',
          orderedQuantity: '10',
          previouslyReceivedQuantity: '0',
          receivedQuantity: '2',
          unitCost: '12.5',
        ),
      ];
      await queue.enqueue(
        purchaseOrderId: 'order-1',
        orderStatus: 'approved',
        receivedAt: DateTime.utc(2026, 11, 3),
        lines: lines,
      );
      await expectLater(
        queue.enqueue(
          purchaseOrderId: ' order-1 ',
          orderStatus: 'approved',
          receivedAt: DateTime.utc(2026, 11, 3),
          lines: lines,
        ),
        throwsFormatException,
      );
      expect(
        await MobileSyncRepository(database).pendingOutbox(),
        hasLength(1),
      );
    },
  );
}
