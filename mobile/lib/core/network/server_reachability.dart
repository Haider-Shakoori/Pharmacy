import 'dart:async';

import 'package:http/http.dart' as http;

enum ServerReachability { notConfigured, reachable, unreachable }

class ServerReachabilityProbe {
  ServerReachabilityProbe(
    this._client,
    this._apiBaseUri, {
    this.timeout = const Duration(seconds: 5),
  });

  final http.Client _client;
  final Uri? _apiBaseUri;
  final Duration timeout;

  Future<ServerReachability> check() async {
    final Uri? base = _apiBaseUri;
    if (base == null) {
      return ServerReachability.notConfigured;
    }

    try {
      final http.Response response = await _client
          .get(base.resolve('/up'))
          .timeout(timeout);

      if (response.statusCode >= 200 && response.statusCode < 500) {
        return ServerReachability.reachable;
      }

      return ServerReachability.unreachable;
    } on Object {
      return ServerReachability.unreachable;
    }
  }
}
