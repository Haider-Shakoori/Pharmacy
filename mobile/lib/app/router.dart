import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/features/registration/presentation/mobile_registration_card.dart';
import 'package:businessos_pharmacy/features/shell/presentation/app_shell.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

GoRouter createAppRouter(ValueNotifier<AppLocale> localeNotifier) {
  return GoRouter(
    initialLocation: '/',
    routes: <RouteBase>[
      GoRoute(
        path: '/',
        builder: (context, state) =>
            _RegistrationGate(localeNotifier: localeNotifier),
      ),
    ],
  );
}

class _RegistrationGate extends ConsumerWidget {
  const _RegistrationGate({required this.localeNotifier});

  final ValueNotifier<AppLocale> localeNotifier;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final registration = ref.watch(mobileRegistrationProvider);
    return registration.when(
      loading: () =>
          const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (error, stack) => Scaffold(
        body: Center(child: Text('Unable to load device registration.')),
      ),
      data: (current) {
        if (current != null) {
          return AppShell(localeNotifier: localeNotifier);
        }
        return Scaffold(
          body: SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: EdgeInsets.all(16),
                child: ConstrainedBox(
                  constraints: BoxConstraints(maxWidth: 480),
                  child: MobileRegistrationCard(),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
