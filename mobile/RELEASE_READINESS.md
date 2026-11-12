# Darmaltoon mobile release readiness

Development changes are kept on the feature branch until the final review. Do not build, deploy, merge or trigger manual CI while feature development is in progress.

## Development integration gates (before physical-device testing)

Six GitHub workflows passed on integration commit `f70f43752138dcbdfa54fe24adbb8510feaa01b2` (2026-11-12): CI, Release Candidate UAT, Flutter Mobile Validation, Flutter Mobile CI, Flutter Windows Offline EXE, and Flutter iOS Unsigned IPA. Automated success does **not** close the remaining physical-device, pharmacy integration, or production release gates.

- [ ] **Goods receiving:** Enqueue a receipt offline, replay the same idempotency key twice, confirm one server receipt and accepted acknowledgments, and confirm stock does not change before separate authorized inventory posting. Test over-receiving and permissions.
- [ ] **Offline POS:** Verify offline sale creation, batch/expiry stock allocation, duplicate push replay, and balanced payments across disconnect/reconnect.
- [ ] **Medicine registration:** Verify local draft and offline OCR handoff, duplicate medicine/barcode conflict, and eventual server sync without data loss.
- [ ] **Synchronization:** Exercise malformed, missing and duplicate acknowledgments, retryable errors, cursor loops, and interrupted pull recovery.
- [ ] **Authentication:** Verify tenant-scoped storage, token refresh failure, device revocation, lease expiration, and Local/Cloud fallback.
- [ ] **Localization:** Check English, Dari, Pashto, RTL layout and user-facing errors in all newly added flows.

Do not claim these tests have passed until their results are recorded. The receiving validator intentionally rejects bonus-only receipts; support for that case would require coordinated mobile and server business-rule changes.

## Current unsigned iPhone test artifact (2026-11-12)

- GitHub Actions workflow: **Flutter iOS Unsigned IPA**, successful run [43219751223](https://github.com/Haider-Shakoori/Pharmacy/actions/runs/43219751223), commit `f70f43752138dcbdfa54fe24adbb8510feaa01b2`.
- Artifact name: `darmaltoon-pharmacy-ios-unsigned`; downloadable from the run's **Artifacts** section.
- The ZIP contains `Darmaltoon-Pharmacy-Unsigned.ipa`. It is explicitly **unsigned**, not an App Store or production release.
- Artifact retention expires **2026-12-12**. Save the test artifact before that date if needed.
- For Sideloadly, extract the IPA from the downloaded artifact ZIP first; Sideloadly handles test-device signing. An unsigned IPA is not directly installable without that signing step.
- Do not use production pharmacy credentials or live tenant data for the test.

### Physical iPhone test checklist — not yet verified

- [ ] Download the artifact ZIP from the specific successful run and extract the IPA.
- [ ] Install via Sideloadly, confirm app opens without crashing, and verify camera/photo permissions.
- [ ] Register against a **test tenant**, verify correct tenant identity and permissions.
- [ ] Scan an English medicine label and a Dari/Pashto label, including two sides of the package.
- [ ] Correct an OCR field **after** checking approval, confirm approval resets and saving stays blocked until reconfirmed.
- [ ] Verify detected batch/expiry stays review-only and is not written to stock by scan.
- [ ] Test inventory Stock/Batches/Expiry/Storage, search clear and refresh, including offline and reconnect.
- [ ] Test POS barcode search, cart quantity, discounts, offline checkout and receipt retrieval with **test data only**.
- [ ] Test Reports, 12-module More navigation, RTL languages and narrow/large text layouts.
- [ ] Confirm no sensitive production data, cross-tenant leaks, or accidental live-stock changes.

Record the tester/device, iOS version, screenshots, and failures before changing any release-readiness checkbox. Successful CI or an artifact is **not** proof these checks passed.

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
