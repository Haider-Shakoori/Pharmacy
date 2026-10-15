# Darmaltoon Batch 06 — Purchasing and Suppliers Audit

Scope: compare the read-only PharmaDesktop PurchasingView/PurchasingViewModel and purchasing/supplier service interfaces with the Laravel Pharmacy purchase order, supplier, GRN and invoice workflows.

## Verified starting points
- Laravel already includes supplier CRUD, supplier searches, purchase orders, goods receipt entry, invoices and supplier payments.
- Laravel includes PurchasingTest (tenant database isolation and exact decimal totals) and dedicated stock posting / accounting services.
- Desktop includes PurchasingView.xaml, PurchasingViewModel.cs and domain abstractions for supplier/purchasing services.

## Implementation priorities after Batch 05 merge
1. Check desktop order-to-receipt-to-invoice interaction and map page-level parity.
2. Improve purchasing pages to desktop blue design tokens, keyboard usability and responsive/RTL presentation.
3. Validate partial receipts, duplicate postings, discounts, landed cost and supplier settlement, keeping decimal precision and immutable stock accounting.
4. Add targeted negative-path tests for tenant isolation, RBAC, over-receipt and retry behavior where missing.
5. Run Laravel CI, Web UAT and Android UAT. Validate screenshots separately.

No operational code changes in this audit branch; do not modify desktop. Batch 05 PR #72 remains awaiting its Android UAT gate.
