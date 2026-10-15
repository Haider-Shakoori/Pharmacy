# Darmaltoon Batch 08 — Accounting and Daily Closing Audit

Source: PharmaDesktop `DailyClosingView.xaml` and `DailyClosingViewModel.cs` (read-only). Target: Pharmacy Laravel.

## Existing implementation
- Laravel `DailyClosingController` and `DailyClosingService`: location-scoped business date snapshots, closing events, shifts, staff tracking and close workflow.
- Laravel `AccountingController`, `AccountingAdjustmentController`, `AccountingReportService`: chart of accounts, period filters, accounting adjustments, expenses, journals and financial reporting.
- Existing `AccountingTest`, `DailyClosingTest` and `SafeClosingTest` provide regression starting points.

## Acceptance priorities
1. Compare desktop closing and web daily-close screen control-by-control, including opening counts, counted cash, variance, closing audit, reopen permissions and previous-day history.
2. Verify payment methods, refunds, purchase inventory postings and account balances reconcile across shifts and business dates.
3. Add targeted tests for repeated closing, open shifts, RBAC, multi-location and tenant isolation. Preserve transaction locking and immutable ledger behavior.
4. Apply desktop blue UI and accessible, responsive/RTL form messaging without reimplementing domain service logic.
5. Run CI/UAT and actual web-browser visual parity tests.

Audit only. No modifications to financial or desktop application code. Batch 07 PR #74 remains pending CI/UAT before merging.
