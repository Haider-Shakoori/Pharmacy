import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_client.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/core/config/app_environment.dart';
import 'package:businessos_pharmacy/core/database/database_providers.dart';
import 'package:businessos_pharmacy/core/device/device_identity.dart';
import 'package:businessos_pharmacy/core/device/device_identity_service.dart';
import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/core/network/connection_profile.dart';
import 'package:businessos_pharmacy/core/network/connection_profile_repository.dart';
import 'package:businessos_pharmacy/core/network/deployment_mode.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class MobileRegistrationCard extends ConsumerStatefulWidget {
  const MobileRegistrationCard({super.key});

  @override
  ConsumerState<MobileRegistrationCard> createState() =>
      _MobileRegistrationCardState();
}

class _MobileRegistrationCardState
    extends ConsumerState<MobileRegistrationCard> {
  final TextEditingController _licenseController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  bool _registering = false;
  String? _error;

  @override
  void dispose() {
    _licenseController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _register() async {
    final AppStrings strings = AppStrings.of(context);

    setState(() {
      _registering = true;
      _error = null;
    });

    try {
      final SecureStore store = ref.read(secureStoreProvider);
      final ConnectionProfileRepository profileRepository =
          ConnectionProfileRepository(
            store,
            defaultCloudUri: AppEnvironment.current.apiBaseUri,
          );
      final ConnectionProfile profile = await profileRepository.load();
      final List<ServerEndpoint> candidates = _candidates(profile);

      if (candidates.isEmpty) {
        throw MobileRegistrationException(strings.noServerConfigured);
      }

      final DeviceIdentity device = await ref
          .read(deviceIdentityServiceProvider)
          .load();
      final MobileRegistrationClient client = MobileRegistrationClient();

      MobileRegistration? registration;
      MobileRegistrationException? lastError;

      for (final ServerEndpoint endpoint in candidates) {
        try {
          registration = await client.register(
            endpoint: endpoint,
            device: device,
            licenseKey: _licenseController.text,
            email: _emailController.text,
            password: _passwordController.text,
          );
          break;
        } on MobileRegistrationException catch (error) {
          lastError = error;
          if (!error.retryable || profile.mode != DeploymentMode.automatic) {
            rethrow;
          }
        }
      }

      if (registration == null) {
        throw lastError ??
            MobileRegistrationException(strings.noServerConfigured);
      }

      await ref.read(mobileRegistrationRepositoryProvider).save(registration);

      final String? cloudBaseUrl = registration.cloudBaseUrl;
      if (cloudBaseUrl != null && cloudBaseUrl.isNotEmpty) {
        final ServerEndpoint cloud = ServerEndpoint.fromInput(
          cloudBaseUrl,
          ServerEndpointKind.cloud,
        );
        await profileRepository.save(
          ConnectionProfile(
            mode: profile.mode,
            localEndpoint: profile.localEndpoint,
            cloudEndpoint: cloud,
          ),
        );
      }

      _licenseController.clear();
      _passwordController.clear();

      ref.invalidate(mobileRegistrationProvider);
      ref.invalidate(localDatabaseScopeProvider);
      ref.invalidate(pharmacyDatabaseProvider);

      if (!mounted) {
        return;
      }

      setState(() {
        _registering = false;
      });
    } on MobileRegistrationException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _registering = false;
        _error = error.message;
      });
    } on FormatException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _registering = false;
        _error = error.message.toString();
      });
    }
  }

  Future<void> _unregister() async {
    await ref.read(mobileRegistrationRepositoryProvider).clear();
    ref.invalidate(mobileRegistrationProvider);
    ref.invalidate(localDatabaseScopeProvider);
    ref.invalidate(pharmacyDatabaseProvider);

    if (!mounted) {
      return;
    }

    setState(() {
      _error = null;
    });
  }

  List<ServerEndpoint> _candidates(ConnectionProfile profile) {
    return switch (profile.mode) {
      DeploymentMode.local => <ServerEndpoint>[
        if (profile.localEndpoint != null) profile.localEndpoint!,
      ],
      DeploymentMode.cloud => <ServerEndpoint>[
        if (profile.cloudEndpoint != null) profile.cloudEndpoint!,
      ],
      DeploymentMode.automatic => <ServerEndpoint>[
        if (profile.localEndpoint != null) profile.localEndpoint!,
        if (profile.cloudEndpoint != null) profile.cloudEndpoint!,
      ],
    };
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);
    final AsyncValue<MobileRegistration?> registration = ref.watch(
      mobileRegistrationProvider,
    );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: registration.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (Object error, StackTrace stackTrace) =>
              Text(error.toString()),
          data: (MobileRegistration? current) {
            if (current != null) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    strings.registeredDevice,
                    style: Theme.of(context).textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Text(current.tenantName),
                  Text(current.userName + ' • ' + current.userEmail),
                  const SizedBox(height: 8),
                  Text(
                    'Access expires: ' +
                        current.accessExpiresAt.toLocal().toString(),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 16),
                  OutlinedButton.icon(
                    onPressed: _unregister,
                    icon: const Icon(Icons.swap_horiz_rounded),
                    label: Text(strings.unregisterDevice),
                  ),
                ],
              );
            }

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  strings.registrationTitle,
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text(strings.registrationBody),
                const SizedBox(height: 8),
                Text(
                  strings.registrationRequired,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _licenseController,
                  autocorrect: false,
                  enableSuggestions: false,
                  decoration: InputDecoration(labelText: strings.licenseKey),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: InputDecoration(labelText: strings.email),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _passwordController,
                  obscureText: true,
                  enableSuggestions: false,
                  autocorrect: false,
                  decoration: InputDecoration(labelText: strings.password),
                  onSubmitted: (_) {
                    if (!_registering) {
                      _register();
                    }
                  },
                ),
                if (_error != null) ...<Widget>[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _registering ? null : _register,
                  icon: const Icon(Icons.verified_user_outlined),
                  label: Text(
                    _registering ? strings.registering : strings.registerDevice,
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
