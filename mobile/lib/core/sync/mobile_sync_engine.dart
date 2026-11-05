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
      final Set<String> eventKeys = events
          .map((event) => event.idempotencyKey)
          .toSet();
      if (eventKeys.length != events.length) {
        throw const MobileSyncException(
          'The local synchronization queue contains duplicate event keys.',
        );
      }
      try {
        await repository.markSending(events);
        final List<SyncPushResult> results = await transport.push(
          endpoint: endpoint,
          registration: registration,
          events: events,
        );
        final Map<String, SyncPushResult> byKey = <String, SyncPushResult>{};
        final Set<String> requestedKeys = events
            .map((event) => event.idempotencyKey)
            .toSet();
        for (final SyncPushResult result in results) {
          if (!requestedKeys.contains(result.idempotencyKey) ||
              byKey.containsKey(result.idempotencyKey)) {
            throw const MobileSyncException(
              'The server returned duplicate or unexpected acknowledgements. '
              'The pending transactions will be retried safely.',
            );
          }
          byKey[result.idempotencyKey] = result;
        }

        // Validate every acknowledgement before applying any local outcome.
        // An incomplete response can be retried, but a malformed response
        // must not partially acknowledge sales before failing validation.
        for (final SyncOutboxEvent event in events) {
          final SyncPushResult? result = byKey[event.idempotencyKey];
          if (result != null &&
              result.accepted &&
              event.eventType == 'sale.completed' &&
              (result.serverId == null || result.serverId!.trim().isEmpty)) {
            throw const MobileSyncException(
              'An accepted sale acknowledgement is missing its server ID. '
              'Pending transactions will be retried safely.',
            );
          }
        }

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
      } catch (error) {
        // Do not leave outbox events in the sending state if the transport
        // throws an unexpected exception. Preserve their idempotency keys
        // and schedule a retry without posting inventory locally.
        await repository.retryTransportFailure(events, error.toString());
        rethrow;
      }
    }

    for (final String stream in <String>[
      'medicines',
      'inventory',
      'customers',
      'suppliers',
      'purchase_orders',
    ]) {
      String? cursor = await repository.checkpoint(stream);
      int pageCount = 0;
      final Set<String> seenCursors = <String>{
        if (cursor != null) cursor,
      };
      const int maxPagesPerStream = 50;

      while (pageCount < maxPagesPerStream) {
        final SyncPullPage page = await transport.pull(
          endpoint: endpoint,
          registration: registration,
          stream: stream,
          cursor: cursor,
        );

        if (page.stream != stream) {
          throw MobileSyncException(
            'Synchronization returned the wrong stream for $stream.',
          );
        }
        if (page.hasMore &&
            (page.nextCursor == null ||
                page.nextCursor!.trim().isEmpty ||
                page.nextCursor == cursor ||
                seenCursors.contains(page.nextCursor))) {
          throw MobileSyncException(
            'Synchronization returned an invalid continuation cursor for '
            '$stream. Local data was not changed for this page.',
          );
        }

        await repository.applyPullPage(page);
        pulled += page.data.length;
        cursor = page.nextCursor;
        if (cursor != null) seenCursors.add(cursor);
        pageCount += 1;

        if (!page.hasMore) {
          break;
        }
        if (pageCount == maxPagesPerStream) {
          throw MobileSyncException(
            'Synchronization is incomplete for $stream after '
            '$maxPagesPerStream pages. Run Sync Now again to continue.',
          );
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
