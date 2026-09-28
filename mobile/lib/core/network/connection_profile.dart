import 'package:businessos_pharmacy/core/network/deployment_mode.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';

class ConnectionProfile {
  const ConnectionProfile({
    required this.mode,
    this.localEndpoint,
    this.cloudEndpoint,
  });

  factory ConnectionProfile.fromJson(Map<String, dynamic> json) {
    final String? localUrl = json['local_url'] as String?;
    final String? cloudUrl = json['cloud_url'] as String?;

    return ConnectionProfile(
      mode: DeploymentMode.fromStorage(json['mode'] as String?),
      localEndpoint: localUrl == null || localUrl.isEmpty
          ? null
          : ServerEndpoint.fromInput(localUrl, ServerEndpointKind.local),
      cloudEndpoint: cloudUrl == null || cloudUrl.isEmpty
          ? null
          : ServerEndpoint.fromInput(cloudUrl, ServerEndpointKind.cloud),
    );
  }

  final DeploymentMode mode;
  final ServerEndpoint? localEndpoint;
  final ServerEndpoint? cloudEndpoint;

  static const ConnectionProfile empty = ConnectionProfile(
    mode: DeploymentMode.automatic,
  );

  Map<String, Object?> toJson() {
    return <String, Object?>{
      'mode': mode.storageValue,
      'local_url': localEndpoint?.uri.toString(),
      'cloud_url': cloudEndpoint?.uri.toString(),
    };
  }
}
