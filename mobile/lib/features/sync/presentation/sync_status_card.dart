import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/core/network/connection_profile_repository.dart';
import 'package:businessos_pharmacy/core/network/connection_route_resolver.dart';
import 'package:businessos_pharmacy/core/network/server_reachability.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_engine.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_repository.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_transport.dart';
import 'package:businessos_pharmacy/core/sync/sync_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;

class SyncStatusCard extends ConsumerStatefulWidget {
  const SyncStatusCard({super.key});

  @override
  ConsumerState<SyncStatusCard> createState() => _SyncStatusCardState();
}

class _SyncStatusCardState extends ConsumerState<SyncStatusCard> {
  SyncStatusSnapshot? _status;
  bool _loading = true;
  bool _syncing = false;
  String? _message;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final PharmacyDatabase database = await ref.read(
        pharmacyDatabaseProvider.future,
      );
      final SyncStatusSnapshot status =
          await MobileSyncRepository(database).status();

      if (!mounted) {
        return;
      }

      setState(() {
        _status = status;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error.toString();
      });
    }
  }

  Future<void> _syncNow() async {
    final AppStrings strings = AppStrings.of(context);
    final http.Client client = http.Client();

    setState(() {
      _syncing = true;
      _error = null;
      _message = null;
    });

    try {
      final PharmacyDatabase database = await ref.read(
        pharmacyDatabaseProvider.future,
      );
      final SecureStore store = ref.read(secureStoreProvider);
      final MobileSyncRepository repository = MobileSyncRepository(database);
      final ConnectionProfileRepository profileRepository =
          ConnectionProfileRepository(
            store,
            defaultCloudUri: AppEnvironment.current.apiBaseUri,
          );

      final ConnectionRouteResolver resolver = ConnectionRouteResolver(
        (endpoint) async {
          final ServerReachability state = await ServerReachabilityProbe(
            client,
            endpoint.uri,
          ).check();
          return state == ServerReachability.reachable;
        },
      );

      final MobileSyncEngine engine = MobileSyncEngine(
        registrationRepository: ref.read(
          mobileRegistrationRepositoryProvider,
        ),
        profileRepository: profileRepository,
        routeResolver: resolver,
        transport: HttpMobileSyncTransport(client),
        repository: repository,
      );

      final SyncRunResult result = await engine.syncNow();
      ref.invalidate(mobileRegistrationProvider);

      if (!mounted) {
        return;
      }

      setState(() {
        _syncing = false;
        _status = result.status;
        _message =
            '${strings.syncCompleted}: '
            '${result.pushed} ${strings.syncPushed}, '
            '${result.pulled} ${strings.syncPulled}.';
      });
    } on MobileSyncException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _syncing = false;
        _error = error.message;
      });
    } on Object catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _syncing = false;
        _error = error.toString();
      });
    } finally {
      client.close();
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    final AsyncValue<MobileRegistration?> registration = ref.watch(
      mobileRegistrationProvider,
    );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: registration.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (Object error, StackTrace stackTrace) =>
              Text(error.toString()),
          data: (MobileRegistration? value) {
            if (value == null) {
              return Text(strings.registrationRequired);
            }

            if (_loading) {
              return const Center(child: CircularProgressIndicator());
            }

            final SyncStatusSnapshot status =
                _status ??
                const SyncStatusSnapshot(pending: 0, rejected: 0);

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  strings.syncStatusTitle,
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text(
                  '${strings.syncPending}: ${status.pending} · '
                  '${strings.syncRejected}: ${status.rejected}',
                ),
                const SizedBox(height: 4),
                Text(
                  status.lastSyncedAt == null
                      ? strings.syncNever
                      : '${strings.syncLast}: ${status.lastSyncedAt}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                if (_message != null) ...<Widget>[
                  const SizedBox(height: 12),
                  Text(_message!),
                ],
                if (_error != null) ...<Widget>[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _syncing ? null : _syncNow,
                  icon: const Icon(Icons.sync_rounded),
                  label: Text(
                    _syncing ? strings.syncing : strings.syncNow,
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
