# Darmaltoon Batch 11 — Desktop Sync, LAN and Licensing Audit

Target: Pharmacy Laravel backend and browser; reference: PharmaDesktop (read-only).

## Existing Laravel capabilities
- DesktopSyncPullController, DesktopSyncPushController, DesktopSyncStatusController; supporting sync access context.
- Desktop license activation and refresh endpoints; signed offline lease verification/signing, platform plan/license management.
- MobileSyncPull/Push controllers and cursor services.
- DesktopSyncApiTest, LicenseActivationApiTest, license verifier unit tests, MobileSyncApiTest and platform-plan tests.

## Audit and acceptance
1. Compare actual PharmaDesktop offline persistence, sync conflict handling, multi-machine LAN use and lease enforcement with existing endpoints. Never equate browser web with a native LAN server.
2. Verify tenant/device authorization, idempotent push, cursor isolation, delete/restore semantics, stale clocks, retries and out-of-order messages.
3. Verify fully offline behavior remains operational during server downtime only for entitlement types that allow it; document license expiry and renewal safety.
4. Confirm signing keys, activation limits, revocation and renewal cannot be bypassed; avoid exposing secrets in logs or browser output.
5. Add tests for conflict/replay, cross-tenant access and license expiry; run CI/UAT and desktop integration tests.

Audit documentation only; do not change production sync, billing, license keys, or desktop files without validation. Batch 10 PR #77 remains awaiting final Android UAT.
