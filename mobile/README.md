# BusinessOS Pharmacy — Android foundation

This directory is the Flutter Android client for BusinessOS Pharmacy.

## Batch 16 scope

Batch 16 establishes the mobile application boundary only:

- Flutter 3.47.2 / Dart 3.13.x toolchain.
- Android-first application shell.
- Material 3 UI with English, Dari and Pashto localization and RTL.
- Riverpod dependency boundary for testable services.
- Declarative routing with GoRouter.
- Secure storage abstraction for future tokens, device credentials and signed offline leases.
- Installation/device identity service.
- Network-link monitoring that deliberately does **not** equate Wi-Fi/mobile connectivity with server reachability.
- Server reachability probe abstraction.
- Compile-time environment configuration.
- POS/Stock/Customers/Sync navigation shell with explicit future-phase placeholders.
- Independent Flutter CI for format, analyze, tests and APK compilation.

This batch intentionally does **not** implement the local SQLite/Drift schema, authentication, device registration, offline sales, synchronization, license leases, stock allocations or receipt printing. Those are Batches 17–23.

## Android host bootstrap

The repository keeps the Flutter business/application source stable while the Android host files are generated from the pinned Flutter SDK. This avoids committing a stale Gradle wrapper before native integrations are required.

With Flutter 3.47.2 installed:

```bash
cd mobile
bash tool/bootstrap_android.sh
flutter pub get
flutter run
```

The script creates the Android host with application ID `af.businessos.pharmacy`. CI performs the same bootstrap from a clean runner before analysis, tests and APK build.

## API configuration

The foundation accepts an optional tenant API base URL at compile time:

```bash
flutter run --dart-define=API_BASE_URL=https://demo.pharmacy.businessos.af
```

No tenant URL is hard-coded into the binary. Future activation/device-registration flows will establish and persist the authorized tenant endpoint.

## Offline-first rule

When transaction work begins in Batch 19, sales must be committed to the local database first. Network availability must never be a prerequisite for an otherwise licensed offline sale. Synchronization will use UUIDs, an outbox, idempotency keys, acknowledgements and checkpoints rather than repeated whole-table uploads.
