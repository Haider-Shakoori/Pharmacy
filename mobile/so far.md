# Darmaltoon Mobile App — So Far
**Project:** Darmaltoon Pharmacy Management | **Updated:** 2026-11-08  
**Scope:** Flutter mobile app (iOS and Android) only. Laravel APIs are mentioned solely as dependencies needed by mobile workflows. Desktop and platform applications are outside this document.  
**Status:** Active development and UI redesign. This is a living implementation record, not a release-readiness certificate.

## Scope boundary
This file documents **only the Darmaltoon Flutter mobile application**: its screens, offline data, mobile integration points, device testing, builds and mobile roadmap. It does not track unrelated Laravel platform administration, desktop implementation, server infrastructure or other BusinessOS apps. Any server-side task below exists only to support a named mobile feature.

## 1. Product goal
Build a professional, offline-first, multilingual pharmacy management app for independent and multi-branch pharmacies. The user-approved visual reference is the **“Darmaltoon Mobile — Complete Pharmacy Management in Your Pocket”** five-phone concept image (dashboard, POS, launcher, inventory, reports). The actual application must use real pharmacy data, not the sample figures or medicine products in the mockup.

### Product principles
- Consistent Darmaltoon pill-and-cross logo, blue/white visual identity, compact cards, readable typography, polished iOS/Android layouts.
- English, Dari and Pashto, including right-to-left layouts where appropriate.
- Local-first workflows that survive disconnection, with tenant-safe synchronization and clear pending/error states.
- Role/permission-based access, device registration and secure pharmacy isolation.
- Separate **medicine master data**, **stock/batches**, and **physical medicine storage locations**.

## 2. Work completed or implemented in source
**Status vocabulary:** *Implemented* means source exists; *automated checks passed* means tests/CI ran; neither implies end-to-end physical-device acceptance.

### Foundation and platform
- [x] Flutter mobile codebase for iOS and Android with Material 3 application shell.
- [x] Current bottom navigation: Home, POS, Medicines, Reports, More.
- [x] English/Dari/Pashto localization foundations and RTL support.
- [x] Darmaltoon app name, icon assets, iOS launch assets and platform identifiers.
- [x] Local/Cloud/Automatic server endpoint selection and encrypted device configuration.
- [x] Pharmacy-specific local SQLite/Drift database, caches, outbox and sync checkpoints.
- [x] Device registration, tenant-scoped authentication/authorization integration and permission gates.
- [x] Offline/online connectivity indicator and synchronization status components.

### Pharmacy operations
- [x] Offline POS components: medicine search/catalog, cart/checkout, local sales/payments, receipts, and sync queue.
- [x] Stock/inventory read-only overview with medicine counts, expired/expiring batches, out-of-stock alerts, acquisition-value summary and location filter.
- [x] Medicine registration UI and scan/review flow; camera/photo acquisition and platform OCR integration in source.
- [x] Duplicate-medicine checks, pharmacist review/approval gates and online registration handoff.
- [x] Offline customers panel and local sales-report summary components.
- [x] Purchasing/receiving worksheet, persisted drafts and offline goods-receipt queue.
- [x] Server-side authorized, idempotent goods-receipt capture with `pending_inventory` state; inventory posting is intentionally separate.
- [x] Operational alerts/notification components and receipt/printing components.
- [x] Sync replay protection, malformed acknowledgment handling, cursor/retry safeguards, tenant isolation, device revocation and offline-sale validation regression tests.

### Source control and iOS build
- [x] Repository: `Haider-Shakoori/Pharmacy`.
- [x] Development branch: `feature/mobile-release-integration-20261106`.
- [x] Latest tracked development file contents synchronized to GitHub on 2026-11-08; the original 15 local commits were represented by four GitHub content commits.
- [x] GitHub Actions macOS unsigned IPA workflow exists and ran successfully on commit `94487d6780f21a3139045ea5b3097982b4355342`.
- [x] Successful run: https://github.com/Haider-Shakoori/Pharmacy/actions/runs/42406697654
- [x] Artifact: `darmaltoon-pharmacy-ios-unsigned` containing `Darmaltoon-Pharmacy-Unsigned.ipa`; uploaded and verified by GitHub Actions. This is an **unsigned test build**, not an App Store or production release.
- [x] GitHub Actions Flutter analysis, tests, unsigned iOS compilation, IPA packaging and artifact upload succeeded for that run.
- [x] User installed/tested the app on an iPhone and supplied screenshots of dashboard and stock/inventory, confirming the present UI needs redesign.

