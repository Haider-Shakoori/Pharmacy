import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/features/finance/data/mobile_finance_api.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;

class FinancePanel extends ConsumerStatefulWidget {
  const FinancePanel({super.key});

  @override
  ConsumerState<FinancePanel> createState() => _FinancePanelState();
}

class _FinancePanelState extends ConsumerState<FinancePanel> {
  Future<MobileFinanceSnapshot>? _snapshot;

  void _load() {
    final registration = ref.read(mobileRegistrationProvider).asData?.value;
    if (registration == null) return;
    final client = http.Client();
    setState(() {
      _snapshot = MobileFinanceApi(
        environment: AppEnvironment.current,
        client: client,
      ).load(registration).whenComplete(client.close);
    });
  }

  Widget _section(String title, List<FinanceEntry> entries) {
    return ExpansionTile(
      title: Text(title),
      subtitle: Text('${entries.length} recent records'),
      children: entries.isEmpty
          ? <Widget>[const ListTile(title: Text('No records found'))]
          : entries
                .map(
                  (entry) => ListTile(
                    title: Text(entry.title),
                    subtitle: Text(entry.detail),
                    trailing: Text('${entry.amount} ${entry.currency}'),
                  ),
                )
                .toList(growable: false),
    );
  }

  @override
  Widget build(BuildContext context) {
    final registration = ref.watch(mobileRegistrationProvider).asData?.value;
    if (registration == null ||
        !registration.permissions.contains('accounting.manage')) {
      return const ListTile(
        leading: Icon(Icons.lock_outline),
        title: Text('Finance'),
        subtitle: Text('Accounting management permission is required.'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text('Finance', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        const Text(
          'Read-only recent financial activity. No payments or expenses are posted here.',
        ),
        OutlinedButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh),
          label: const Text('Load financial activity'),
        ),
        if (_snapshot != null)
          FutureBuilder<MobileFinanceSnapshot>(
            future: _snapshot,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return Text('Unable to load finance: ${snapshot.error}');
              }
              final data = snapshot.data!;
              return Column(
                children: <Widget>[
                  _section('Expenses', data.expenses),
                  _section('Supplier payments', data.supplierPayments),
                  _section('Cash movements', data.cashMovements),
                ],
              );
            },
          ),
      ],
    );
  }
}
