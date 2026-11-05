import 'package:businessos_pharmacy/core/sync/sync_models.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('synchronization wire validation', () {
    test('rejects empty and whitespace-only acknowledgement keys', () {
      for (final String key in <String>['', '  ']) {
        expect(
          () => SyncPushResult.fromJson(<String, dynamic>{
            'idempotency_key': key,
            'status': 'accepted',
          }),
          throwsFormatException,
        );
      }
    });

    test('rejects malformed acknowledgement server identifiers', () {
      for (final Object invalid in <Object>['', '  ', 0, -1, true]) {
        expect(
          () => SyncPushResult.fromJson(<String, dynamic>{
            'idempotency_key': 'event-1',
            'status': 'accepted',
            'server_id': invalid,
          }),
          throwsFormatException,
        );
      }
      expect(
        SyncPushResult.fromJson(<String, dynamic>{
          'idempotency_key': 'event-1',
          'status': 'accepted',
          'server_id': 'sale-1',
        }).serverId,
        'sale-1',
      );
      expect(
        SyncPushResult.fromJson(<String, dynamic>{
          'idempotency_key': 'event-1',
          'status': 'accepted',
          'server_id': 123,
        }).serverId,
        '123',
      );
    });

    test('rejects malformed acknowledgement retry flags', () {
      for (final Object invalid in <Object>['true', 1, <String, Object>{}]) {
        expect(
          () => SyncPushResult.fromJson(<String, dynamic>{
            'idempotency_key': 'event-1',
            'status': 'rejected',
            'retryable': invalid,
          }),
          throwsFormatException,
        );
      }
      expect(
        SyncPushResult.fromJson(<String, dynamic>{
          'idempotency_key': 'event-1',
          'status': 'rejected',
          'retryable': true,
        }).retryable,
        isTrue,
      );
    });

    test('rejects continuation pages without a usable cursor', () {
      for (final Object? cursor in <Object?>[null, '', '  ']) {
        expect(
          () => SyncPullPage.fromJson(<String, dynamic>{
            'stream': 'inventory',
            'data': <Object>[],
            'has_more': true,
            'next_cursor': cursor,
          }),
          throwsFormatException,
        );
      }
    });

    test('rejects blank cursor even on final pull pages', () {
      for (final String cursor in <String>['', '   ']) {
        expect(
          () => SyncPullPage.fromJson(<String, dynamic>{
            'stream': 'inventory',
            'data': <Object>[],
            'has_more': false,
            'next_cursor': cursor,
          }),
          throwsFormatException,
        );
      }
    });

    test('rejects blank stream names', () {
      expect(
        () => SyncPullPage.fromJson(<String, dynamic>{
          'stream': '  ',
          'data': <Object>[],
          'has_more': false,
          'next_cursor': null,
        }),
        throwsFormatException,
      );
    });

    test('accepts a valid final page with no continuation cursor', () {
      final SyncPullPage page = SyncPullPage.fromJson(<String, dynamic>{
        'stream': 'inventory',
        'data': <Object>[],
        'has_more': false,
        'next_cursor': null,
      });
      expect(page.hasMore, isFalse);
      expect(page.nextCursor, isNull);
    });

    test('rejects invalid persisted outbox identities', () {
      expect(
        () => SyncOutboxEvent.fromRow(
          idempotencyKey: 'key-1',
          aggregateId: '  ',
          eventType: 'sale.completed',
          payloadJson: '{}',
        ),
        throwsFormatException,
      );
    });
  });
}
