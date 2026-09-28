# BusinessOS Pharmacy — Android foundation

This directory is the Flutter Android client for BusinessOS Pharmacy.

## Batch 16 scope

Batch 16 establishes the mobile application boundary:

- Flutter 3.47.5 / Dart 3.13.4 toolchain.
- Android-first Material 3 shell.
- English, Dari and Pashto localization with RTL.
- Riverpod and GoRouter application boundaries.
- Encrypted secure storage and stable installation identity.
- Network-link monitoring separated from server reachability.
- **Local / Cloud / Automatic** server connection policy.
- Secure persistence of the selected connection profile.
- Private-LAN validation for local HTTP and HTTPS-only cloud endpoints.
- POS/Stock/Customers/Sync placeholders for later mobile batches.
- Independent Flutter CI for format, analysis, tests and APK compilation.

Batch 16 intentionally does not implement Drift/SQLite, authentication, device
registration, offline sales, synchronization, signed license leases, offline
stock allocation or receipt printing. Those remain Batches 17–23.

## Connection scenarios

**Local** connects to a Laravel/Apache server on the pharmacy's own LAN/Wi-Fi.
The user can enter a private IP such as `192.168.1.20` or a local hostname.

**Cloud** connects to the tenant's dedicated BusinessOS Pharmacy HTTPS endpoint.

**Automatic** prefers Local, falls back to Cloud, and becomes offline when
neither endpoint is reachable. Offline is a runtime state, not a fourth mode.

The Local and Cloud servers must expose the same versioned Pharmacy API. Future
QR pairing or mDNS discovery will only populate the Local endpoint; they do not
change the data model or sync protocol.

## Android host bootstrap

With Flutter 3.47.5 installed:

```bash
cd mobile
bash tool/bootstrap_android.sh
flutter pub get
flutter run
```

The script creates the Android host with application ID
`af.businessos.pharmacy`. CI performs the same bootstrap from a clean runner.

## Optional cloud seed

A build can seed the Cloud endpoint:

```bash
flutter run --dart-define=API_BASE_URL=https://demo.pharmacy.businessos.af
```

The connection screen can then persist the authorized Local/Cloud profile in
encrypted storage. Batch 18 will bind that profile to the registered tenant and
device identity.

## Offline-first rule

When transaction work begins in Batch 19, sales are committed to the local
database first. Network availability must never be a prerequisite for an
otherwise licensed offline sale. Batch 20 synchronization uses UUIDs, an
outbox, idempotency keys, acknowledgements and checkpoints.
