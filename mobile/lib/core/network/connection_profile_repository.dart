import 'dart:convert';

import 'package:businessos_pharmacy/core/network/connection_profile.dart';
import 'package:businessos_pharmacy/core/network/deployment_mode.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';

class ConnectionProfileRepository {
  ConnectionProfileRepository(this._store, {this.defaultCloudUri});

  static const String _storageKey = 'pharmacy.connection.profile';

  final SecureStore _store;
  final Uri? defaultCloudUri;

  Future<ConnectionProfile> load() async {
    final String? raw = await _store.read(_storageKey);
    if (raw == null || raw.isEmpty) {
      return _defaultProfile();
    }

    try {
      final Object? decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic>) {
        return _defaultProfile();
      }

      return ConnectionProfile.fromJson(decoded);
    } on FormatException {
      return _defaultProfile();
    } on TypeError {
      return _defaultProfile();
    }
  }

  Future<void> save(ConnectionProfile profile) {
    return _store.write(_storageKey, jsonEncode(profile.toJson()));
  }

  ConnectionProfile _defaultProfile() {
    final Uri? cloudUri = defaultCloudUri;
    if (cloudUri == null) {
      return ConnectionProfile.empty;
    }

    try {
      return ConnectionProfile(
        mode: DeploymentMode.automatic,
        cloudEndpoint: ServerEndpoint.fromUri(
          cloudUri,
          ServerEndpointKind.cloud,
        ),
      );
    } on FormatException {
      return ConnectionProfile.empty;
    }
  }
}
