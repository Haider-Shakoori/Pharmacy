import 'dart:convert';

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:cryptography/cryptography.dart';

enum OfflineLeaseFailure { missing, invalid, expired, clockRollback }

class OfflineLeaseException implements Exception {
  const OfflineLeaseException(this.code, this.message);

  final OfflineLeaseFailure code;
  final String message;

  @override
  String toString() => message;
}

class OfflineLeaseClaims {
  const OfflineLeaseClaims({
    required this.issuedAt,
    required this.expiresAt,
  });

  final DateTime issuedAt;
  final DateTime expiresAt;
}

class OfflineLeaseVerifier {
  OfflineLeaseVerifier({SignatureAlgorithm? algorithm})
    : _algorithm = algorithm ?? Ed25519();

  final SignatureAlgorithm _algorithm;

  Future<OfflineLeaseClaims> verify(MobileRegistration registration) async {
    final String? publicKeyText = registration.leasePublicKey;
    final String? deviceId = registration.deviceId;

    if (publicKeyText == null ||
        publicKeyText.isEmpty ||
        deviceId == null ||
        deviceId.isEmpty ||
        registration.leaseToken.isEmpty) {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.missing,
        'A refreshed offline license lease is required.',
      );
    }

    final List<String> parts = registration.leaseToken.split('.');
    if (parts.length != 3 || parts.first != 'v1') {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.invalid,
        'The offline license lease format is invalid.',
      );
    }

    try {
      final List<int> payloadBytes = _base64UrlDecode(parts[1]);
      final List<int> signatureBytes = _base64UrlDecode(parts[2]);
      final List<int> publicKeyBytes = base64Decode(publicKeyText);
      final String payloadText = utf8.decode(payloadBytes);
      final Object? decoded = jsonDecode(payloadText);

      if (decoded is! Map<String, dynamic>) {
        throw const FormatException('Invalid lease payload.');
      }

      final SimplePublicKey publicKey = SimplePublicKey(
        publicKeyBytes,
        type: KeyPairType.ed25519,
      );
      final Signature signature = Signature(
        signatureBytes,
        publicKey: publicKey,
      );
      final bool valid = await _algorithm.verify(
        payloadBytes,
        signature: signature,
      );

      if (!valid) {
        throw const OfflineLeaseException(
          OfflineLeaseFailure.invalid,
          'The offline license lease signature is invalid.',
        );
      }

      if (decoded['v'] != 1 ||
          decoded['tenant_id']?.toString() != registration.tenantId ||
          decoded['activation_id']?.toString() != registration.activationId ||
          decoded['device_id']?.toString() != deviceId) {
        throw const OfflineLeaseException(
          OfflineLeaseFailure.invalid,
          'The offline license lease does not belong to this device.',
        );
      }

      final String status = decoded['subscription_status']?.toString() ?? '';
      if (status != 'active' && status != 'trial') {
        throw const OfflineLeaseException(
          OfflineLeaseFailure.invalid,
          'The offline license lease is not operational.',
        );
      }

      final int issuedAt = _integer(decoded['issued_at']);
      final int expiresAt = _integer(decoded['expires_at']);
      if (issuedAt <= 0 || expiresAt <= issuedAt) {
        throw const OfflineLeaseException(
          OfflineLeaseFailure.invalid,
          'The offline license lease timestamps are invalid.',
        );
      }

      return OfflineLeaseClaims(
        issuedAt: DateTime.fromMillisecondsSinceEpoch(
          issuedAt * 1000,
          isUtc: true,
        ),
        expiresAt: DateTime.fromMillisecondsSinceEpoch(
          expiresAt * 1000,
          isUtc: true,
        ),
      );
    } on OfflineLeaseException {
      rethrow;
    } on Object {
      throw const OfflineLeaseException(
        OfflineLeaseFailure.invalid,
        'The offline license lease could not be verified.',
      );
    }
  }

  int _integer(Object? value) {
    if (value is int) {
      return value;
    }
    return int.tryParse(value?.toString() ?? '') ?? -1;
  }

  List<int> _base64UrlDecode(String value) {
    final String normalized = base64Url.normalize(value);
    return base64Url.decode(normalized);
  }
}
