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
    this.roles = const <String>[],
    this.lowStockThreshold = 10,
    this.nearExpiryDays = 90,
    this.deviceId,
    this.leasePublicKey,
    this.cloudBaseUrl,
  });

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
      tenantId: tenant['id'].toString(),
      tenantName: tenant['name'].toString(),
      activationId: data['activation_id'].toString(),
      deviceId: data['device_id']?.toString(),
      accessToken: data['access_token'].toString(),
      accessExpiresAt: DateTime.parse(data['access_expires_at'].toString()),
      leaseToken: lease['token'].toString(),
      leaseExpiresAt: DateTime.parse(lease['expires_at'].toString()),
      leasePublicKey: lease['public_key']?.toString(),
      userId: user['id'].toString(),
      userName: user['name'].toString(),
      userEmail: user['email'].toString(),
      permissions: (user['permissions'] as List<dynamic>? ?? <dynamic>[])
          .map((dynamic value) => value.toString())
          .toList(growable: false),
      roles: (user['roles'] as List<dynamic>? ?? <dynamic>[])
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
      tenantId: json['tenant_id'].toString(),
      tenantName: json['tenant_name'].toString(),
      activationId: json['activation_id'].toString(),
      deviceId: json['device_id']?.toString(),
      accessToken: json['access_token'].toString(),
      accessExpiresAt: DateTime.parse(json['access_expires_at'].toString()),
      leaseToken: json['lease_token'].toString(),
      leaseExpiresAt: DateTime.parse(json['lease_expires_at'].toString()),
      leasePublicKey: json['lease_public_key']?.toString(),
      userId: json['user_id'].toString(),
      userName: json['user_name'].toString(),
      userEmail: json['user_email'].toString(),
      permissions: (json['permissions'] as List<dynamic>? ?? <dynamic>[])
          .map((dynamic value) => value.toString())
          .toList(growable: false),
      roles: (json['roles'] as List<dynamic>? ?? <dynamic>[])
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
      deviceId: data['device_id']?.toString() ?? deviceId,
      accessToken: data['access_token'].toString(),
      accessExpiresAt: DateTime.parse(data['access_expires_at'].toString()),
      leaseToken: lease['token'].toString(),
      leaseExpiresAt: DateTime.parse(lease['expires_at'].toString()),
      leasePublicKey: lease['public_key']?.toString() ?? leasePublicKey,
      userId: user?['id']?.toString() ?? userId,
      userName: user?['name']?.toString() ?? userName,
      userEmail: user?['email']?.toString() ?? userEmail,
      permissions: user == null
          ? permissions
          : (user['permissions'] as List<dynamic>? ?? <dynamic>[])
                .map((dynamic value) => value.toString())
                .toList(growable: false),
      roles: user == null
          ? roles
          : (user['roles'] as List<dynamic>? ?? <dynamic>[])
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
  final List<String> roles;
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
      'roles': roles,
      'low_stock_threshold': lowStockThreshold,
      'near_expiry_days': nearExpiryDays,
      'cloud_base_url': cloudBaseUrl,
    };
  }
}