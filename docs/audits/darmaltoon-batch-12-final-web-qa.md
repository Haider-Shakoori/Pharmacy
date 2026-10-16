# Darmaltoon Batch 12 — Web QA and release-readiness evidence

Updated: 2026-10-16. Target: `Haider-Shakoori/Pharmacy` (Laravel web). `PharmaDesktop` is a read-only reference. **Android work is deferred until all web batches finish.** This document records evidence, not an assertion of release readiness.

## Merged work
- Batches 01–10: merged in the earlier Darmaltoon web-parity sequence.
- Batch 11: [PR #78](https://github.com/Haider-Shakoori/Pharmacy/pull/78), merged 2026-10-16, merge commit `4750984be1d13d0f808aa8f00a0c2815c4239b43`. Its scope was license-management confirmation/accessibility UI, **not** end-to-end offline/LAN parity.
- Batch 11 head `8ba03efe40f56b9b88bc0d204677bb35e22b172f`: [Laravel CI run 38794246884](https://github.com/Haider-Shakoori/Pharmacy/actions/runs/38794246884) = success; [Release Candidate UAT run 38794246987](https://github.com/Haider-Shakoori/Pharmacy/actions/runs/38794246987) = success. The existing UAT may run Android automatically; do not treat this as Android feature completion.

## QA gate matrix

| Gate | Current evidence | Required before web-release sign-off |
| --- | --- | --- |
| PHP unit/feature tests | CI succeeded on Batch 11 | Re-run full suite on final Batch 12 SHA, investigate skipped tests |
| PHP formatting / dependency audit / Vite build | CI workflow includes `pint --test`, `composer audit`, `npm run build`; Batch 11 CI succeeded | Re-run on Batch 12 SHA |
| Tenant isolation | Existing desktop sync feature tests present | Negative cross-tenant tests across stock, exports, returns, user roles, accounting, and sync |
| RBAC and platform privilege boundaries | Existing feature tests present | Verify GET and every mutation for cashier, stock user, manager and platform admin |
| Stock / accounting invariants | Existing inventory, sale, return, purchasing, accounting and closing test suites present | Compare receipts, FEFO, refunds, GRN, cash shift and ledger balances against expected fixtures |
| Sync and Windows licensing | `DesktopSyncApiTest` covers disabled sync, pulls, idempotency and token purpose; `LicenseActivationApiTest` covers Windows activation and lease refresh | Test revoked devices, expired leases, stale cursor, retries, conflict handling and real offline/LAN behavior |
| Browser E2E and visual parity | Not proven by CI alone | Capture 1440px / 1024px / 390px English, Dari and Pashto/RTL screenshots for key views; check real browser flows |
| Operational readiness | Not yet proven | Stage migration and rollback, queue/scheduler, backup/restore, key management, logs and monitoring |
| Receipt printer | Not yet proven | Physical printer and print preview smoke test |
| Android | Explicitly deferred | Run after web batches; do not add Android development to this PR |

## Web E2E script (manual / browser automation)

1. Sign in as tenant owner; create/review medicines, batch quantities and expiry dates.
2. Receive stock via GRN and inspect supplier balance and stock movement.
3. Execute FEFO POS sale and browser receipt print preview; confirm correct batch decrement, rounding and payment posting.
4. Return part of the sale; confirm batch restoration, refunds and balanced accounting.
5. Complete cash shift and daily close; confirm restricted reopen and audit trail.
6. Verify searching, filtering, exports and pagination for reports with a second tenant, without cross-tenant leakage.
7. Exercise Windows activation, refresh, revoked-device rejection, cloud-sync-off status, queued retries and offline mode on a real desktop/LAN when available.
8. Compare key screens against PharmaDesktop on 1440px, 1024px and 390px across EN / Dari / Pashto, including RTL and focus states.

## Release decision
**NOT SIGNED OFF.** Passing repository CI and UAT is necessary but insufficient to certify physical printers, pixel-perfect visual parity, offline LAN behavior, recovery and production configuration. Do not deploy without explicit user instruction.
