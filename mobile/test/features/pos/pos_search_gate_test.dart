import 'package:businessos_pharmacy/features/pos/domain/pos_search_gate.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('only newest search result can update the visible catalog', () {
    final gate = PosSearchGate();
    final first = gate.begin();
    final second = gate.begin();
    expect(gate.isCurrent(first), isFalse);
    expect(gate.isCurrent(second), isTrue);
  });

  test('changing location invalidates an outstanding search', () {
    final gate = PosSearchGate();
    final pending = gate.begin();
    gate.invalidate();
    expect(gate.isCurrent(pending), isFalse);
  });
}
