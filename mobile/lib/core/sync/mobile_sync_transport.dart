import 'dart:async';
import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:businessos_pharmacy/core/sync/sync_models.dart';
import 'package:http/http.dart' as http;

abstract interface class MobileSyncTransport {
  Future<MobileRegistration> refreshSession({
    required ServerEndpoint endpoint,
    required MobileRegistration registration,
  });

  Future<List<SyncPushResult>> push({
    required ServerEndpoint endpoint,
    required MobileRegistration registration,
    required List<SyncOutboxEvent> events,
  });

  Future<SyncPullPage> pull({
    required ServerEndpoint endpoint,
    required MobileRegistration registration,
    required String stream,
    required String? cursor,
    int limit = 100,
  });
}

class HttpMobileSyncTransport implements MobileSyncTransport {
  HttpMobileSyncTransport(
    this._client, {
    this.timeout = const Duration(seconds: 20),
  });

  final http.Client _client;
  final Duration timeout;

  @override
  Future<MobileRegistration> refreshSession({
    required ServerEndpoint endpoint,
    required MobileRegistration registration,
  }) async {
    final Map<String, dynamic> data = await _request(
      method: 'POST',
      uri: endpoint.uri.resolve('/api/v1/mobile/session/refresh'),
      registration: registration,
    );

    return registration.withRefreshedSession(data);
  }

  @override
  Future<List<SyncPushResult>> push({
    required ServerEndpoint endpoint,
    required MobileRegistration registration,
    required List<SyncOutboxEvent> events,
  }) async {
    final Map<String, dynamic> data = await _request(
      method: 'POST',
      uri: endpoint.uri.resolve('/api/v1/mobile/sync/push'),
      registration: registration,
      body: <String, Object?>{
        'events': events
            .map((SyncOutboxEvent event) => event.toApi())
            .toList(growable: false),
      },
    );

    final List<dynamic> raw = data['results'] as List<dynamic>? ?? <dynamic>[];

    return raw
        .whereType<Map<dynamic, dynamic>>()
        .map(
          (Map<dynamic, dynamic> value) =>
              SyncPushResult.fromJson(Map<String, dynamic>.from(value)),
        )
        .toList(growable: false);
  }

  @override
  Future<SyncPullPage> pull({
    required ServerEndpoint endpoint,
    required MobileRegistration registration,
    required String stream,
    required String? cursor,
    int limit = 100,
  }) async {
    final Uri uri = endpoint.uri
        .resolve('/api/v1/mobile/sync/pull/$stream')
        .replace(
          queryParameters: <String, String>{
            'limit': limit.toString(),
            if (cursor != null && cursor.isNotEmpty) 'cursor': cursor,
          },
        );

    final Map<String, dynamic> data = await _request(
      method: 'GET',
      uri: uri,
      registration: registration,
    );

    return SyncPullPage.fromJson(data);
  }

  Future<Map<String, dynamic>> _request({
    required String method,
    required Uri uri,
    required MobileRegistration registration,
    Map<String, Object?>? body,
  }) async {
    try {
      final Map<String, String> headers = <String, String>{
        'Accept': 'application/json',
        'Authorization': 'Bearer ${registration.accessToken}',
        if (body != null) 'Content-Type': 'application/json',
      };

      final http.Response response = switch (method) {
        'POST' =>
          await _client
              .post(
                uri,
                headers: headers,
                body: body == null ? null : jsonEncode(body),
              )
              .timeout(timeout),
        'GET' => await _client.get(uri, headers: headers).timeout(timeout),
        _ => throw const MobileSyncException(
          'Unsupported synchronization request.',
        ),
      };

      final Object? decoded = response.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(response.body);
      final Map<String, dynamic> payload = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{};

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final Object? data = payload['data'];
        if (data is Map<String, dynamic>) {
          return data;
        }
        if (data is Map) {
          return Map<String, dynamic>.from(data);
        }

        throw const MobileSyncException(
          'The pharmacy server returned an invalid synchronization response.',
        );
      }

      throw MobileSyncException(
        _errorMessage(payload) ??
            'Synchronization failed with status ${response.statusCode}.',
        statusCode: response.statusCode,
      );
    } on MobileSyncException {
      rethrow;
    } on TimeoutException {
      throw const MobileSyncException(
        'The pharmacy server did not respond in time.',
      );
    } on FormatException {
      throw const MobileSyncException(
        'The pharmacy server returned an invalid response.',
      );
    } on Object {
      throw const MobileSyncException(
        'The pharmacy server could not be reached.',
      );
    }
  }

  String? _errorMessage(Map<String, dynamic> payload) {
    final Object? errors = payload['errors'];
    if (errors is Map) {
      for (final Object? value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
      }
    }

    return payload['message']?.toString();
  }
}