## 3. Current deficiencies and decisions
- [ ] **UI does not match approved reference.** Current dashboard has oversized workspace banner, weak information density and excessive empty space.
- [ ] **Inventory layout needs restructuring.** Large KPI blocks push searchable medicine content below the fold.
- [ ] **Medicine storage page is missing from visible mobile navigation.** A location dropdown on the read-only stock page is not a dedicated storage management workflow.
- [ ] Current More menu exposes Customers, Purchases, Alerts, Sync, Settings; not all proposed 12 modules have complete dedicated screens.
- [ ] Sample reference features such as finance, suppliers, transfers, multi-branch and advanced reports must not be marked implemented without code and integration verification.
- [ ] iPhone camera scanner crash reported previously; physical-device fix/verification remains open.
- [ ] Physical-device OCR, barcode/QR, notifications, printer, local networking and reconnect behavior remain to be verified.
- [ ] Real mobile-to-Laravel end-to-end integration against a disposable tenant is not yet accepted.
- [ ] A successful unsigned IPA build does **not** establish production readiness.

## 4. Required screens and behavior (target scope)
### A. Dashboard
- Branch name, current user/role, notifications, connectivity/sync indicator.
- Compact real-data KPI tiles: sales, profit (when calculable), invoices, low stock, expiring stock, receivables.
- Sales overview chart with selectable date ranges.
- Recent transactions and actionable inventory alerts.
- Quick links to POS, Medicines, Inventory, Purchases, Customers, Suppliers, Reports, Finance, Transfers, Alerts, Sync and Settings.
- Explicit loading, empty, offline and permission-denied states.

### B. POS (sales)
- Barcode/QR scan and searchable medicine catalog.
- Customer selection, item quantities, unit prices, discount, tax only where configured, cart totals and payment methods.
- Batch/expiry-aware stock allocation (FEFO where business rules require), negative-stock prevention and permissions.
- Hold/resume sale, cash/card/split payment as supported, invoice/receipt print/share, returns/refunds with audit trail.
- Offline sales persisted locally, idempotent sync and conflict handling.

### C. Medicines and registration
- Search/filter medicine catalog, medicine detail, images, generics/brands, dosage, unit, barcode, price and status.
- Create/edit medicine subject to permissions; camera/photo-assisted offline OCR with pharmacist review.
- Duplicate detection and safe reconciliation after sync.
- Batch detail, expiry, stock levels by branch/location and stock movement history.

### D. Inventory and **Medicine Storage** (dedicated page)
- Distinct tabs/routes: **Stock | Batches | Expiry | Storage Locations | Movements**.
- Location hierarchy as supported by backend: branch/warehouse → room/area → rack/shelf → bin.
- Create/edit/archive locations with validation and permissions; assign medicines/batches to a storage position.
- View quantities by location, search by medicine/barcode/location, low-stock and expiry alerts.
- Stock receipt posting, adjustments, stock counts, transfers between locations/branches and audit history.
- Offline storage operations only after a safe server/API + local schema + sync conflict model is implemented.
- Avoid inventing stock quantities or allowing unauthorized transfers.

### E. Purchases, customers, suppliers and transfers
- Supplier directory, purchase orders, goods receiving, cost/bonus/expiry capture and separate authorized stock posting.
- Customer profiles, balances/receivables, sale history and payments.
- Branch-to-branch or location-to-location transfers with source/destination approvals and idempotent stock movements.
- Supplier balances, payments, returns and purchasing history where backend support exists.

### F. Reports and finance
- Sales, profit/loss, inventory valuation, expiry, purchases, customer, supplier and cash-flow reports.
- Date/branch/location filters, consistent AFN currency formatting, role restrictions and exports.
- Daily closing, expense entry, payment reconciliation and receivables/payables when implemented.
- Offline summaries must be visibly distinguished from server-authoritative reports.

### G. Administration and support
- User roles/permissions, device registration/revocation, branch selection, license status, tenant isolation.
- Sync queue and conflict resolution, Local/Cloud/Automatic connectivity, diagnostics, language and theme.
- Notifications, stock/expiry alerts, security/audit logs, backup/recovery policy and support information.

