import 'package:businessos_pharmacy/core/network/connection_profile.dart';
import 'package:businessos_pharmacy/core/network/deployment_mode.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';

typedef EndpointReachability = Future<bool> Function(ServerEndpoint endpoint);

enum ConnectionRouteSource { local, cloud, offline }

class ConnectionRoute {
  const ConnectionRoute({required this.source, this.endpoint});

  final ConnectionRouteSource source;
  final ServerEndpoint? endpoint;

  bool get isOnline => endpoint != null;
}

class ConnectionRouteResolver {
  const ConnectionRouteResolver(this._isReachable);

  final EndpointReachability _isReachable;

  Future<ConnectionRoute> resolve(ConnectionProfile profile) async {
    return switch (profile.mode) {
      DeploymentMode.local => _resolveSingle(
        profile.localEndpoint,
        ConnectionRouteSource.local,
      ),
      DeploymentMode.cloud => _resolveSingle(
        profile.cloudEndpoint,
        ConnectionRouteSource.cloud,
      ),
      DeploymentMode.automatic => _resolveAutomatic(profile),
    };
  }

  Future<ConnectionRoute> _resolveAutomatic(ConnectionProfile profile) async {
    final ServerEndpoint? local = profile.localEndpoint;
    if (local != null && await _safeReachable(local)) {
      return ConnectionRoute(
        source: ConnectionRouteSource.local,
        endpoint: local,
      );
    }

    final ServerEndpoint? cloud = profile.cloudEndpoint;
    if (cloud != null && await _safeReachable(cloud)) {
      return ConnectionRoute(
        source: ConnectionRouteSource.cloud,
        endpoint: cloud,
      );
    }

    return const ConnectionRoute(source: ConnectionRouteSource.offline);
  }

  Future<bool> _safeReachable(ServerEndpoint endpoint) async {
    try {
      return await _isReachable(endpoint);
    } on Object {
      // A LAN timeout must not prevent automatic cloud fallback.
      return false;
    }
  }

  Future<ConnectionRoute> _resolveSingle(
    ServerEndpoint? endpoint,
    ConnectionRouteSource source,
  ) async {
    if (endpoint != null && await _safeReachable(endpoint)) {
      return ConnectionRoute(source: source, endpoint: endpoint);
    }

    return const ConnectionRoute(source: ConnectionRouteSource.offline);
  }
}
