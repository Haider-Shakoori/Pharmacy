# Darmaltoon Batch 12 — Final Web QA & Release Readiness

Date: 2026-10-15. Target: Pharmacy Laravel (PharmaDesktop stays read-only).

## Required web/backend gates
1. CI: complete Laravel tests, Pint, Composer audit, production Vite build; review failed or skipped checks explicitly.
2. Tenant security: cross-pharmacy route binding, search, exports, stock batches, accounting reports, licenses and settings.
3. RBAC: cashier, inventory, manager, platform support permissions, including POST/PUT/DELETE enforcement.
4. Accounting integrity: exact decimal payments, returns, goods receipt, stock adjustments, ledger balance, closing/reopen.
5. Desktop visual parity: actual browser screenshots at 1440px, 1024px and 390px; English/Dari/Pashto + RTL; dashboard, stock, POS, purchases, customers, accounting, reports and settings.
6. Browser functional E2E: onboarding, medicines, GRN, FEFO checkout, receipt print preview, return, shifts, daily closing, audit history, exports and navigation.
7. Sync and licensing: desktop push/pull retry, stale cursor, device permissions, offline lease expiry, revocation and LAN scenario; record what cannot be verified without real desktop devices.
8. Deploy readiness: migration/rollback plan, tenant backup/restore, queue/scheduler config, runtime secrets, monitoring and staged smoke tests. **Do not deploy without explicit instruction.**

## Known deferred/not yet proven items
- Android builds/tests/UAT intentionally postponed by user until all web batches are completed; Android jobs may still run automatically in the existing GitHub workflow but are not a required web merge gate.
- Physical receipt printer validation, live desktop/browser pixel-level comparison, persistent web notification-history parity and end-to-end offline/LAN scenarios remain open until demonstrated.
- Passing CI alone is not evidence of pixel-perfect desktop parity or offline reliability.

Status: Audit-only initial checklist; no production code changes. Batch 11 PR #78 awaits required Laravel CI and Web UAT.
