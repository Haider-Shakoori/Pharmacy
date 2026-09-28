import 'dart:convert';

import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/features/pos/data/offline_pos_checkout_service.dart';
import 'package:businessos_pharmacy/features/pos/domain/offline_pos_models.dart';
import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PharmacyDatabase database;
  late OfflinePosCheckoutService service;

  setUp(() async {
    database = PharmacyDatabase(NativeDatabase.memory());
    int idCounter = 0;
    service = OfflinePosCheckoutService(
      database,
      authorizeTransaction: () async {},
      idGenerator: () {
        idCounter += 1;
        return '00000000-0000-4000-8000-${idCounter.toString().padLeft(12, '0')}';
      },
      clock: () => DateTime(2026, 9, 28, 10, 30),
    );

    await database.customStatement(
      '''
      INSERT INTO local_medicines (
        id, medicine_code, brand_name, generic_name, sale_unit,
        is_active, is_deleted
      ) VALUES (?, ?, ?, ?, ?, 1, 0)
      ''',
      <Object?>['medicine-1', 'MED-001', 'Test Medicine', 'Generic', 'box'],
    );

    await database.customStatement(
      '''
      INSERT INTO local_inventory_batches (
        id, medicine_id, stock_location_id, batch_number,
        available_quantity, sale_price, purchase_cost, status, is_deleted
      ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0)
      ''',
      <Object?>[
        'batch-1',
        'medicine-1',
        'main-location',
        'LOT-1',
        '20.0000',
        '12.5000',
        '8.0000',
      ],
    );
  });

  tearDown(() async {
    await database.close();
  });

  test('offline checkout commits sale payment and outbox atomically', () async {
    final OfflinePosCheckoutResult result = await service.checkout(
      const OfflinePosCheckoutRequest(
        stockLocationId: 'main-location',
        cashierUserId: '7',
        permissions: <String>{'pos.sell', 'pos.discount'},
        lines: <OfflinePosLineInput>[
          OfflinePosLineInput(
            medicineId: 'medicine-1',
            quantity: '2',
            unitPrice: '12.5000',
            discountAmount: '1.0000',
          ),
        ],
        payments: <OfflinePosPaymentInput>[
          OfflinePosPaymentInput(method: 'cash', amount: '30'),
        ],
      ),
    );

    expect(result.subtotal.toString(), '25.0000');
    expect(result.total.toString(), '24.0000');
    expect(result.changeAmount.toString(), '6.0000');

    final QueryRow sale = await database
        .customSelect('SELECT * FROM local_sales')
        .getSingle();
    expect(sale.read<String>('status'), 'completed');
    expect(sale.read<String>('sync_state'), 'pending');
    expect(sale.read<String>('total'), '24.0000');

    expect(
      await database
          .customSelect('SELECT COUNT(*) AS c FROM local_sale_lines')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle(),
      1,
    );
    expect(
      await database
          .customSelect('SELECT COUNT(*) AS c FROM local_sale_payments')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle(),
      1,
    );

    final QueryRow outbox = await database
        .customSelect('SELECT * FROM sync_outbox_entries')
        .getSingle();
    final Map<String, dynamic> payload =
        jsonDecode(outbox.read<String>('payload_json')) as Map<String, dynamic>;

    expect(outbox.read<String>('event_type'), 'sale.completed');
    expect(payload['cashier_user_id'], '7');
    expect(payload['total'], '24.0000');
  });

  test('failed lease authorization leaves no partial local transaction', () async {
    final OfflinePosCheckoutService blocked = OfflinePosCheckoutService(
      database,
      authorizeTransaction: () async {
        throw const OfflinePosException('Lease expired.');
      },
      idGenerator: () => 'blocked-id',
      clock: () => DateTime(2026, 9, 28, 10, 30),
    );

    await expectLater(
      blocked.checkout(
        const OfflinePosCheckoutRequest(
          stockLocationId: 'main-location',
          cashierUserId: '7',
          permissions: <String>{'pos.sell'},
          lines: <OfflinePosLineInput>[
            OfflinePosLineInput(
              medicineId: 'medicine-1',
              quantity: '1',
              unitPrice: '12.5000',
            ),
          ],
          payments: <OfflinePosPaymentInput>[
            OfflinePosPaymentInput(method: 'cash', amount: '12.5000'),
          ],
        ),
      ),
      throwsA(isA<OfflinePosException>()),
    );

    final int count = await database
        .customSelect('SELECT COUNT(*) AS c FROM local_sales')
        .map((QueryRow row) => row.read<int>('c'))
        .getSingle();
    expect(count, 0);
  });

  test(
    'failed permission validation leaves no partial local transaction',
    () async {
      await expectLater(
        service.checkout(
          const OfflinePosCheckoutRequest(
            stockLocationId: 'main-location',
            cashierUserId: '7',
            permissions: <String>{'pos.sell'},
            lines: <OfflinePosLineInput>[
              OfflinePosLineInput(
                medicineId: 'medicine-1',
                quantity: '1',
                unitPrice: '12.5000',
                discountAmount: '1.0000',
              ),
            ],
            payments: <OfflinePosPaymentInput>[
              OfflinePosPaymentInput(method: 'cash', amount: '20'),
            ],
          ),
        ),
        throwsA(isA<OfflinePosException>()),
      );

      final int saleCount = await database
          .customSelect('SELECT COUNT(*) AS c FROM local_sales')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle();
      final int lineCount = await database
          .customSelect('SELECT COUNT(*) AS c FROM local_sale_lines')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle();
      final int outboxCount = await database
          .customSelect('SELECT COUNT(*) AS c FROM sync_outbox_entries')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle();

      expect(saleCount, 0);
      expect(lineCount, 0);
      expect(outboxCount, 0);
    },
  );
}
