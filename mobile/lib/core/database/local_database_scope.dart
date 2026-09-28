class LocalDatabaseScope {
  const LocalDatabaseScope._(this.storageKey);

  factory LocalDatabaseScope.forTenant(String tenantId) {
    final String normalized = tenantId
        .trim()
        .toLowerCase()
        .replaceAll(RegExp(r'[^a-z0-9_-]+'), '_')
        .replaceAll(RegExp(r'_+'), '_')
        .replaceAll(RegExp(r'^_+|_+$'), '');

    if (normalized.isEmpty) {
      throw const FormatException('Tenant database scope cannot be empty.');
    }

    return LocalDatabaseScope._(normalized);
  }

  static const LocalDatabaseScope unbound = LocalDatabaseScope._('unbound');

  final String storageKey;

  String get databaseName => 'businessos_pharmacy_$storageKey';
}
