import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

enum NetworkLinkState { unknown, offline, connected }

abstract interface class ConnectivityMonitor {
  Future<NetworkLinkState> current();

  Stream<NetworkLinkState> watch();
}

class ConnectivityPlusMonitor implements ConnectivityMonitor {
  ConnectivityPlusMonitor({Connectivity? connectivity})
    : _connectivity = connectivity ?? Connectivity();

  final Connectivity _connectivity;

  @override
  Future<NetworkLinkState> current() async {
    return _map(await _connectivity.checkConnectivity());
  }

  @override
  Stream<NetworkLinkState> watch() async* {
    yield await current();
    yield* _connectivity.onConnectivityChanged.map(_map).distinct();
  }

  NetworkLinkState _map(List<ConnectivityResult> results) {
    if (results.isEmpty ||
        results.every(
          (ConnectivityResult result) => result == ConnectivityResult.none,
        )) {
      return NetworkLinkState.offline;
    }

    return NetworkLinkState.connected;
  }
}

final Provider<ConnectivityMonitor> connectivityMonitorProvider =
    Provider<ConnectivityMonitor>((Ref ref) => ConnectivityPlusMonitor());
