import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_verifier.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:drift/drift.dart';

class OfflineLeaseAuthorizer {
  OfflineLeaseAuthorizer({
    required this.database,
    required this.registrationRepository,
    SecureStore? secureStore,
    OfflineLeaseVerifier? verifier,
    DateTime Function()? clock,
  }) : secureStore = secureStore ?? EncryptedSecureStore(),
       verifier = verifier ?? OfflineLeaseVerifier(),
       clock = clock ?? DateTime.now;

  static const Duration clockSkewTolerance = Duration(minutes: 5);
  static const String _lastSeenPrefix = 'offline_lease.max_seen_epoch.v2';

  final PharmacyDatabase database;
  final MobileRegistrationRepository registrationRepository;
  final SecureStore secureStore;
  final OfflineLeaseVerifier verifier;
  final DateTime Function() clock;

  Future<void> assertCanTransact() async {
    final MobileRegistration? registration = await registrationRepository
        .load();

    if (registration == null) {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.missing,
        'Register this device before creating new sales.',
      );
    }

    final OfflineLeaseClaims claims = await verifier.verify(registration);
    final DateTime now = clock().toUtc();
    final int nowEpoch = now.millisecondsSinceEpoch ~/ 1000;
    final int issuedEpoch = claims.issuedAt.millisecondsSinceEpoch ~/ 1000;
    final int expiresEpoch = claims.expiresAt.millisecondsSinceEpoch ~/ 1000;
    final int tolerance = clockSkewTolerance.inSeconds;
    final String anchorKey = _anchorKey(registration);
    final int? databaseEpoch = await _databaseLastSeenEpoch(anchorKey);
    final int? secureEpoch = await _secureLastSeenEpoch(anchorKey);
    final int? lastSeenEpoch = _greatest(databaseEpoch, secureEpoch);

    if (nowEpoch + tolerance < issuedEpoch ||
        (lastSeenEpoch != null && nowEpoch + tolerance < lastSeenEpoch)) {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.clockRollback,
        'The device clock moved backwards. Correct the clock and connect to the pharmacy server to renew the offline lease.',
      );
    }

    if (nowEpoch >= expiresEpoch) {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.expired,
        'The offline license lease has expired. Synchronize while online to renew it.',
      );
    }

    int observedEpoch = nowEpoch > issuedEpoch ? nowEpoch : issuedEpoch;
    if (lastSeenEpoch != null && lastSeenEpoch > observedEpoch) {
      observedEpoch = lastSeenEpoch;
    }

    await _storeLastSeenEpoch(anchorKey, observedEpoch);
  }

  String _anchorKey(MobileRegistration registration) {
    return '$_lastSeenPrefix.${registration.tenantId}.${registration.deviceId ?? 'unknown'}';
  }

  int? _greatest(int? first, int? second) {
    if (first == null) {
      return second;
    }
    if (second == null) {
      return first;
    }
    return first > second ? first : second;
  }

  Future<int?> _databaseLastSeenEpoch(String key) async {
    final QueryRow? row = await database
        .customSelect(
          'SELECT value FROM app_metadata WHERE key = ? LIMIT 1',
          variables: <Variable<Object>>[Variable<String>(key)],
        )
        .getSingleOrNull();

    return int.tryParse(row?.read<String>('value') ?? '');
  }

  Future<int?> _secureLastSeenEpoch(String key) async {
    return int.tryParse(await secureStore.read(key) ?? '');
  }

  Future<void> _storeLastSeenEpoch(String key, int epoch) async {
    await secureStore.write(key, epoch.toString());
    await database.customStatement(
      '''
      INSERT INTO app_metadata (key, value, updated_at)
      VALUES (?, ?, ?)
      ON CONFLICT(key) DO UPDATE SET
        value = excluded.value,
        updated_at = excluded.updated_at
      ''',
      <Object?>[key, epoch.toString(), epoch],
    );
  }
}
