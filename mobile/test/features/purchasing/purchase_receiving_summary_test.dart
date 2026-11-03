import 'package:businessos_pharmacy/features/purchasing/data/purchase_receiving_summary.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'receiving preview calculates outstanding and over-received quantities',
    () {
    final result = PurchaseReceivingSummary.fromLines(<dynamic>[
      <String, String>{
        'ordered_quantity': '10.5000',
        'received_quantity': '4.2500',
      },
      <String, String>{
        'ordered_quantity': '2.0000',
        'received_quantity': '3.0000',
      },
      <String, String>{
        'ordered_quantity': '1.0000',
        'received_quantity': '1.0000',
      },
    ]);
    expect(result.ordered.compact, '13.5');
    expect(result.received.compact, '8.25');
    expect(result.outstanding.compact, '6.25');
    expect(result.overReceived.compact, '1');
      expect(result.linesOutstanding, 1);
    },
  );

  test('negative receiving quantities are rejected', () {
    expect(
      () => PurchaseReceivingSummary.fromLines(<dynamic>[
        <String, String>{'ordered_quantity': '2', 'received_quantity': '-1'},
      ]),
      throwsFormatException,
    );
  });
}
