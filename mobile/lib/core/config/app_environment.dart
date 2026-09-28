class AppEnvironment {
  const AppEnvironment({
    required this.flavor,
    required this.apiBaseUri,
  });

  factory AppEnvironment.fromValues({
    String flavor = 'production',
    String apiBaseUrl = '',
  }) {
    final String normalizedFlavor =
        flavor.trim().isEmpty ? 'production' : flavor.trim();
    final String normalizedUrl = apiBaseUrl.trim();

    if (normalizedUrl.isEmpty) {
      return AppEnvironment(
        flavor: normalizedFlavor,
        apiBaseUri: null,
      );
    }

    final Uri? uri = Uri.tryParse(normalizedUrl);
    if (uri == null || !uri.hasScheme || !uri.hasAuthority) {
      throw const FormatException('API_BASE_URL must be an absolute URL.');
    }

    final bool localHttp = uri.scheme == 'http' &&
        <String>{'localhost', '127.0.0.1', '10.0.2.2'}.contains(uri.host);

    if (uri.scheme != 'https' && !localHttp) {
      throw const FormatException(
        'API_BASE_URL must use HTTPS outside local development.',
      );
    }

    return AppEnvironment(
      flavor: normalizedFlavor,
      apiBaseUri: uri,
    );
  }

  static AppEnvironment get current => AppEnvironment.fromValues(
        flavor: const String.fromEnvironment(
          'APP_FLAVOR',
          defaultValue: 'production',
        ),
        apiBaseUrl: const String.fromEnvironment('API_BASE_URL'),
      );

  final String flavor;
  final Uri? apiBaseUri;

  bool get isApiConfigured => apiBaseUri != null;
}
