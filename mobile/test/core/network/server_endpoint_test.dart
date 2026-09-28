import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('private LAN address can use HTTP for local mode', () {
    final ServerEndpoint endpoint = ServerEndpoint.fromInput(
      '192.168.10.5:8080',
      ServerEndpointKind.local,
    );

    expect(endpoint.uri.scheme, 'http');
    expect(endpoint.uri.host, '192.168.10.5');
  });

  test('cloud mode requires HTTPS', () {
    expect(
      () => ServerEndpoint.fromInput(
        'http://demo.pharmacy.businessos.af',
        ServerEndpointKind.cloud,
      ),
      throwsFormatException,
    );
  });

  test('public HTTP host is rejected in local mode', () {
    expect(
      () => ServerEndpoint.fromInput(
        'http://example.com',
        ServerEndpointKind.local,
      ),
      throwsFormatException,
    );
  });
}
