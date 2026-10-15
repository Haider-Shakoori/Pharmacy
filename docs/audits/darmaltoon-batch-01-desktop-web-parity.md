# Darmaltoon Batch 01 — Desktop ↔ Laravel Web Parity Audit

Date: 2026-10-15  
Source of truth: `Haider-Shakoori/PharmaDesktop@main` (read-only)  
Target: `Haider-Shakoori/Pharmacy@main`  
Scope: repository-based static audit, prioritized follow-up backlog; no application code changes.

## Scope and evidence

The desktop is native .NET 10/WPF, local-first with SQLite, a LAN server/client topology, signed licensing and cloud synchronization. The web is Laravel 13/Blade/Tailwind/Alpine, multi-tenant with central provisioning and a tenant pharmacy web interface. Parity means equivalent business outcomes, not copying device-specific infrastructure into the browser.

Desktop evidence:
- `src/BusinessOS.Pharmacy.Desktop/MainWindowViewModel.cs`: desktop navigation, collapsible sidebar, immersive POS mode, global search, localization, profile, notifications, network and sync status, appearance.
- `src/BusinessOS.Pharmacy.Desktop/{Dashboard,Medicines,Inventory,Purchasing,Pos,Returns,Customers,Expenses,DailyClosing,Reports,Notifications,Backup,Updates,Networking,Administration,Barcode}/*`: implemented UI surfaces.
- `docs/LAN_ACCEPTANCE_STATUS.md`: server-authoritative LAN coverage, simultaneous terminal security, and outstanding physical Windows acceptance.
- `docs/feature-parity-matrix.md`: prior desktop conversion baseline, **not** proof of current feature equivalence.
- `src/BusinessOS.Pharmacy.Desktop/Themes/*`: desktop presentation reference.

Web evidence:
- `routes/tenant.php`: routed operational features and permission middleware.
- `resources/views/pharmacy/*`: present browser screens.
- `resources/views/pharmacy/partials/sidebar.blade.php`: currently flat navigation, teal/slate visual treatments and limited module discovery.
- `app/Http/Controllers/Pharmacy/*`: controller coverage.
- `tests/Feature/{Pharmacy,Tenancy,Desktop,Security,Subscriptions,Uat}/*`: existing regression test suites.
- `README.md`: app architecture and deployment intent.

**Verification semantics:** “Surface present” means a concrete route, view, controller or desktop view exists, **not** a passing end-to-end test or identical rules. “Gap confirmed” is used only when the inspected artifacts directly show a difference. “Needs validation” means behavior, visuals or operational correctness must still be exercised against running builds. No runtime browser, Windows or automated test executions were made in Batch 01.

## Complete module inventory and comparison

