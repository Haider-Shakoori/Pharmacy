# Darmaltoon desktop ↔ Laravel web — functional parity audit (2026-10-16)

Reference: `Haider-Shakoori/PharmaDesktop` (read-only). Target: `Haider-Shakoori/Pharmacy` web. This audit identifies **observed gaps and verification needs**, not a claim that every desktop function is missing. Do not deploy this document.

| Module | Desktop evidence | Laravel web evidence | Follow-up |
| --- | --- | --- | --- |
| Medicines | `MedicineCsvService`: UTF-8 BOM template, preview, duplicate code/barcode checking, category/manufacturer resolution and bulk import | Medicine CRUD and references screens; feature branch PR #89 introduces template and CSV import | **Critical:** PR #89 CI failed due to incorrect UploadedFile namespace in tests (fix committed); require full green CI, negative/permission tests, duplicate and atomicity checks before merge/deploy |
| Inventory | `InventoryCsvImporter`: opening stock CSV (medicine code, batch, dates, quantity, cost, price, notes); invokes validated stock service | Batch/FEFO/GRN/stock adjustment APIs and inventory screens | **Critical:** review all-or-nothing preview/commit, dedupe, batch rules, idempotency, exact decimals, stock movement integrity for PR #89 |
| Backup/restore | `BackupRestoreViewModel` supports create, verify and restore | Production backup commands/services exist; no dedicated tenant UI among `resources/views/pharmacy` | **High:** design permission-restricted tenant backup UI and safe restore approval workflow; never expose filesystem paths/secrets |
| Updates/conflict resolution | `UpdateViewModel`: check, download, install updates; refresh, retry, dismiss sync conflicts | Desktop update manifest API exists, but no equivalent pharmacy web conflict-management view observed | **High:** clarify web owner vs Windows desktop responsibility; add authorized sync conflict visibility/retry where appropriate |
| Network/terminals | `NetworkSettingsViewModel`: pairing, revoke/rename terminals, connection testing, discovery, LAN diagnostics | Desktop session and sync APIs, platform licensing UI | **Medium:** expose appropriate web-managed device/session status where supported; desktop LAN host controls are native-only |
| Notifications | Dedicated `NotificationCenterView` | Web alerts/history screens and tests | **Medium:** parity-check read/clear, locale, retention, acknowledgement, filtering and actionable messages |
| POS and returns | Dedicated POS and returns views | Web POS/returns routes and screens | **High:** desktop action-by-action comparison for partial payments/refunds, FEFO, receipt reprinting and permissions |
| Purchasing, suppliers | Dedicated purchasing view | Web purchase orders, suppliers, GRN, invoices and payments | **High:** compare all desktop actions, cost allocation and financial reversals |
| Expenses, safe, closing, accounting | Desktop expenses and daily closing views | Web accounting, daily closing and safe views | **High:** compare expenses journal, cash movements, closing/reopen, double-entry invariants, permissions; review recent corrupt Safe view incident |
| Reports | Desktop reports view | Web reports and CSV export | **Medium:** reconcile totals, filters, date ranges, exports and localization using identical fixtures |
| Customer management | Desktop customers view | Web customers CRUD, returns workflows | **Medium:** compare search, balances, credit limits, statements and editing flows |
| Password/profile/settings | Desktop password/profile, UI themes and operational settings | Web auth, settings and roles | **Medium:** verify all user-facing settings and RBAC rules |
| Barcode labels | Desktop BarcodePrintView | No dedicated web barcode label screen seen | **Explicit scope decision:** user previously declined barcode dependency; avoid adding unless requested |
| Android | Existing Flutter project | Not part of web parity | **Deferred** until user authorizes Android work |

## Verification sequence
1. Repair and fully test PR #89 before deploying CSV to cPanel. Review transactional correctness, authorization and tenant scoping.
2. Exercise existing web modules in authenticated browser sessions; inspect Laravel logs on 500 errors, and compare each desktop action with a reproducible test.
3. Create focused feature branches for confirmed omissions (backups, conflict visibility, notification actions), with CI/UAT and rollback plans.
4. Perform side-by-side UI/UX comparison after core workflows pass, then staged cPanel deployment with verified database backups.
5. Native-only desktop operations (LAN server start, firewall configuration and executable updates) should not be recreated as unsafe server-side controls.

## Evidence limitations
Navigation and repository code inspection is not equivalent to fully executing desktop and web workflows. Physical printer, offline/LAN, authenticated browser and historical stock/accounting reconciliation remain unverified. No production data changes were made by this audit.
