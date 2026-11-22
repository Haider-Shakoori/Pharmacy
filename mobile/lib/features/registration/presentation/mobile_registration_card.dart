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
  bool _credentialsStep = false;
  bool _keyEntered = false;
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

      try {
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
      } finally {
        client.close();
      }

      if (registration == null) {
        throw lastError ??
            MobileRegistrationException(strings.noServerConfigured);
      }

      await _persistRegistration(
        registration,
        profileRepository: profileRepository,
        profile: profile,
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
    } on Object {
      if (!mounted) {
        return;
      }
      setState(() {
        _registering = false;
        _error = 'Unable to save registration. Please try again.';
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

      final MobileRegistrationClient client = MobileRegistrationClient();
      late final MobileRegistration registration;
      try {
        registration = await client.startTrial(
          endpoint: licensingEndpoint,
          device: device,
          pharmacyCode: _pharmacyCodeController.text,
          email: _emailController.text,
          password: _passwordController.text,
        );
      } finally {
        client.close();
      }

      await _persistRegistration(
        registration,
        profileRepository: profileRepository,
        profile: profile,
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
    } on Object {
      if (!mounted) {
        return;
      }
      setState(() {
        _registering = false;
        _error = 'Unable to save registration. Please try again.';
      });
    }
  }

  Future<void> _persistRegistration(
    MobileRegistration registration, {
    required ConnectionProfileRepository profileRepository,
    required ConnectionProfile profile,
  }) async {
    // Validate the supplied endpoint before persisting credentials, so a
    // malformed server response cannot leave a partially activated device.
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

    await ref.read(mobileRegistrationRepositoryProvider).save(registration);

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
                  Text(
                    'Offline lease expires: ${current.leaseExpiresAt.toLocal()}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  if (current.accessExpired ||
                      current.leaseExpired) ...<Widget>[
                    const SizedBox(height: 8),
                    Text(
                      current.leaseExpired
                          ? 'Offline transactions are unavailable until the lease is renewed. Connect to the pharmacy server and synchronize.'
                          : 'The online session has expired. Connect to the pharmacy server and synchronize to renew it.',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ],
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
              key: const ValueKey('activation-flow'),
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Darmaltoon',
                  style: Theme.of(context).textTheme.headlineSmall
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text(
                  _credentialsStep
                      ? 'Step 2 of 2 — Sign in to your pharmacy'
                      : 'Step 1 of 2 — Enter your activation key',
                ),
                const SizedBox(height: 20),
                if (!_credentialsStep) ...<Widget>[
                  TextField(
                    key: const ValueKey('activation-key'),
                    controller: _licenseController,
                    autocorrect: false,
                    enableSuggestions: false,
                    textInputAction: TextInputAction.done,
                    decoration: InputDecoration(
                      labelText: strings.licenseKey,
                      prefixIcon: const Icon(Icons.vpn_key_outlined),
                    ),
                    onChanged: (_) => setState(() {}),
                    onSubmitted: (_) {
                      if (_licenseController.text.trim().isNotEmpty) {
                        setState(() {
                          _credentialsStep = true;
                          _error = null;
                        });
                      }
                    },
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    key: const ValueKey('activation-continue'),
                    onPressed: _licenseController.text.trim().isEmpty
                        ? null
                        : () => setState(() {
                            _credentialsStep = true;
                            _error = null;
                          }),
                    icon: const Icon(Icons.arrow_forward_rounded),
                    label: const Text('Continue'),
                  ),
                ] else ...<Widget>[
                  TextField(
                    key: const ValueKey('activation-email'),
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    enableSuggestions: false,
                    decoration: InputDecoration(labelText: strings.email),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    key: const ValueKey('activation-password'),
                    controller: _passwordController,
                    obscureText: true,
                    enableSuggestions: false,
                    autocorrect: false,
                    decoration: InputDecoration(labelText: strings.password),
                    onSubmitted: (_) {
                      if (!_registering) _register();
                    },
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Your activation key and credentials are verified together by the pharmacy server. No access is granted before successful registration.',
                    style: Theme.of(context).textTheme.bodySmall,
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
                    key: const ValueKey('activation-sign-in'),
                    onPressed: _registering ? null : _register,
                    icon: const Icon(Icons.login_rounded),
                    label: Text(
                      _registering ? strings.registering : 'Sign in',
                    ),
                  ),
                  TextButton.icon(
                    key: const ValueKey('activation-back'),
                    onPressed: _registering
                        ? null
                        : () => setState(() {
                            _credentialsStep = false;
                            _passwordController.clear();
                            _error = null;
                          }),
                    icon: const Icon(Icons.arrow_back_rounded),
                    label: const Text('Change activation key'),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}
