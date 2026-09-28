enum DeploymentMode {
  local,
  cloud,
  automatic;

  String get storageValue => name;

  static DeploymentMode fromStorage(String? value) {
    return DeploymentMode.values.firstWhere(
      (DeploymentMode mode) => mode.storageValue == value,
      orElse: () => DeploymentMode.automatic,
    );
  }
}
