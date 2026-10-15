# Darmaltoon Batch 05 — POS, Checkout and Receipt Scoping Audit

Source repositories: PharmaDesktop (read-only) and Pharmacy (Laravel target). Date: 2026-10-15.

## Verified Laravel entry points
- `PosController`: tenant-context POS, completed invoices and receipt actions.
- `resources/views/pharmacy/pos/index.blade.php`: dedicated full-screen POS, location selection, recent sales and FEFO-related interaction.
- `resources/views/pharmacy/pos/receipt.blade.php`: browser printable receipt with cashier, customer, business date and sales metadata.
- `tests/Feature/Pharmacy/WebPosTest.php`: existing feature tests for FEFO stock allocation and idempotent completed sales.

## Required next implementation steps
1. Inspect complete desktop `PosView`, payment window, receipt printer and browser checkout script before changing UI or checkout behavior.
2. Verify mixed payment, precision, barcode keyboard/scanner, focus management and duplicate submit controls against the existing services.
3. Validate 58mm/80mm/A4 browser receipt CSS and printable metadata with running browser screenshots.
4. Add safe negative-path feature tests for expired/recalled lots, attempted cross-tenant stock access, retries and payment totals where not already covered.
5. Run Laravel CI, full UAT, security audit and browser visual checks. No device-dependent native printer/LAN assumptions in web.

## Status
This is a documentation-only scope branch while Batch 04 PR #71 completes its remaining Android UAT. No checkout or inventory production behavior was changed. Do not merge or begin a dependent code branch until Batch 04 base is confirmed.
