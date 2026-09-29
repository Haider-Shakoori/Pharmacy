class DeviceIdentity {
  const DeviceIdentity({
    required this.installationId,
    required this.platform,
    required this.deviceName,
    required this.model,
    required this.osVersion,
    required this.appVersion,
    required this.buildNumber,
  });

  final String installationId;
  final String platform;
  final String deviceName;
  final String model;
  final String osVersion;
  final String appVersion;
  final String buildNumber;
}
