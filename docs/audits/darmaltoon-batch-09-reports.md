# Darmaltoon Batch 09 — Reports and Analytics Parity

References: PharmaDesktop `ReportsView.xaml` / `ReportsViewModel.cs` (read-only) and Pharmacy Laravel `ReportController`, reports Blade view, `ReportsTest`.

## Audit checklist
- Compare report types, date ranges, branch/location selection, filters and aggregations with desktop views.
- Confirm sales, gross profit, purchases, batch stock, expiry, returns, daily closings and financial reports tie back to posted source documents.
- Respect user permissions and tenant database boundaries; never expose cross-pharmacy data.
- Check CSV/export access, safe handling of large datasets and RTL/mobile chart and table rendering.
- Implement scoped differences on a separate feature branch after Batch 08 validation; rerun CI/UAT and browser screenshots.

Status: Audit started. No financial formulas, database changes or desktop files modified. Batch 08 PR #75 still awaiting Android UAT.
