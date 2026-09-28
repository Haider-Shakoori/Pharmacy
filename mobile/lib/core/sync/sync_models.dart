import 'dart:convert';

class SyncOutboxEvent {
  const SyncOutboxEvent({
    required this.idempotencyKey,
    required this.aggregateId,
    required this.eventType,
    required this.payload,
  });

  factory SyncOutboxEvent.fromRow({
    required String idempotencyKey,
    required String aggregateId,
    required String eventType,
    required String payloadJson,
  }) {
    final Object? decoded = jsonDecode(payloadJson);
    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Invalid synchronization outbox payload.');
    }

    return SyncOutboxEvent(
      idempotencyKey: idempotencyKey,
      aggregateId: aggregateId,
      eventType: eventType,
      payload: decoded,
    );
  }

  final String idempotencyKey;
  final String aggregateId;
  final String eventType;
  final Map<String, dynamic> payload;

  Map<String, Object?> toApi() {
    return <String, Object?>{
      'idempotency_key': idempotencyKey,
      'event_type': eventType,
      'payload': payload,
    };
  }
}

class SyncPushResult {
  const SyncPushResult({
    required this.idempotencyKey,
    required this.status,
    this.serverId,
    this.code,
    this.message,
    this.retryable = false,
  });

  factory SyncPushResult.fromJson(Map<String, dynamic> json) {
    return SyncPushResult(
      idempotencyKey: json['idempotency_key'].toString(),
      status: json['status'].toString(),
      serverId: json['server_id']?.toString(),
      code: json['code']?.toString(),
      message: json['message']?.toString(),
      retryable: json['retryable'] == true,
    );
  }

  final String idempotencyKey;
  final String status;
  final String? serverId;
  final String? code;
  final String? message;
  final bool retryable;

  bool get accepted => status == 'accepted';
}

class SyncPullPage {
  const SyncPullPage({
    required this.stream,
    required this.data,
    required this.nextCursor,
    required this.hasMore,
  });

  factory SyncPullPage.fromJson(Map<String, dynamic> json) {
    final List<dynamic> raw = json['data'] as List<dynamic>? ?? <dynamic>[];

    return SyncPullPage(
      stream: json['stream'].toString(),
      data: raw
          .whereType<Map<dynamic, dynamic>>()
          .map(
            (Map<dynamic, dynamic> value) => Map<String, dynamic>.from(value),
          )
          .toList(growable: false),
      nextCursor: json['next_cursor']?.toString(),
      hasMore: json['has_more'] == true,
    );
  }

  final String stream;
  final List<Map<String, dynamic>> data;
  final String? nextCursor;
  final bool hasMore;
}

class SyncStatusSnapshot {
  const SyncStatusSnapshot({
    required this.pending,
    required this.rejected,
    this.lastSyncedAt,
  });

  final int pending;
  final int rejected;
  final DateTime? lastSyncedAt;
}

class SyncRunResult {
  const SyncRunResult({
    required this.pushed,
    required this.rejected,
    required this.pulled,
    required this.status,
  });

  final int pushed;
  final int rejected;
  final int pulled;
  final SyncStatusSnapshot status;
}

class MobileSyncException implements Exception {
  const MobileSyncException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}
