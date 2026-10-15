# Darmaltoon Batch 04 — Medicines & Inventory Parity

Date: 2026-10-15. Source: `Haider-Shakoori/PharmaDesktop` (read-only); implementation target: `Pharmacy`.

## Findings
- Desktop `MedicinesView`, `InventoryView` and `InventoryCsvImporter` provide a rich local medicines/stock UI and CSV import.
- Laravel already implements medicines CRUD, reference data, batch/expiry inventory, FEFO allocation, status audit, immutable stock movement ledger, posting adjustments with accounting, and goods-receipt stock intake.
- `FefoAllocator` selects active non-expired batches with positive available stock in expiry order using transaction locks. Do not rewrite allocation or financial postings for UI parity.
- Web inventory listing previously lacked a filter or visible indication of *sellable versus unavailable* batches. The adjustment page displayed no validation summary when a post was rejected.

## Changes
- Read-only `eligibility=sellable|blocked` filters on the tenant inventory query, preserving pagination and other filtering. Sellable means active, non-expired, positive stock; blocked is its complement.
- Batch table now shows FEFO eligibility and blocked-state reasons (expired, quarantined/recalled/damaged, or depleted). Status, expiry, cost and audit history remain visible.
- Batch detail highlights blocked statuses, adds Laravel validation errors to the page and explains that adjustments immediately affect stock and accounting.
- Desktop blue visual accents used on adjusted pages. All existing routes and permission guards retained.

## Safety and remaining parity
- No migration, new raw stock mutation, accounting rewrite, cross-tenant SQL, or desktop changes.
- No CSV importer added: desktop import has different local-file ownership; needs a validated mapping, audit trail and tenant-safe staging before exposing a bulk action.
- Eligibility filter mirrors current FEFO eligibility check, but **does not override settings** or guarantee other checkout requirements such as price.
- Await GitHub Actions Laravel tests, Pint, Composer audit, Vite build and web/Android UAT.
- Carry-over from Batches 02–03: no side-by-side live browser screenshots at 1440/1024/390 or Dari/Pashto RTL verification; persistent web notification center still outstanding.
