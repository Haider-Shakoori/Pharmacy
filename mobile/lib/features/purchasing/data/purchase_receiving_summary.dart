import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';

/// Quantities are derived from the server's last synchronized order snapshot.
/// This is a receiving preview, not an authorization to update stock offline.
class PurchaseReceivingSummary {
  const PurchaseReceivingSummary({
    required this.ordered,
    required this.received,
    required this.outstanding,
    required this.overReceived,
    required this.linesOutstanding,
  });

  final FixedDecimal ordered;
  final FixedDecimal received;
  final FixedDecimal outstanding;
  final FixedDecimal overReceived;
  final int linesOutstanding;

  static PurchaseReceivingSummary fromLines(List<dynamic> lines) {
    var ordered = FixedDecimal.zero;
    var received = FixedDecimal.zero;
    var outstanding = FixedDecimal.zero;
    var overReceived = FixedDecimal.zero;
    var linesOutstanding = 0;

    for (final raw in lines) {
      if (raw is! Map) continue;
      final quantity = FixedDecimal.parse(
        raw['ordered_quantity']?.toString() ?? '0',
      );
      final delivered = FixedDecimal.parse(
        raw['received_quantity']?.toString() ?? '0',
      );
      if (quantity.isNegative || delivered.isNegative) {
        throw const FormatException('Purchase quantities cannot be negative.');
      }
      ordered += quantity;
      received += delivered;
      final remaining = quantity - delivered;
      if (remaining.isPositive) {
        outstanding += remaining;
        linesOutstanding++;
      } else if (remaining.isNegative) {
        overReceived += delivered - quantity;
      }
    }
    return PurchaseReceivingSummary(
      ordered: ordered,
      received: received,
      outstanding: outstanding,
      overReceived: overReceived,
      linesOutstanding: linesOutstanding,
    );
  }
}
