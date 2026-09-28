import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';

class MobileRegistrationRepository {
  const MobileRegistrationRepository(this._store);

  static const String _storageKey = 'pharmacy.mobile.registration.v1';

  final SecureStore _store;

  Future<MobileRegistration?> load() async {
    final String? raw = await _store.read(_storageKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    try {
      final Object? decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic>) {
        return null;
      }

      return MobileRegistration.fromJson(decoded);
    } on FormatException {
      return null;
    } on TypeError {
      return null;
    }
  }

  Future<void> save(MobileRegistration registration) {
    return _store.write(_storageKey, jsonEncode(registration.toJson()));
  }

  Future<void> clear() => _store.delete(_storageKey);
}
