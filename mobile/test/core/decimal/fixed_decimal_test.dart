import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('fixed decimal keeps four-place pharmacy precision', () {
    expect(FixedDecimal.parse('1.2').toString(), '1.2000');
    expect(
      FixedDecimal.parse('3.5000')
          .multiply(FixedDecimal.parse('12.3456'))
          .toString(),
      '43.2096',
    );
    expect(
      (FixedDecimal.parse('100') - FixedDecimal.parse('0.0001')).toString(),
      '99.9999',
    );
  });

  test('more than four fractional digits are rejected', () {
    expect(
      () => FixedDecimal.parse('1.00001'),
      throwsA(isA<FormatException>()),
    );
  });
}
