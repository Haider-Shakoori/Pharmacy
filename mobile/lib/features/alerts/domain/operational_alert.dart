enum OperationalAlertKind { lowStock, nearExpiry, expired, syncRejected }

enum OperationalAlertSeverity { warning, critical }

class LocalOperationalAlert {
  const LocalOperationalAlert({
    required this.kind,
    required this.severity,
    required this.title,
    required this.detail,
  });

  final OperationalAlertKind kind;
  final OperationalAlertSeverity severity;
  final String title;
  final String detail;
}

class OperationalAlertSnapshot {
  const OperationalAlertSnapshot({
    required this.alerts,
    required this.lowStockCount,
    required this.nearExpiryCount,
    required this.expiredCount,
    required this.syncRejectedCount,
  });

  final List<LocalOperationalAlert> alerts;
  final int lowStockCount;
  final int nearExpiryCount;
  final int expiredCount;
  final int syncRejectedCount;

  int get total =>
      lowStockCount + nearExpiryCount + expiredCount + syncRejectedCount;

  bool get hasCritical => expiredCount > 0 || syncRejectedCount > 0;

  String get fingerprint =>
      '$lowStockCount:$nearExpiryCount:$expiredCount:$syncRejectedCount';
}