| ID | Capability | Desktop evidence | Web evidence | Status / finding | Priority | Implementation batch |
|---|---|---|---|---|---|---|
| PAR-01 | Global shell / design | MainWindowViewModel + Themes/* | layouts/pharmacy.blade.php, partials/sidebar.blade.php | **Confirmed UI divergence:** desktop has sidebar collapse, dedicated POS shell, glass/classic switch, global search. Web sidebar is a flat links list without evidenced equivalents. | P1 | 02 |
| PAR-02 | Dashboard / KPIs | DashboardView + DashboardViewModel | pharmacy/dashboard.blade.php, DashboardController | Both present; numerical filters/alerts/quick-action parity needs validation. | P1 | 03 |
| PAR-03 | Alerts / notifications | NotificationCenterView, NotificationService | pharmacy/alerts/index.blade.php, AlertController | Alert page present on web; persistent notification center/history/preferences parity not established. | P1 | 03 |
| PAR-04 | Medicines catalog | MedicinesView/VM; medicine CSV services | medicines index/create/edit/references; MedicineController | Both present; bulk CSV, validation, categories, manufacturer and pricing behavior need validation. | P1 | 04 |
| PAR-05 | Batch/expiry inventory | InventoryView/VM + stock services | inventory index/show/locations; status/adjustment routes | Both present; FEFO, blocked states, opening stock, adjustments and display parity need tests. | P0 | 04 |
| PAR-06 | Locations / branches | Desktop stock service / LAN | InventoryLocationController and locations blade | Web surface present; location switching and scope parity unverified. | P1 | 04 |
| PAR-07 | Stock import/export | Desktop inventory CSV importer | InventoryController and existing inventory views | Desktop explicit CSV import; web equivalent route not evidenced in inspected tenant routes. Confirm requirements before implementation. | P2 | 04 |
| PAR-08 | POS checkout | PosView/VM + PaymentWindow | pos/index.blade.php + PosController | Both present; keyboard, scanner, mixed payments, precision, failure recovery must be compared. | P0 | 05 |
| PAR-09 | Sales invoices/receipts | InvoiceDetailWindow + native receipt printer | pos/invoices.blade.php + pos/receipt.blade.php | Both present; 58/80mm/A4 output, numbering and metadata need side-by-side validation. | P1 | 05 |
| PAR-10 | Barcode labels | BarcodePrintView + BarcodeLabelPrinter | No dedicated barcode label screen in enumerated pharmacy views | **Confirmed navigation/screen gap** for web barcode-label tools; browser printing feasibility to scope. | P2 | 05 |
| PAR-11 | Suppliers | PurchasingView/VM | suppliers index/create/edit | Both present; ledger/history workflows unverified. | P1 | 06 |
| PAR-12 | Purchase order lifecycle | PurchasingView/VM | purchase order index/create/show; submit/approve/cancel routes | Both present; approval/receipt/payment contract parity to test. | P0 | 06 |
| PAR-13 | Goods receipts/payables | Desktop purchasing services | GoodsReceiptController, PurchaseInvoiceController, SupplierPaymentController | Both present; partial receipt, idempotence and overpayment control to test. | P0 | 06 |
| PAR-14 | Customers | CustomersView/VM | customers index/create/edit/show | Both present; customer sales history/credit and fields need validation. | P1 | 07 |
| PAR-15 | Sales returns/refunds | ReturnsView/VM | return routes + pos/return.blade.php | Both present; allocation-linked stock reversal and refund rules need verification. | P0 | 07 |
| PAR-16 | Expenses / accounting | ExpensesView/VM | accounting/index.blade.php, AccountingController, ExpenseController | Both present; reversal, source-linked journal balance and permissions to test. | P0 | 08 |
| PAR-17 | Cashier shifts / daily close | DailyClosingView/VM | daily-closing/index.blade.php + open/close/finalize/approve/reopen routes | Both present; cash reconciliation, timezone and reopening checks required. | P0 | 08 |
| PAR-18 | Safe / cash management | Local LAN service documented; expenses/closing flows | safe/index.blade.php, SafeController routes | Dedicated safe web screen evidenced; desktop standalone safe UI entry not confirmed from MainWindowViewModel. **Do not remove web capability.** | P1 | 08 |
| PAR-19 | Reports / CSV | ReportsView/VM | reports/index.blade.php + export route | Both present; reconciled totals and filter/export parity require dataset assertions. | P0 | 09 |
| PAR-20 | Users / RBAC | AccessManagementView/VM | users and roles CRUD + middleware | Both present; permission crosswalk, tenant scope and negative-path checks needed. | P0 | 10 |
| PAR-21 | Settings / language | ThemeManager, UiLanguageCatalog, network/receipt/pos settings | pharmacy/settings/edit.blade.php; locale switch | Three-language support claimed on both; desktop appearance/receipt/device settings are device-specific. Test RTL, translation completeness and settings ownership. | P1 | 10 |
| PAR-22 | Authentication & tenant | LoginWindow; session and license clients | login route, tenant-domain middleware, subscription middleware | Different deployment ownership intentional; test permission/auth boundaries and device/tenant identity. | P0 | 10–11 |
| PAR-23 | Offline and local LAN | LAN client/server, Standalone mode, SQLite | Desktop sync API controllers / cloud tenant web | **Architecture distinction, not web bug:** browser requires network to server unless an explicit offline web mode is built. Test cross-channel convergence, never imply browser is offline-first. | P0 | 11 |
| PAR-24 | Desktop/cloud synchronization | Outbox/sync services, cloud sync status | DesktopSyncPull/Push/Status controllers | Both sides of sync interface evidenced; event contract, collisions, duplicate delivery and authorization need executable tests. | P0 | 11 |
| PAR-25 | Backup/recovery | BackupRestoreView, local backup service | Laravel backup commands / services | Environment-specific tools; document responsibility and tenant recovery limits; avoid unsafe browser restore. | P1 | 11 |
| PAR-26 | Updates / installer | UpdateView, signed update pipeline | DesktopUpdateManifestController | Desktop-only update UI intentional; web must publish correct metadata, entitlement and signatures. | P1 | 11 |
| PAR-27 | Responsive & accessibility | Windows WPF UI | Blade + mobile-nav partial | Different form factors; verify browser mobile/tablet, RTL, keyboard/focus and dialogs. | P1 | 02,12 |
| PAR-28 | End-to-end tenant/financial safety | Desktop unit/LAN tests | Web Feature/Pharmacy, Tenancy, Uat suites | Test suites exist; no tests run during audit. Need cross-platform golden fixtures and CI gate. | P0 | 12 |

## Prioritized implementation backlog

### P0 — Data integrity / revenue operations
- **P0-01 [04]:** Golden dataset: batch quantities, blocked/expired lots, FEFO, stock adjustments; assert same remaining stock after same transactions on desktop and web.
- **P0-02 [05]:** POS contract tests: pricing, fractional/decimal values, mixed payments, invoice atomicity, duplicate submit and stock allocation; align browser interaction only after rule tests.
- **P0-03 [06]:** Purchase state-machine/receipt/payables parity: submit→approve→receive→invoice→pay, partial quantities, idempotent posting and overpayment rejection.
- **P0-04 [07]:** Return/refund parity: correct lot allocation reversals, quantity limits, immutable audit records and refund precision.
- **P0-05 [08]:** Cash closing, journal and reversal parity; compare shift totals, safe transfers, finalization/approval/reopen and closed-day protections.
- **P0-06 [09]:** Same dataset yields same profit, stock valuation, sales and close figures; exported CSV totals reconcile.
- **P0-07 [10–11]:** Authorization/tenant boundaries across web controllers, desktop sessions and sync endpoints; verify malicious cross-tenant IDs fail closed.
- **P0-08 [11–12]:** Sync contract, no phantom duplicates, stable retry/idempotency, eventual convergence, fail-closed expired/revoked licenses and offline-mode compatibility.

### P1 — Workflow/UI consistency
- **P1-01 [02]:** Establish desktop style tokens and web UI inventory; compare screenshots for sidebar/topbar, density, empty/loading/error states, table/actions, forms and permissions.
- **P1-02 [02]:** Add web shell equivalents where appropriate: grouped sidebar, collapse/persistence, search, immersive POS entry; ensure no interference with web responsive design.
- **P1-03 [03]:** Dashboard KPI/alerts/notifications side-by-side; define freshness and acknowledgement semantics.
- **P1-04 [04]:** Medicines, suppliers, categories, batches and location workflows; web UX and validation alignment.
- **P1-05 [05]:** Browser POS scanner + keyboard accessibility; receipt rendering, 58/80mm/A4 preview and printer fallback.
- **P1-06 [06–07]:** Purchasing and customer/return workflows: transitions, visible warnings, selection/filters and transactional history.
- **P1-07 [08]:** Show reconciliation differences and controlled audit trail on cash close/accounting actions.
- **P1-08 [09]:** Report filters/timezone range boundaries and download/print parity.
- **P1-09 [10]:** Role/permission matrix, settings ownership and complete en/fa/ps translation+RTL checks.
- **P1-10 [11]:** Backup/restore ownership, desktop update manifest contract and LAN physical smoke plan.
- **P1-11 [12]:** Playwright visual snapshots at desktop/tablet/mobile widths, accessibility and role-based smoke tests.

### P2 — Additional conveniences
- **P2-01 [04]:** Assess web CSV inventory import against desktop CSV mapping; implement only if business flow warrants.
- **P2-02 [05]:** Assess browser barcode label generation/printing with configurable media and printer fallback.
- **P2-03 [02]:** Optional appearance modes; use web-appropriate theming rather than porting WPF glass effects verbatim.

## Acceptance criteria for implementation batches

1. **02:** Comparison captures at 1440px, 1024px and 390px, plus RTL, and documents deliberate platform differences; no dead navigation or broken permissions.
2. **03:** KPI fixture totals and alerts match contract; notifications deterministic per user/tenant.
3. **04:** Batch safety golden paths, concurrent reservations and expired/recalled restrictions pass.
4. **05:** Identical sales inputs produce identical totals, allocations and immutable sale records; print preview validated.
5. **06:** Purchase lifecycle state transitions, receipt idempotency and supplier balances match expected fixtures.
6. **07:** Return quantities and original sales remain auditable; stock/refund reconciles.
7. **08:** Balanced journals, closings and safe reconciliation pass independent fixture assertions.
8. **09:** Reports match underlying transaction and accounting ledgers; CSV tested.
9. **10:** Every tenant-facing route denies unauthorized actions; translations and RTL pass smoke tests.
10. **11:** Revocation/expiry, duplicate pushes, retries and offline reconnect preserve correctness; no cloud requirement added to standalone desktop mode.
11. **12:** PHP tests, Pint, frontend build, Playwright smoke/visual, isolation/security paths and approved UAT gate pass; record versions and CI URLs.

## Validation ledger (Batch 01)

- [x] GitHub inspected both repository source trees and selected feature-defining files.
- [x] Audited web tenant route inventory, pharmacy view inventory and existing test-file inventory.
- [x] Audited desktop navigation, implemented WPF screen inventory and LAN acceptance document.
- [x] Documented confirmed UI differences separately from runtime verification requirements.
- [x] Limited changes to documentation in `Pharmacy` audit branch.
- [ ] Run PHP/Laravel test suite and Pint: **not run**, no PHP execution environment provisioned through GitHub connector.
- [ ] Run .NET tests: **not run**, desktop must remain unchanged and no test runner invoked.
- [ ] Capture side-by-side real running screenshots: **not performed**.
- [ ] Audit complete behavior of every service/controller: **not established by static inventory**.
- [ ] Physical Windows LAN acceptance: **not performed** (desktop LAN doc explicitly defers this).

## Implementation constraints

- Treat desktop business rules as reference, but retain existing safe web behaviors and data without destructive migrations.
- Tenant route filtering, authorization, plan entitlements and transaction idempotency are mandatory.
- Do not edit `PharmaDesktop`, production hosting, databases or active clients in Batch 01.
- Do not conflate `DarmaltoonWeb` (marketing-only) with `Pharmacy` (tenant app).
- Re-evaluate each P0/P1 hypothesis against executable tests and full implementation before declaring a bug.
