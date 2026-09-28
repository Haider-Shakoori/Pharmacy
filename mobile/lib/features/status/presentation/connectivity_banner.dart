import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/core/network/connectivity_monitor.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class ConnectivityBanner extends ConsumerWidget {
  const ConnectivityBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppStrings strings = AppStrings.of(context);
    final ConnectivityMonitor monitor = ref.watch(connectivityMonitorProvider);

    return StreamBuilder<NetworkLinkState>(
      stream: monitor.watch(),
      initialData: NetworkLinkState.unknown,
      builder:
          (BuildContext context, AsyncSnapshot<NetworkLinkState> snapshot) {
            final NetworkLinkState state =
                snapshot.data ?? NetworkLinkState.unknown;
            final IconData icon = switch (state) {
              NetworkLinkState.connected => Icons.wifi_rounded,
              NetworkLinkState.offline => Icons.wifi_off_rounded,
              NetworkLinkState.unknown => Icons.sync_rounded,
            };
            final String label = switch (state) {
              NetworkLinkState.connected => strings.networkAvailable,
              NetworkLinkState.offline => strings.networkUnavailable,
              NetworkLinkState.unknown => strings.networkUnknown,
            };

            return Material(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                child: Row(
                  children: <Widget>[
                    Icon(icon, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '$label · ${strings.serverNotVerified}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
    );
  }
}
