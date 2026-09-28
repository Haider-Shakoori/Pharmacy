import 'package:businessos_pharmacy/features/alerts/data/operational_alert_repository.dart';
import 'package:businessos_pharmacy/features/alerts/domain/operational_alert.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class OperationalNotificationService {
  OperationalNotificationService({FlutterLocalNotificationsPlugin? plugin})
    : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;
  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized) {
      return;
    }

    const InitializationSettings settings = InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
    );
    await _plugin.initialize(settings: settings);
    _initialized = true;
  }

  Future<bool> requestPermission() async {
    await initialize();
    final bool? granted = await _plugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.requestNotificationsPermission();

    return granted ?? true;
  }

  Future<void> notifyIfChanged(
    OperationalAlertSnapshot snapshot,
    OperationalAlertRepository repository,
  ) async {
    if (snapshot.total == 0 || !await repository.notificationsEnabled()) {
      return;
    }

    final String? previous = await repository.lastNotifiedFingerprint();
    if (previous == snapshot.fingerprint) {
      return;
    }

    await initialize();

    const AndroidNotificationDetails android = AndroidNotificationDetails(
      'operational_alerts',
      'Operational alerts',
      channelDescription:
          'Low stock, expiry warnings and offline synchronization conflicts.',
      importance: Importance.high,
      priority: Priority.high,
    );

    await _plugin.show(
      id: 2401,
      title: snapshot.hasCritical
          ? 'Pharmacy action required'
          : 'Pharmacy inventory alerts',
      body:
          '${snapshot.lowStockCount} low stock · '
          '${snapshot.nearExpiryCount} near expiry · '
          '${snapshot.expiredCount} expired · '
          '${snapshot.syncRejectedCount} sync conflicts',
      notificationDetails: const NotificationDetails(android: android),
      payload: 'operational-alerts',
    );

    await repository.setLastNotifiedFingerprint(snapshot.fingerprint);
  }
}
