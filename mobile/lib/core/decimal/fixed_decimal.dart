class FixedDecimal implements Comparable<FixedDecimal> {
  const FixedDecimal._(this._units);

  factory FixedDecimal.parse(String value) {
    final String normalized = value.trim();
    final RegExpMatch? match = RegExp(r'^([+-]?)(\d+)(?:\.(\d+))?

    if (match == null) {
      throw FormatException('Invalid decimal value: $value');
    }

    final String fraction = match.group(3) ?? '';
    if (fraction.length > scale) {
      throw FormatException(
        'Decimal values may have at most $scale fractional digits.',
      );
    }

    final BigInt whole = BigInt.parse(match.group(2)!);
    final BigInt fractional = BigInt.parse(
      fraction.padRight(scale, '0').isEmpty
          ? '0'
          : fraction.padRight(scale, '0'),
    );
    BigInt units = (whole * factor) + fractional;

    if (match.group(1) == '-') {
      units = -units;
    }

    return FixedDecimal._(units);
  }

  static const int scale = 4;
  static final BigInt factor = BigInt.from(10000);
  static final FixedDecimal zero = FixedDecimal._(BigInt.zero);

  final BigInt _units;

  FixedDecimal operator +(FixedDecimal other) {
    return FixedDecimal._(_units + other._units);
  }

  FixedDecimal operator -(FixedDecimal other) {
    return FixedDecimal._(_units - other._units);
  }

  FixedDecimal multiply(FixedDecimal other) {
    final BigInt product = _units * other._units;
    final BigInt quotient = product ~/ factor;
    final BigInt remainder = product.abs() % factor;

    if ((remainder * BigInt.two) < factor) {
      return FixedDecimal._(quotient);
    }

    return FixedDecimal._(
      quotient + (product.isNegative ? -BigInt.one : BigInt.one),
    );
  }

  bool get isNegative => _units.isNegative;
  bool get isZero => _units == BigInt.zero;
  bool get isPositive => _units > BigInt.zero;

  String get compact {
    final String fixed = toString();
    if (!fixed.contains('.')) {
      return fixed;
    }

    final String stripped = fixed.replaceFirst(RegExp(r'0+$'), '');
    return stripped.endsWith('.')
        ? stripped.substring(0, stripped.length - 1)
        : stripped;
  }

  @override
  int compareTo(FixedDecimal other) => _units.compareTo(other._units);

  @override
  bool operator ==(Object other) {
    return other is FixedDecimal && _units == other._units;
  }

  @override
  int get hashCode => _units.hashCode;

  @override
  String toString() {
    final bool negative = _units.isNegative;
    final BigInt absolute = _units.abs();
    final BigInt whole = absolute ~/ factor;
    final String fraction = (absolute % factor).toString().padLeft(scale, '0');

    return '${negative ? '-' : ''}$whole.$fraction';
  }
}
)
        .firstMatch(normalized);

    if (match == null) {
      throw FormatException('Invalid decimal value: $value');
    }

    final String fraction = match.group(3) ?? '';
    if (fraction.length > scale) {
      throw FormatException(
        'Decimal values may have at most $scale fractional digits.',
      );
    }

    final BigInt whole = BigInt.parse(match.group(2)!);
    final BigInt fractional = BigInt.parse(
      fraction.padRight(scale, '0').isEmpty
          ? '0'
          : fraction.padRight(scale, '0'),
    );
    BigInt units = (whole * factor) + fractional;

    if (match.group(1) == '-') {
      units = -units;
    }

    return FixedDecimal._(units);
  }

  static const int scale = 4;
  static final BigInt factor = BigInt.from(10000);
  static final FixedDecimal zero = FixedDecimal._(BigInt.zero);

  final BigInt _units;

  FixedDecimal operator +(FixedDecimal other) {
    return FixedDecimal._(_units + other._units);
  }

  FixedDecimal operator -(FixedDecimal other) {
    return FixedDecimal._(_units - other._units);
  }

  FixedDecimal multiply(FixedDecimal other) {
    final BigInt product = _units * other._units;
    final BigInt quotient = product ~/ factor;
    final BigInt remainder = product.abs() % factor;

    if ((remainder * BigInt.two) < factor) {
      return FixedDecimal._(quotient);
    }

    return FixedDecimal._(
      quotient + (product.isNegative ? -BigInt.one : BigInt.one),
    );
  }

  bool get isNegative => _units.isNegative;
  bool get isZero => _units == BigInt.zero;
  bool get isPositive => _units > BigInt.zero;

  String get compact {
    final String fixed = toString();
    if (!fixed.contains('.')) {
      return fixed;
    }

    final String stripped = fixed.replaceFirst(RegExp(r'0+$'), '');
    return stripped.endsWith('.')
        ? stripped.substring(0, stripped.length - 1)
        : stripped;
  }

  @override
  int compareTo(FixedDecimal other) => _units.compareTo(other._units);

  @override
  bool operator ==(Object other) {
    return other is FixedDecimal && _units == other._units;
  }

  @override
  int get hashCode => _units.hashCode;

  @override
  String toString() {
    final bool negative = _units.isNegative;
    final BigInt absolute = _units.abs();
    final BigInt whole = absolute ~/ factor;
    final String fraction = (absolute % factor)
        .toString()
        .padLeft(scale, '0');

    return '${negative ? '-' : ''}$whole.$fraction';
  }
}
