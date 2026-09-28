import 'package:businessos_pharmacy/core/localization/app_strings.dart';
import 'package:businessos_pharmacy/core/network/connection_profile.dart';
import 'package:businessos_pharmacy/core/network/connection_profile_repository.dart';
import 'package:businessos_pharmacy/core/network/deployment_mode.dart';
import 'package:businessos_pharmacy/core/network/server_endpoint.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:flutter/material.dart';

class ConnectionModeCard extends StatefulWidget {
  const ConnectionModeCard({
    required this.defaultCloudUri,
    this.secureStore,
    super.key,
  });

  final Uri? defaultCloudUri;
  final SecureStore? secureStore;

  @override
  State<ConnectionModeCard> createState() => _ConnectionModeCardState();
}

class _ConnectionModeCardState extends State<ConnectionModeCard> {
  late final ConnectionProfileRepository _repository;
  final TextEditingController _localController = TextEditingController();
  final TextEditingController _cloudController = TextEditingController();

  DeploymentMode _mode = DeploymentMode.automatic;
  bool _loading = true;
  bool _saving = false;
  bool _saved = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repository = ConnectionProfileRepository(
      widget.secureStore ?? EncryptedSecureStore(),
      defaultCloudUri: widget.defaultCloudUri,
    );
    _load();
  }

  @override
  void dispose() {
    _localController.dispose();
    _cloudController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final ConnectionProfile profile = await _repository.load();
    if (!mounted) {
      return;
    }

    _localController.text = profile.localEndpoint?.uri.toString() ?? '';
    _cloudController.text = profile.cloudEndpoint?.uri.toString() ?? '';
    setState(() {
      _mode = profile.mode;
      _loading = false;
    });
  }

  Future<void> _save() async {
    final AppStrings strings = AppStrings.of(context);

    setState(() {
      _saving = true;
      _saved = false;
      _error = null;
    });

    try {
      final String localValue = _localController.text.trim();
      final String cloudValue = _cloudController.text.trim();

      final ServerEndpoint? local = localValue.isEmpty
          ? null
          : ServerEndpoint.fromInput(localValue, ServerEndpointKind.local);
      final ServerEndpoint? cloud = cloudValue.isEmpty
          ? null
          : ServerEndpoint.fromInput(cloudValue, ServerEndpointKind.cloud);

      if (_mode == DeploymentMode.local && local == null) {
        throw const FormatException(
          'Local mode requires a pharmacy LAN server.',
        );
      }
      if (_mode == DeploymentMode.cloud && cloud == null) {
        throw const FormatException(
          'Cloud mode requires an HTTPS tenant server.',
        );
      }
      if (_mode == DeploymentMode.automatic && local == null && cloud == null) {
        throw const FormatException(
          'Automatic mode requires a local or cloud server.',
        );
      }

      await _repository.save(
        ConnectionProfile(
          mode: _mode,
          localEndpoint: local,
          cloudEndpoint: cloud,
        ),
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _saving = false;
        _saved = true;
      });
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(strings.connectionSaved)));
    } on FormatException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _saving = false;
        _error = error.message.toString();
      });
    }
  }

  String _modeDescription(AppStrings strings) {
    return switch (_mode) {
      DeploymentMode.local => strings.localModeBody,
      DeploymentMode.cloud => strings.cloudModeBody,
      DeploymentMode.automatic => strings.automaticModeBody,
    };
  }

  @override
  Widget build(BuildContext context) {
    final AppStrings strings = AppStrings.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    strings.connectionModeTitle,
                    style: Theme.of(context).textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Text(strings.connectionModeBody),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<DeploymentMode>(
                    initialValue: _mode,
                    decoration: InputDecoration(labelText: strings.mode),
                    items: <DropdownMenuItem<DeploymentMode>>[
                      DropdownMenuItem<DeploymentMode>(
                        value: DeploymentMode.local,
                        child: Text(strings.localMode),
                      ),
                      DropdownMenuItem<DeploymentMode>(
                        value: DeploymentMode.cloud,
                        child: Text(strings.cloudMode),
                      ),
                      DropdownMenuItem<DeploymentMode>(
                        value: DeploymentMode.automatic,
                        child: Text(strings.automaticMode),
                      ),
                    ],
                    onChanged: (DeploymentMode? value) {
                      if (value == null) {
                        return;
                      }
                      setState(() {
                        _mode = value;
                        _saved = false;
                      });
                    },
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _modeDescription(strings),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _localController,
                    decoration: InputDecoration(
                      labelText: strings.localServer,
                      hintText: '192.168.1.20',
                      helperText: strings.localServerHelp,
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _cloudController,
                    decoration: InputDecoration(
                      labelText: strings.cloudServer,
                      hintText: 'https://tenant.pharmacy.businessos.af',
                      helperText: strings.cloudServerHelp,
                    ),
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
                  if (_saved) ...<Widget>[
                    const SizedBox(height: 12),
                    Text(strings.connectionSaved),
                  ],
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: _saving ? null : _save,
                    icon: const Icon(Icons.save_outlined),
                    label: Text(
                      _saving ? strings.saving : strings.saveConnection,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
