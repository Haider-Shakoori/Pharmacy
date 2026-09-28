import 'dart:io';

import 'package:businessos_pharmacy/core/device/device_identity.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:uuid/uuid.dart';

class DeviceIdentityService {
  DeviceIdentityService(
    this._secureStore, {
    DeviceInfoPlugin? deviceInfo,
    Uuid? uuid,
  }) : _deviceInfo = deviceInfo ?? DeviceInfoPlugin(),
       _uuid = uuid ?? Uuid();

  static const String _installationIdKey = 'installation_id';

  final SecureStore _secureStore;
  final DeviceInfoPlugin _deviceInfo;
  final Uuid _uuid;

  Future<DeviceIdentity> load() async {
    String? installationId = await _secureStore.read(_installationIdKey);
    installationId ??= _uuid.v4();

    await _secureStore.write(_installationIdKey, installationId);

    final PackageInfo package = await PackageInfo.fromPlatform();

    if (!Platform.isAndroid) {
      return DeviceIdentity(
        installationId: installationId,
        deviceName: Platform.operatingSystem,
        model: Platform.operatingSystem,
        androidVersion: 'n/a',
        appVersion: package.version,
        buildNumber: package.buildNumber,
      );
    }

    final AndroidDeviceInfo android = await _deviceInfo.androidInfo;

    return DeviceIdentity(
      installationId: installationId,
      deviceName: android.device,
      model: '${android.manufacturer} ${android.model}'.trim(),
      androidVersion: android.version.release,
      appVersion: package.version,
      buildNumber: package.buildNumber,
    );
  }
}

final Provider<SecureStore> secureStoreProvider = Provider<SecureStore>(
  (Ref ref) => EncryptedSecureStore(),
);

final Provider<DeviceIdentityService> deviceIdentityServiceProvider =
    Provider<DeviceIdentityService>(
      (Ref ref) => DeviceIdentityService(ref.watch(secureStoreProvider)),
    );
