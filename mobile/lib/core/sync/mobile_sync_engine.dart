import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/network/connection_profile_repository.dart';
import 'package:businessos_pharmacy/core/network/connection_route_resolver.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_repository.dart';
import 'package:businessos_pharmacy/core/sync/mobile_sync_transport.dart';
import 'package:businessos_pharmacy/core/sync/sync_models.dart';

class MobileSyncEngine {
  const MobileSyncEngine({
    required this.registrationRepository,
    required this.profileRepository,
    required this.routeResolver,
    required this.transport,
    required this.repository,
  });

  final MobileRegistrationRepository registrationRepository;
  final ConnectionProfileRepository profileRepository;
  final ConnectionRouteResolver routeResolver;
  final MobileSyncTransport transport;
  final MobileSyncRepository repository;

  Future<SyncRunResult> syncNow() async {
    final MobileRegistration? current = await registrationRepository.load();
    if (current == null) {
      throw const MobileSyncException(
        'Register this device before synchronization.',
      );
    }

    final profile = await profileRepository.load();
    final route = await routeResolver.resolve(profile);
    final ServerEndpoint? endpoint = route.endpoint;

    if (endpoint == null) {
      throw const MobileSyncException(
        'No configured pharmacy server is reachable.',
      );
    }

    final MobileRegistration registration = await transport.refreshSession(
      endpoint: endpoint,
      registration: current,
    );
    await registrationRepository.save(registration);

    int pushed = 0;
    int rejected = 0;
    int pulled = 0;

    final List<SyncOutboxEvent> events = await repository.pendingOutbox();
    if (events.isNotEmpty) {
      await repository.markSending(events);

      try {
        final List<SyncPushResult> results = await transport.push(
          endpoint: endpoint,
          registration: registration,
          events: events,
        );
        final Map<String, SyncPushResult> byKey = <String, SyncPushResult>{
          for (final SyncPushResult result in results)
            result.idempotencyKey: result,
        };

        for (final SyncOutboxEvent event in events) {
          final SyncPushResult? result = byKey[event.idempotencyKey];
          if (result == null) {
            await repository.reject(
              event,
              SyncPushResult(
                idempotencyKey: event.idempotencyKey,
                status: 'rejected',
                code: 'missing_acknowledgement',
                message: 'The server did not acknowledge this event.',
                retryable: true,
              ),
            );
            continue;
          }

          if (result.accepted) {
            await repository.acknowledge(event, result);
            pushed += 1;
          } else {
            await repository.reject(event, result);
            if (!result.retryable) {
              rejected += 1;
            }
          }
        }
      } on MobileSyncException catch (error) {
        await repository.retryTransportFailure(events, error.message);
        rethrow;
      }
    }

    for (final String stream in <String>[
      'medicines',
      'inventory',
      'customers',
    ]) {
      String? cursor = await repository.checkpoint(stream);
      int pageCount = 0;

      while (pageCount < 50) {
        final SyncPullPage page = await transport.pull(
          endpoint: endpoint,
          registration: registration,
          stream: stream,
          cursor: cursor,
        );

        await repository.applyPullPage(page);
        pulled += page.data.length;
        cursor = page.nextCursor;
        pageCount += 1;

        if (!page.hasMore) {
          break;
        }
      }
    }

    return SyncRunResult(
      pushed: pushed,
      rejected: rejected,
      pulled: pulled,
      status: await repository.status(),
    );
  }
}
