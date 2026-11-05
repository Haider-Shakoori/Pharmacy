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