## 5. UI redesign implementation plan
1. **Design system:** reusable typography, spacing, color tokens, cards, inputs, buttons, icons, empty/error states; maintain accessibility and responsive iPhone/Android layouts.
2. **App shell:** compact branded header; Home/POS/Medicines/Reports/More bottom bar; polished 12-module launcher without fake active actions.
3. **Dashboard:** replace oversized banner with compact KPI grid, chart, alerts and quick actions using actual providers.
4. **POS:** rebuild search/cart/checkout to reference layout without changing financial or offline rules.
5. **Inventory:** compact Stock/Batches/Expiry views, medicine rows with images, filters and location-aware stock.
6. **Medicine Storage:** implement a dedicated navigable page and required backend/local schema/permissions before enabling writes.
7. **Reports:** attractive icon-led report directory and functioning detail views; mark unsupported report types clearly.
8. **Other modules:** purchases, customers, suppliers, transfers, finance, alerts, sync and settings, prioritized by API readiness.
9. **Localization and polish:** EN/FA/PS strings, RTL, small-screen overflow, dynamic type, dark-mode decisions, accessibility.
10. **Quality gate:** widget/golden tests for key screens, automated API tests, physical iPhone/Android end-to-end testing, then approved GitHub builds.

## 6. Outstanding integration/acceptance tests
- [ ] Offline goods receipt → duplicate replay → one server receipt → separate authorized stock posting; over-receiving/permissions.
- [ ] Offline POS sale, FEFO/expiry stock allocation, payment balancing, disconnect/reconnect, duplicate push.
- [ ] Medicine registration, native OCR without internet, camera crash, duplicate barcode and pharmacist approval.
- [ ] Sync: missing/duplicate/malformed acknowledgments, retryable errors, interrupted pull, cursor loops and recovery.
- [ ] Tenant separation, token refresh failure, device revocation, session lease expiry and Local/Cloud fallback.
- [ ] Storage location create/edit/assign/count/transfer flows and audit consistency after implementation.
- [ ] Physical-device camera, barcode, OCR, receipt printer, notifications, permissions and LAN connectivity.
- [ ] EN/FA/PS/RTL, screen-reader labels, overflow, accessibility and smaller iPhones.
- [ ] Signing, provisioning, dependency/security review and release approval before distribution.

## 7. Delivery rules
- **GitHub is source of truth.** Development changes should be committed on a feature branch and reviewed before merge.
- Use **GitHub Actions macOS** for iOS IPA; do not attempt to compile iOS on the Linux VPS.
- Unsigned IPA can be sideloaded after appropriate signing by the installer; do not call it App Store ready.
- Preserve data integrity, offline-first behavior, role permissions and existing users/sessions during changes.
- Do not deploy backend changes to production or merge without review/approval.
- Track implementation, automated test results, physical-device results and release status separately.

## 8. Next immediate batch
**Priority P0:** implement the reference-aligned design system, dashboard, inventory and module launcher; add a discoverable Medicine Storage entry with clear disabled/empty states until its actual data model is implemented.  
**Priority P1:** implement storage locations and stock movements end-to-end, enhance POS and medicine registration, fix iPhone scanner/OCR behavior.  
**Priority P2:** complete suppliers, finance, transfers, advanced reports and multi-branch workflows; validate on devices and produce the next GitHub IPA.

## 9. Source references
- GitHub repository: https://github.com/Haider-Shakoori/Pharmacy
- Feature branch: https://github.com/Haider-Shakoori/Pharmacy/tree/feature/mobile-release-integration-20261106
- iOS build run: https://github.com/Haider-Shakoori/Pharmacy/actions/runs/42406697654
- Existing mobile project notes: `README.md` and `RELEASE_READINESS.md`.
- Visual target: user-provided “Darmaltoon Mobile — Complete Pharmacy Management in Your Pocket” reference image, 2026-11-08.

## 10. Progress log and update discipline
- **2026-11-06–08:** Extended mobile sync/API regression coverage (duplicate keys, malformed ACK/cursors, outbox retry, tenant isolation, cashier and device authorization); the changes were reconciled into GitHub feature-branch source.
- **2026-11-08:** Built and uploaded an unsigned iOS IPA using GitHub Actions macOS; the workflow completed successfully, but release and hardware integration acceptance remain separate.
- **2026-11-08:** Reviewed real iPhone dashboard/inventory screenshots. Identified information-density, navigation and missing dedicated medicine-storage problems. Approved the five-phone Darmaltoon product concept as the UI design reference.
- **Next update:** For each implemented batch, record commit SHA, files/screens changed, tests run, device observations, remaining limitations and the next priority. Mark checklist items complete only after verification.

**Important:** The approved visual reference is supplied in the project conversation; its original image file is not asserted to be committed to this repository. Keep this Markdown specification usable without requiring that image asset.
