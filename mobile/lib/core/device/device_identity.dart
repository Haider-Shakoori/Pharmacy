class DeviceIdentity {
  const DeviceIdentity({
    required this.installationId,
    required this.deviceName,
    required this.model,
    required this.androidVersion,
    required this.appVersion,
    required this.buildNumber,
  });

  final String installationId;
  final String deviceName;
  final String model;
  final String androidVersion;
  final String appVersion;
  final String buildNumber;
}
