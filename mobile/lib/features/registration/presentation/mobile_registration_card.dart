import 'package:businessos_pharmacy/core/auth/local_node_bootstrap_client.dart';
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
  final TextEditingController _pharmacyCodeController = TextEditingController();
  final TextEditingController _licenseController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  bool _registering = false;
  String? _error;

  @override
  void dispose() {
    _pharmacyCodeController.dispose();
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

      await _bootstrapBundledLocalNode(
        registration,
        password: _passwordController.text,
        profileRepository: profileRepository,
        profile: profile,
      );

      await _persistRegistration(
        registration,
        profileRepository: profileRepository,
        profile: await profileRepository.load(),
      );

      _licenseController.clear();
      _passwordController.clear();

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

  Future<void> _startTrial() async {
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
      final Uri? apiBaseUri = AppEnvironment.current.apiBaseUri;
      if (apiBaseUri == null) {
        throw const MobileRegistrationException(
          'The BusinessOS licensing server is not configured.',
        );
      }
      final ServerEndpoint licensingEndpoint = ServerEndpoint.fromUri(
        apiBaseUri,
        ServerEndpointKind.cloud,
      );
      final DeviceIdentity device = await ref
          .read(deviceIdentityServiceProvider)
          .load();

      final MobileRegistration registration = await MobileRegistrationClient()
          .startTrial(
            endpoint: licensingEndpoint,
            device: device,
            pharmacyCode: _pharmacyCodeController.text,
            email: _emailController.text,
            password: _passwordController.text,
          );

      await _bootstrapBundledLocalNode(
        registration,
        password: _passwordController.text,
        profileRepository: profileRepository,
        profile: profile,
      );

      await _persistRegistration(
        registration,
        profileRepository: profileRepository,
        profile: await profileRepository.load(),
      );

      _pharmacyCodeController.clear();
      _licenseController.clear();
      _passwordController.clear();

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


  Future<void> _bootstrapBundledLocalNode(
    MobileRegistration registration, {
    required String password,
    required ConnectionProfileRepository profileRepository,
    required ConnectionProfile profile,
  }) async {
    if (!AppEnvironment.current.bundledLocalNode) {
      return;
    }

    try {
      final Uri localUri = await LocalNodeBootstrapClient().bootstrap(
        registration: registration,
        password: password,
      );
      final ServerEndpoint local = ServerEndpoint.fromUri(
        localUri,
        ServerEndpointKind.local,
      );

      await profileRepository.save(
        ConnectionProfile(
          mode: DeploymentMode.automatic,
          localEndpoint: local,
          cloudEndpoint: profile.cloudEndpoint,
        ),
      );
    } on LocalNodeBootstrapException catch (error) {
      throw MobileRegistrationException(error.message);
    }
  }

  Future<void> _persistRegistration(
    MobileRegistration registration, {
    required ConnectionProfileRepository profileRepository,
    required ConnectionProfile profile,
  }) async {
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

    ref.invalidate(mobileRegistrationProvider);
    ref.invalidate(localDatabaseScopeProvider);
    ref.invalidate(pharmacyDatabaseProvider);
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
                  Text('${current.userName} • ${current.userEmail}'),
                  const SizedBox(height: 8),
                  Text(
                    'Access expires: ${current.accessExpiresAt.toLocal()}',
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
                Text(
                  'First time here? Start a one-time 7-day trial for an already provisioned BusinessOS pharmacy.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _pharmacyCodeController,
                  autocorrect: false,
                  enableSuggestions: false,
                  decoration: const InputDecoration(
                    labelText: 'Pharmacy code',
                    hintText: 'e.g. kabul-central',
                  ),
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
                    if (_registering) {
                      return;
                    }
                    if (_pharmacyCodeController.text.trim().isNotEmpty &&
                        _licenseController.text.trim().isEmpty) {
                      _startTrial();
                    } else {
                      _register();
                    }
                  },
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _registering ? null : _startTrial,
                  icon: const Icon(Icons.schedule_rounded),
                  label: Text(
                    _registering ? strings.registering : 'Start 7-Day Trial',
                  ),
                ),
                const SizedBox(height: 20),
                const Divider(),
                const SizedBox(height: 12),
                Text(
                  'Already licensed? Register this device with your license key.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _licenseController,
                  autocorrect: false,
                  enableSuggestions: false,
                  decoration: InputDecoration(labelText: strings.licenseKey),
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