import 'package:businessos_pharmacy/core/network/connection_profile.dart';
import 'package:businessos_pharmacy/core/network/connection_route_resolver.dart';
import 'package:businessos_pharmacy/core/network/deployment_mode.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final ServerEndpoint local = ServerEndpoint.fromInput(
    '192.168.1.20:8000',
    ServerEndpointKind.local,
  );
  final ServerEndpoint cloud = ServerEndpoint.fromInput(
    'https://demo.pharmacy.businessos.af',
    ServerEndpointKind.cloud,
  );

  test('automatic prefers the reachable local pharmacy server', () async {
    final ConnectionRouteResolver resolver = ConnectionRouteResolver(
      (ServerEndpoint endpoint) async => true,
    );

    final ConnectionRoute route = await resolver.resolve(
      ConnectionProfile(
        mode: DeploymentMode.automatic,
        localEndpoint: local,
        cloudEndpoint: cloud,
      ),
    );

    expect(route.source, ConnectionRouteSource.local);
    expect(route.endpoint, same(local));
  });

  test('automatic falls back to cloud when local is unavailable', () async {
    final ConnectionRouteResolver resolver = ConnectionRouteResolver(
      (ServerEndpoint endpoint) async =>
          endpoint.kind == ServerEndpointKind.cloud,
    );

    final ConnectionRoute route = await resolver.resolve(
      ConnectionProfile(
        mode: DeploymentMode.automatic,
        localEndpoint: local,
        cloudEndpoint: cloud,
      ),
    );

    expect(route.source, ConnectionRouteSource.cloud);
    expect(route.endpoint, same(cloud));
  });

  test('automatic becomes offline when neither endpoint is reachable', () async {
    final ConnectionRouteResolver resolver = ConnectionRouteResolver(
      (ServerEndpoint endpoint) async => false,
    );

    final ConnectionRoute route = await resolver.resolve(
      ConnectionProfile(
        mode: DeploymentMode.automatic,
        localEndpoint: local,
        cloudEndpoint: cloud,
      ),
    );

    expect(route.source, ConnectionRouteSource.offline);
    expect(route.isOnline, isFalse);
  });

  test('local and cloud modes never cross-fallback', () async {
    final ConnectionRouteResolver resolver = ConnectionRouteResolver(
      (ServerEndpoint endpoint) async =>
          endpoint.kind == ServerEndpointKind.cloud,
    );

    final ConnectionRoute route = await resolver.resolve(
      ConnectionProfile(
        mode: DeploymentMode.local,
        localEndpoint: local,
        cloudEndpoint: cloud,
      ),
    );

    expect(route.source, ConnectionRouteSource.offline);
  });
}
