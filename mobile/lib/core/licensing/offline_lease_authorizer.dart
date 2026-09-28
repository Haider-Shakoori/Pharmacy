import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/licensing/offline_lease_verifier.dart';
import 'package:drift/drift.dart';

class OfflineLeaseAuthorizer {
  OfflineLeaseAuthorizer({
    required this.database,
    required this.registrationRepository,
    OfflineLeaseVerifier? verifier,
    DateTime Function()? clock,
  }) : verifier = verifier ?? OfflineLeaseVerifier(),
       clock = clock ?? DateTime.now;

  static const Duration clockSkewTolerance = Duration(minutes: 5);
  static const String _lastSeenKey = 'offline_lease.max_seen_epoch';

  final PharmacyDatabase database;
  final MobileRegistrationRepository registrationRepository;
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
    final int? lastSeenEpoch = await _lastSeenEpoch();

    if (nowEpoch + tolerance < issuedEpoch ||
        (lastSeenEpoch != null && nowEpoch + tolerance < lastSeenEpoch)) {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.clockRollback,
        'The device clock moved backwards. Connect to the pharmacy server to renew the offline lease.',
      );
    }

    if (nowEpoch >= expiresEpoch) {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.expired,
        'The offline license lease has expired. Synchronize while online to renew it.',
      );
    }

    final int observedEpoch = lastSeenEpoch == null
        ? nowEpoch
        : (nowEpoch > lastSeenEpoch ? nowEpoch : lastSeenEpoch);
    await _storeLastSeenEpoch(observedEpoch);
  }

  Future<int?> _lastSeenEpoch() async {
    final QueryRow? row = await database
        .customSelect(
          'SELECT value FROM app_metadata WHERE key = ? LIMIT 1',
          variables: <Variable<Object>>[
            const Variable<String>(_lastSeenKey),
          ],
        )
        .getSingleOrNull();

    return int.tryParse(row?.read<String>('value') ?? '');
  }

  Future<void> _storeLastSeenEpoch(int epoch) {
    return database.customStatement(
      '''
      INSERT INTO app_metadata (key, value, updated_at)
      VALUES (?, ?, ?)
      ON CONFLICT(key) DO UPDATE SET
        value = excluded.value,
        updated_at = excluded.updated_at
      ''',
      <Object?>[_lastSeenKey, epoch.toString(), epoch],
    );
  }
}
