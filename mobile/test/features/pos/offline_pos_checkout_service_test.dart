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
    expect(
      await database
          .customSelect('SELECT COUNT(*) AS c FROM local_sale_batch_allocations')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle(),
      1,
    );

    final QueryRow batch = await database
        .customSelect(
          'SELECT available_quantity FROM local_inventory_batches WHERE id = ?',
          variables: <Variable<Object>>[
            const Variable<String>('batch-1'),
          ],
        )
        .getSingle();
    expect(batch.read<String>('available_quantity'), '18.0000');

    final QueryRow outbox = await database
        .customSelect('SELECT * FROM sync_outbox_entries')
        .getSingle();
    final Map<String, dynamic> payload =
        jsonDecode(outbox.read<String>('payload_json')) as Map<String, dynamic>;

    expect(outbox.read<String>('event_type'), 'sale.completed');
    expect(payload['cashier_user_id'], '7');
    expect(payload['total'], '24.0000');
    final List<dynamic> lines = payload['lines'] as List<dynamic>;
    final Map<String, dynamic> line = Map<String, dynamic>.from(
      lines.single as Map,
    );
    expect((line['batch_allocations'] as List<dynamic>).length, 1);
  });

  test('FEFO spans batches and local oversell is rejected atomically', () async {
    final int firstExpiry =
        DateTime(2026, 10, 1).millisecondsSinceEpoch ~/ 1000;
    final int secondExpiry =
        DateTime(2026, 11, 1).millisecondsSinceEpoch ~/ 1000;

    await database.customStatement(
      '''
      UPDATE local_inventory_batches
      SET available_quantity = '1.0000', expires_at = ?
      WHERE id = 'batch-1'
      ''',
      <Object?>[firstExpiry],
    );
    await database.customStatement(
      '''
      INSERT INTO local_inventory_batches (
        id, medicine_id, stock_location_id, batch_number, expires_at,
        available_quantity, sale_price, purchase_cost, status, is_deleted
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 0)
      ''',
      <Object?>[
        'batch-2',
        'medicine-1',
        'main-location',
        'LOT-2',
        secondExpiry,
        '2.0000',
        '12.5000',
        '8.5000',
      ],
    );

    await service.checkout(
      const OfflinePosCheckoutRequest(
        stockLocationId: 'main-location',
        cashierUserId: '7',
        permissions: <String>{'pos.sell'},
        lines: <OfflinePosLineInput>[
          OfflinePosLineInput(
            medicineId: 'medicine-1',
            quantity: '2.0000',
            unitPrice: '12.5000',
          ),
        ],
        payments: <OfflinePosPaymentInput>[
          OfflinePosPaymentInput(method: 'cash', amount: '25.0000'),
        ],
      ),
    );

    final List<QueryRow> allocations = await database.customSelect(
      '''
      SELECT product_batch_id, quantity
      FROM local_sale_batch_allocations
      ORDER BY created_at, local_id
      ''',
    ).get();
    expect(allocations.length, 2);
    expect(allocations[0].read<String>('product_batch_id'), 'batch-1');
    expect(allocations[0].read<String>('quantity'), '1.0000');
    expect(allocations[1].read<String>('product_batch_id'), 'batch-2');
    expect(allocations[1].read<String>('quantity'), '1.0000');

    final QueryRow secondBatch = await database
        .customSelect(
          'SELECT available_quantity FROM local_inventory_batches WHERE id = ?',
          variables: <Variable<Object>>[
            const Variable<String>('batch-2'),
          ],
        )
        .getSingle();
    expect(secondBatch.read<String>('available_quantity'), '1.0000');

    final int saleCountBefore = await database
        .customSelect('SELECT COUNT(*) AS c FROM local_sales')
        .map((QueryRow row) => row.read<int>('c'))
        .getSingle();

    await expectLater(
      service.checkout(
        const OfflinePosCheckoutRequest(
          stockLocationId: 'main-location',
          cashierUserId: '7',
          permissions: <String>{'pos.sell'},
          lines: <OfflinePosLineInput>[
            OfflinePosLineInput(
              medicineId: 'medicine-1',
              quantity: '2.0000',
              unitPrice: '12.5000',
            ),
          ],
          payments: <OfflinePosPaymentInput>[
            OfflinePosPaymentInput(method: 'cash', amount: '25.0000'),
          ],
        ),
      ),
      throwsA(
        isA<OfflinePosException>().having(
          (OfflinePosException error) => error.message,
          'message',
          contains('Insufficient locally cached eligible stock'),
        ),
      ),
    );

    expect(
      await database
          .customSelect('SELECT COUNT(*) AS c FROM local_sales')
          .map((QueryRow row) => row.read<int>('c'))
          .getSingle(),
      saleCountBefore,
    );
    final QueryRow preserved = await database
        .customSelect(
          'SELECT available_quantity FROM local_inventory_batches WHERE id = ?',
          variables: <Variable<Object>>[
            const Variable<String>('batch-2'),
          ],
        )
        .getSingle();
    expect(preserved.read<String>('available_quantity'), '1.0000');
  });

  test(
    'failed lease authorization leaves no partial local transaction',
    () async {
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
    },
  );

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
