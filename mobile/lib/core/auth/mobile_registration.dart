class MobileRegistration {
  const MobileRegistration({
    required this.tenantId,
    required this.tenantName,
    required this.activationId,
    required this.accessToken,
    required this.accessExpiresAt,
    required this.leaseToken,
    required this.leaseExpiresAt,
    required this.userId,
    required this.userName,
    required this.userEmail,
    required this.permissions,
    this.lowStockThreshold = 10,
    this.nearExpiryDays = 90,
    this.deviceId,
    this.leasePublicKey,
    this.cloudBaseUrl,
  });

  static String _requiredString(Object? value, String field) {
    if (value is! String && value is! num) {
      throw FormatException('Invalid mobile registration field: $field');
    }
    final text = value.toString().trim();
    if (text.isEmpty || text == 'null') {
      throw FormatException('Missing mobile registration field: $field');
    }
    return text;
  }

  factory MobileRegistration.fromApi(Map<String, dynamic> data) {
    final Map<String, dynamic> tenant = Map<String, dynamic>.from(
      data['tenant'] as Map,
    );
    final Map<String, dynamic> user = Map<String, dynamic>.from(
      data['user'] as Map,
    );
    final Map<String, dynamic> lease = Map<String, dynamic>.from(
      data['offline_lease'] as Map,
    );
    final Map<String, dynamic> inventoryPolicy = data['inventory_policy'] is Map
        ? Map<String, dynamic>.from(data['inventory_policy'] as Map)
        : <String, dynamic>{};

    return MobileRegistration(
      tenantId: _requiredString(tenant['id'], 'tenant.id'),
      tenantName: _requiredString(tenant['name'], 'tenant.name'),
      activationId: _requiredString(data['activation_id'], 'activation_id'),
      deviceId: data['device_id']?.toString(),
      accessToken: _requiredString(data['access_token'], 'access_token'),
      accessExpiresAt: DateTime.parse(data['access_expires_at'].toString()),
      leaseToken: _requiredString(lease['token'], 'offline_lease.token'),
      leaseExpiresAt: DateTime.parse(lease['expires_at'].toString()),
      leasePublicKey: lease['public_key']?.toString(),
      userId: _requiredString(user['id'], 'user.id'),
      userName: _requiredString(user['name'], 'user.name'),
      userEmail: _requiredString(user['email'], 'user.email'),
      permissions: (user['permissions'] as List<dynamic>? ?? <dynamic>[])
          .map((dynamic value) => value.toString())
          .toList(growable: false),
      lowStockThreshold:
          int.tryParse(
            inventoryPolicy['low_stock_threshold']?.toString() ?? '',
          ) ??
          10,
      nearExpiryDays:
          int.tryParse(inventoryPolicy['near_expiry_days']?.toString() ?? '') ??
          90,
      cloudBaseUrl: tenant['cloud_base_url']?.toString(),
    );
  }

  factory MobileRegistration.fromJson(Map<String, dynamic> json) {
    return MobileRegistration(
      tenantId: _requiredString(json['tenant_id'], 'tenant_id'),
      tenantName: _requiredString(json['tenant_name'], 'tenant_name'),
      activationId: _requiredString(json['activation_id'], 'activation_id'),
      deviceId: json['device_id']?.toString(),
      accessToken: _requiredString(json['access_token'], 'access_token'),
      accessExpiresAt: DateTime.parse(json['access_expires_at'].toString()),
      leaseToken: _requiredString(json['lease_token'], 'lease_token'),
      leaseExpiresAt: DateTime.parse(json['lease_expires_at'].toString()),
      leasePublicKey: json['lease_public_key']?.toString(),
      userId: _requiredString(json['user_id'], 'user_id'),
      userName: _requiredString(json['user_name'], 'user_name'),
      userEmail: _requiredString(json['user_email'], 'user_email'),
      permissions: (json['permissions'] as List<dynamic>? ?? <dynamic>[])
          .map((dynamic value) => value.toString())
          .toList(growable: false),
      lowStockThreshold:
          int.tryParse(json['low_stock_threshold']?.toString() ?? '') ?? 10,
      nearExpiryDays:
          int.tryParse(json['near_expiry_days']?.toString() ?? '') ?? 90,
      cloudBaseUrl: json['cloud_base_url']?.toString(),
    );
  }

  MobileRegistration withRefreshedSession(Map<String, dynamic> data) {
    final Map<String, dynamic> lease = Map<String, dynamic>.from(
      data['offline_lease'] as Map,
    );
    final Map<String, dynamic>? user = data['user'] is Map
        ? Map<String, dynamic>.from(data['user'] as Map)
        : null;
    final Map<String, dynamic> inventoryPolicy = data['inventory_policy'] is Map
        ? Map<String, dynamic>.from(data['inventory_policy'] as Map)
        : <String, dynamic>{};

    return MobileRegistration(
      tenantId: tenantId,
      tenantName: tenantName,
      activationId: activationId,
      deviceId: data['device_id'] == null
          ? deviceId
          : _requiredString(data['device_id'], 'device_id'),
      accessToken: _requiredString(data['access_token'], 'access_token'),
      accessExpiresAt: DateTime.parse(data['access_expires_at'].toString()),
      leaseToken: _requiredString(lease['token'], 'offline_lease.token'),
      leaseExpiresAt: DateTime.parse(lease['expires_at'].toString()),
      leasePublicKey: lease['public_key']?.toString() ?? leasePublicKey,
      userId: user == null ? userId : _requiredString(user['id'], 'user.id'),
      userName: user == null ? userName : _requiredString(user['name'], 'user.name'),
      userEmail: user == null ? userEmail : _requiredString(user['email'], 'user.email'),
      permissions: user == null
          ? permissions
          : (user['permissions'] as List<dynamic>? ?? <dynamic>[])
                .map((dynamic value) => value.toString())
                .toList(growable: false),
      lowStockThreshold:
          int.tryParse(
            inventoryPolicy['low_stock_threshold']?.toString() ?? '',
          ) ??
          lowStockThreshold,
      nearExpiryDays:
          int.tryParse(inventoryPolicy['near_expiry_days']?.toString() ?? '') ??
          nearExpiryDays,
      cloudBaseUrl: cloudBaseUrl,
    );
  }

  final String tenantId;
  final String tenantName;
  final String activationId;
  final String? deviceId;
  final String accessToken;
  final DateTime accessExpiresAt;
  final String leaseToken;
  final DateTime leaseExpiresAt;
  final String? leasePublicKey;
  final String userId;
  final String userName;
  final String userEmail;
  final List<String> permissions;
  final int lowStockThreshold;
  final int nearExpiryDays;
  final String? cloudBaseUrl;

  bool get accessExpired => !DateTime.now().isBefore(accessExpiresAt);

  Map<String, Object?> toJson() {
    return <String, Object?>{
      'tenant_id': tenantId,
      'tenant_name': tenantName,
      'activation_id': activationId,
      'device_id': deviceId,
      'access_token': accessToken,
      'access_expires_at': accessExpiresAt.toUtc().toIso8601String(),
      'lease_token': leaseToken,
      'lease_expires_at': leaseExpiresAt.toUtc().toIso8601String(),
      'lease_public_key': leasePublicKey,
      'user_id': userId,
      'user_name': userName,
      'user_email': userEmail,
      'permissions': permissions,
      'low_stock_threshold': lowStockThreshold,
      'near_expiry_days': nearExpiryDays,
      'cloud_base_url': cloudBaseUrl,
    };
  }
}
