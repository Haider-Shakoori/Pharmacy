import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/features/alerts/data/operational_alert_repository.dart';
import 'package:businessos_pharmacy/features/alerts/domain/operational_alert.dart';
import 'package:businessos_pharmacy/features/alerts/services/operational_notification_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class OperationalAlertsCard extends ConsumerStatefulWidget {
  const OperationalAlertsCard({super.key});

  @override
  ConsumerState<OperationalAlertsCard> createState() =>
      _OperationalAlertsCardState();
}

class _OperationalAlertsCardState extends ConsumerState<OperationalAlertsCard> {
  OperationalAlertSnapshot? _snapshot;
  OperationalAlertRepository? _repository;
  bool _loading = true;
  bool _notificationsEnabled = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_load);
  }

  Future<void> _load() async {
    try {
      final MobileRegistration? registration = await ref.read(
        mobileRegistrationProvider.future,
      );

      if (registration == null) {
        if (mounted) {
          setState(() {
            _loading = false;
          });
        }
        return;
      }

      final PharmacyDatabase database = await ref.read(
        pharmacyDatabaseProvider.future,
      );
      final OperationalAlertRepository repository = OperationalAlertRepository(
        database,
        lowStockThreshold: registration.lowStockThreshold,
        nearExpiryDays: registration.nearExpiryDays,
      );
      final OperationalAlertSnapshot snapshot = await repository.snapshot();
      final bool enabled = await repository.notificationsEnabled();

      if (enabled) {
        await OperationalNotificationService().notifyIfChanged(
          snapshot,
          repository,
        );
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _repository = repository;
        _snapshot = snapshot;
        _notificationsEnabled = enabled;
        _loading = false;
        _error = null;
      });
    } on Object catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error.toString();
      });
    }
  }

  Future<void> _enableNotifications() async {
    final OperationalAlertRepository? repository = _repository;
    final OperationalAlertSnapshot? snapshot = _snapshot;
    if (repository == null || snapshot == null) {
      return;
    }

    final OperationalNotificationService notifications =
        OperationalNotificationService();
    final bool granted = await notifications.requestPermission();
    if (!granted) {
      return;
    }

    await repository.setNotificationsEnabled(true);
    await notifications.notifyIfChanged(snapshot, repository);

    if (mounted) {
      setState(() {
        _notificationsEnabled = true;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);

    if (_loading) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(20),
          child: Center(child: CircularProgressIndicator()),
        ),
      );
    }

    if (_error != null) {
      return Card(
        child: Padding(padding: const EdgeInsets.all(20), child: Text(_error!)),
      );
    }

    final OperationalAlertSnapshot? snapshot = _snapshot;
    if (snapshot == null) {
      return const SizedBox.shrink();
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                const Icon(Icons.notifications_active_outlined),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    strings.alertsTitle,
                    style: Theme.of(context).textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  tooltip: strings.alertsRefresh,
                  onPressed: _load,
                  icon: const Icon(Icons.refresh_rounded),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              strings.alertsSummary(
                snapshot.lowStockCount,
                snapshot.nearExpiryCount,
                snapshot.expiredCount,
                snapshot.syncRejectedCount,
              ),
            ),
            if (!_notificationsEnabled) ...<Widget>[
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _enableNotifications,
                icon: const Icon(Icons.notifications_none_rounded),
                label: Text(strings.alertsEnableNotifications),
              ),
            ],
            if (snapshot.alerts.isEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Text(strings.alertsNone),
            ] else ...<Widget>[
              const SizedBox(height: 12),
              ...snapshot.alerts
                  .take(8)
                  .map(
                    (LocalOperationalAlert alert) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        alert.severity == OperationalAlertSeverity.critical
                            ? Icons.error_outline_rounded
                            : Icons.warning_amber_rounded,
                      ),
                      title: Text(alert.title),
                      subtitle: Text(alert.detail),
                    ),
                  ),
            ],
          ],
        ),
      ),
    );
  }
}
