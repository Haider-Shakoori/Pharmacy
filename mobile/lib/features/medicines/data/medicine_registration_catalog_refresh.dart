import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/network/connection_profile_repository.dart';
import 'package:businessos_pharmacy/core/network/connection_route_resolver.dart';
import 'package:businessos_pharmacy/core/network/server_reachability.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_engine.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_repository.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_transport.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;

/// Reuses the existing tenant-scoped sync engine after an online create.
/// Never inserts an unsynchronized medicine into the local catalog.
Future<void> refreshRegisteredMedicineCatalog(WidgetRef ref) async {
  final client = http.Client();
  try {
    final database = await ref.read(pharmacyDatabaseProvider.future);
    final store = ref.read(secureStoreProvider);
    final profiles = ConnectionProfileRepository(
      store,
      defaultCloudUri: AppEnvironment.current.apiBaseUri,
    );
    final resolver = ConnectionRouteResolver((endpoint) async {
      final state = await ServerReachabilityProbe(
        client,
        endpoint.uri,
      ).check();
      return state == ServerReachability.reachable;
    });
    final engine = MobileSyncEngine(
      registrationRepository: ref.read(mobileRegistrationRepositoryProvider),
      profileRepository: profiles,
      routeResolver: resolver,
      transport: HttpMobileSyncTransport(client),
      repository: MobileSyncRepository(database),
    );
    await engine.syncNow();
    ref.invalidate(mobileRegistrationProvider);
  } finally {
    client.close();
  }
}
