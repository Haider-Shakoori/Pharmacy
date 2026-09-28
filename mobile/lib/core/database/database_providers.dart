import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/local_database_scope.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final FutureProvider<LocalDatabaseScope> localDatabaseScopeProvider =
    FutureProvider<LocalDatabaseScope>((Ref ref) async {
      final registration = await ref.watch(mobileRegistrationProvider.future);

      if (registration == null) {
        return LocalDatabaseScope.unbound;
      }

      return LocalDatabaseScope.forTenant(registration.tenantId);
    });

final FutureProvider<PharmacyDatabase> pharmacyDatabaseProvider =
    FutureProvider<PharmacyDatabase>((Ref ref) async {
      final LocalDatabaseScope scope = await ref.watch(
        localDatabaseScopeProvider.future,
      );
      final PharmacyDatabase database = PharmacyDatabase.forScope(scope);

      ref.onDispose(() {
        database.close();
      });

      return database;
    });
