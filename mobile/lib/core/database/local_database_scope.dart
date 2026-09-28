[Reading 26 lines from start (total: 26 lines, 0 remaining)]

class LocalDatabaseScope {
  const LocalDatabaseScope._(this.storageKey);

  factory LocalDatabaseScope.forTenant(String tenantId) {
    final String normalized = tenantId
        .trim()
        .toLowerCase()
        .replaceAll(RegExp(r'[^a-z0-9_-]+'), '_')
        .replaceAll(RegExp(r'_+'), '_')
        .replaceAll(RegExp(r'^_+|_+$'), '');

    if (normalized.isEmpty || !RegExp(r'[a-z0-9]').hasMatch(normalized)) {
      throw const FormatException(
        'Tenant database scope must contain an alphanumeric character.',
      );
    }

    return LocalDatabaseScope._(normalized);
  }

  static const LocalDatabaseScope unbound = LocalDatabaseScope._('unbound');

  final String storageKey;

  String get databaseName => 'businessos_pharmacy_$storageKey';
}

[executed on device: ubuntu-6gb-dal-x8mx (7a7959a3-59c9-4f84-acec-6fd985dc6d01)]