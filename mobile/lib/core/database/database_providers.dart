import 'package:businessos_pharmacy/core/database/local_database_scope.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<LocalDatabaseScope> localDatabaseScopeProvider =
    Provider<LocalDatabaseScope>((Ref ref) => LocalDatabaseScope.unbound);

final Provider<PharmacyDatabase> pharmacyDatabaseProvider =
    Provider<PharmacyDatabase>((Ref ref) {
      final LocalDatabaseScope scope = ref.watch(localDatabaseScopeProvider);
      final PharmacyDatabase database = PharmacyDatabase.forScope(scope);

      ref.onDispose(database.close);

      return database;
    });
