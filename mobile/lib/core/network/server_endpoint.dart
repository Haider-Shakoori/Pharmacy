enum ServerEndpointKind { local, cloud }

class ServerEndpoint {
  const ServerEndpoint({required this.uri, required this.kind});

  final Uri uri;
  final ServerEndpointKind kind;

  bool get isSecure => uri.scheme == 'https';

  factory ServerEndpoint.fromUri(Uri uri, ServerEndpointKind kind) {
    return ServerEndpoint.fromInput(uri.toString(), kind);
  }

  factory ServerEndpoint.fromInput(String input, ServerEndpointKind kind) {
    final String raw = input.trim();
    if (raw.isEmpty) {
      throw const FormatException('Server address is required.');
    }

    final bool hasScheme = raw.contains('://');
    final String normalized = hasScheme
        ? raw
        : '${kind == ServerEndpointKind.cloud ? 'https' : 'http'}://$raw';
    final Uri? uri = Uri.tryParse(normalized);

    if (uri == null ||
        uri.host.isEmpty ||
        !<String>{'http', 'https'}.contains(uri.scheme)) {
      throw const FormatException(
        'Enter a valid HTTP or HTTPS server address.',
      );
    }

    if (kind == ServerEndpointKind.cloud && uri.scheme != 'https') {
      throw const FormatException('Cloud server must use HTTPS.');
    }

    if (kind == ServerEndpointKind.local &&
        uri.scheme == 'http' &&
        !_isPrivateLanHost(uri.host)) {
      throw const FormatException(
        'Unencrypted local addresses must stay on the private LAN.',
      );
    }

    return ServerEndpoint(uri: uri, kind: kind);
  }

  static bool _isPrivateLanHost(String host) {
    final String value = host.toLowerCase();

    if (value == 'localhost' ||
        value == '::1' ||
        value.endsWith('.local') ||
        (!value.contains('.') && !value.contains(':')) ||
        value.startsWith('fe80:')) {
      return true;
    }

    final List<int?> octets = value.split('.').map(int.tryParse).toList();
    if (octets.length != 4 || octets.any((int? part) => part == null)) {
      return false;
    }

    final int first = octets[0]!;
    final int second = octets[1]!;

    return first == 10 ||
        first == 127 ||
        (first == 169 && second == 254) ||
        (first == 192 && second == 168) ||
        (first == 172 && second >= 16 && second <= 31);
  }
}
