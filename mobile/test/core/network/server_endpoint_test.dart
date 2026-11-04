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

  test('rejects URLs containing credentials or extra components', () {
    for (final address in <String>[
      'https://user:pass@demo.example.test',
      'https://demo.example.test/api',
      'https://demo.example.test/?key=value',
      'https://demo.example.test/#section',
    ]) {
      expect(
        () => ServerEndpoint.fromInput(address, ServerEndpointKind.cloud),
        throwsFormatException,
      );
    }
  });
}
