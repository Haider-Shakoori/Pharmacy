# Windows Offline Edition

BusinessOS Pharmacy uses the same Flutter offline-first core for Android and Windows.

## First launch and licensing

The production Windows build is compiled with `https://pharmacy.businessos.af` as its initial cloud endpoint. A PC must reach the BusinessOS licensing service before it can be registered. The registration request includes a stable installation UUID and declares the platform as `windows`.

A valid subscription or one-time seven-day trial receives an Ed25519-signed offline lease. Trial expiry is server-owned and is capped at the subscription's `trial_ends_at`; the Windows clock never determines when the trial started or when it ends.

For a first-time trial, the pharmacy must already be provisioned in BusinessOS. The Windows app asks for the pharmacy code and the owner credentials, authenticates those credentials with the central licensing service, then starts the one-time trial server-side and registers that PC. The plaintext license key is not required for this first trial registration. Invalid credentials do not consume the trial, an expired trial cannot be restarted, and a pharmacy with an existing paid subscription must register with its normal license key.

The trial plan permits one Windows PC by default. Paid plans have a separate `max_windows_devices` allowance from Android devices.

## Clock rollback resistance

Before each offline transaction the client verifies the signed lease and compares local time against:

- the server-signed `issued_at` and `expires_at` timestamps;
- the greatest time previously observed in the tenant SQLite database; and
- the same high-water mark stored independently in encrypted OS secure storage.

If the Windows clock moves backwards by more than the five-minute tolerance, new transactions are blocked until the clock is corrected and the client can renew its lease online. Removing only the SQLite database does not remove the secure high-water mark. Reinstalling cannot create a fresh trial because trial eligibility and expiry are stored centrally.

This is tamper resistance, not an assertion that an administrator-controlled PC can be made cryptographically impossible to modify. The authoritative controls remain the server-issued signed lease and the one-time server-side trial record.

## Build output

`.github/workflows/flutter-windows.yml` runs on a Windows GitHub runner, generates the native Windows host, runs analysis/tests, creates the release bundle, and packages it with Inno Setup.

Artifacts:

- `BusinessOS-Pharmacy-Setup.exe` — per-user Windows installer.
- `BusinessOS-Pharmacy-Portable.zip` — portable release bundle for diagnostics or manual deployment.
