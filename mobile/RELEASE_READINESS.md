# Darmaltoon mobile release readiness

Development changes are kept on the feature branch until the final review. Do not build, deploy, merge or trigger manual CI while feature development is in progress.

## Development integration gates (before physical-device testing)

The feature branch has passed all five automated GitHub workflows at commit `8edeaeb2`. These gates still need explicit integration evidence; do not mark them complete from CI alone.

- [ ] **Goods receiving:** Enqueue a receipt offline, replay the same idempotency key twice, confirm one server receipt and accepted acknowledgments, and confirm stock does not change before separate authorized inventory posting. Test over-receiving and permissions.
- [ ] **Offline POS:** Verify offline sale creation, batch/expiry stock allocation, duplicate push replay, and balanced payments across disconnect/reconnect.
- [ ] **Medicine registration:** Verify local draft and offline OCR handoff, duplicate medicine/barcode conflict, and eventual server sync without data loss.
- [ ] **Synchronization:** Exercise malformed, missing and duplicate acknowledgments, retryable errors, cursor loops, and interrupted pull recovery.
- [ ] **Authentication:** Verify tenant-scoped storage, token refresh failure, device revocation, lease expiration, and Local/Cloud fallback.
- [ ] **Localization:** Check English, Dari, Pashto, RTL layout and user-facing errors in all newly added flows.

Do not claim these tests have passed until their results are recorded. The receiving validator intentionally rejects bonus-only receipts; support for that case would require coordinated mobile and server business-rule changes.

## Native configuration

- Android application ID: `com.darmaltoon.businessos_pharmacy`.
- iOS bundle ID: `com.darmaltoon.businessosPharmacy`.
- Both platforms use the Darmaltoon icon assets sourced from the public Darmaltoon website.
- Android permits cleartext traffic to support private-LAN pharmacy servers. The Dart endpoint validator restricts unencrypted local endpoints to private-LAN hosts. This manifest setting is application-wide: review every network call for accidental public HTTP usage before production.
- iOS permits local networking via App Transport Security and provides camera, photo library and local network usage descriptions.
- Android desugaring is enabled for the local notification plugin.

## Android signing (only when release is approved)

Provide the following environment variables through a protected CI environment or secure local shell:

- `DARMALTOON_ANDROID_KEYSTORE` — absolute or project-relative keystore path
- `DARMALTOON_ANDROID_STORE_PASSWORD`
- `DARMALTOON_ANDROID_KEY_ALIAS`
- `DARMALTOON_ANDROID_KEY_PASSWORD`

Do not commit a keystore or signing passwords. Without all four variables, the Android release signing configuration is intentionally omitted. Never distribute an unsigned or debug-signed release.

## iOS signing (only when release is approved)

Configure an Apple Developer team, bundle identifier, provisioning profile and signing certificate in the protected macOS build environment. No Apple credentials or provisioning profiles belong in Git.

## Final verification gate — deferred until development completion

1. Review the final branch diff and dependencies.
2. Run Flutter dependency resolution and static analysis.
3. Run all Dart unit/widget tests, including registration, offline POS, purchasing, and synchronization.
4. Verify Android and iOS plugin registration, notifications, scanner, OCR, printer and photo permissions on physical devices.
5. Test registration against a test pharmacy tenant, LAN fallback, token refresh, lease expiry, offline operation and sync conflict recovery.
6. Confirm the Android release signing configuration and iOS provisioning without exposing secrets.
7. Only after explicit approval, run CI and produce Android/iOS artifacts; then review and merge the PR.

No release readiness or successful build is claimed by this checklist.
