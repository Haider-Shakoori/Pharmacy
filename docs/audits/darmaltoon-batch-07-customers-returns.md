# Darmaltoon Batch 07 — Customers, Returns and Refunds

Desktop references: `CustomersView.xaml`, `CustomersViewModel.cs`, `ReturnsView.xaml`, `ReturnsViewModel.cs` (PharmaDesktop, read-only).

## Laravel baseline
- `CustomerController` supports customer create/edit, searchable directory, completed sales history and outstanding-credit totals.
- `SaleReturnController` only permits returns for completed sales and routes posted returns through `SaleReturnService`.
- Customer index displays credit limit, outstanding due amount, sales count and account status.
- `SaleReturnTest` exists for returns regression coverage.

## Next implementation and verification
1. Compare desktop customer directory and return/refund screens field-by-field, including partial returns and remaining returnable quantities.
2. Improve customer and return navigation, search, status, credit display and inline validation in web UI.
3. Audit refund accounting, original batch identity, stock disposition, repeated refund idempotency and tenant/RBAC enforcement; preserve existing services.
4. Add focused regression tests for over-return, credit adjustment, permission rejection and cross-tenant access where lacking.
5. Run Laravel CI, web/Android UAT and visual/RTL checks before declaring parity.

Audit-only branch: no desktop, database, financial posting or production changes.
