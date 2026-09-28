# Release Candidate UAT — Batch 29

Batch 29 is the release-candidate validation gate for BusinessOS Pharmacy. It
does not introduce new business behavior. Its purpose is to prove that the
SaaS control plane, tenant pharmacy, Android offline client, synchronization,
licensing, accounting, daily closing, security and recovery features still work
together after Batches 1–28.

## Automated release-candidate gate

The `Release Candidate UAT` GitHub workflow runs on pull requests to
`main` and can also be started manually.

The web job validates:

- the cross-module golden path from trial provisioning through POS and Daily
  Closing;
- the complete Laravel test suite;
- PHP formatting;
- Composer security audit; and
- the production frontend build.

The Android job validates:

- Android host bootstrap;
- Drift code generation;
- Dart formatting;
- static analysis;
- the complete Flutter regression suite; and
- an installable debug APK artifact for manual device UAT.

## Automated golden path

`GoldenPathUatTest` verifies this sequence in one tenant:

1. create a pharmacy tenant;
2. provision the seven-day trial and license;
3. provision the owner/RBAC model;
4. create a medicine and batch;
5. post opening stock through the stock movement ledger;
6. complete a cash POS sale;
7. verify stock decreases exactly once;
8. finalize Daily Closing with zero cash variance;
9. verify the business day blocks another sale; and
10. verify the rejected post-close attempt does not change stock or sales.

This complements the focused regression suites for tenancy isolation, purchase
receipts, FEFO, returns, accounting, mobile registration, signed offline lease,
offline POS, sync idempotency, receipt printing, alerts, security and backups.

## Manual web UAT checklist

Before production release, complete these checks on a staging tenant:

- Platform admin login, logout and password change work.
- Start a new seven-day trial and confirm the subscription/license status.
- Confirm device entitlement and subscription monitoring are visible.
- Pharmacy login works for owner, pharmacist, cashier and restricted roles.
- English, Dari and Pashto can be switched; Dari/Pashto render RTL correctly.
- Create/edit a medicine and confirm validation.
- Create supplier → purchase order → goods receipt → inventory posting.
- Confirm batch, expiry, landed cost and FEFO stock are correct.
- Complete cash, bank and mobile-money POS sales.
- Verify discounts and price overrides require the correct permission.
- Verify expired/quarantined/recalled stock is not sold.
- Complete a return/refund and verify stock/accounting reversal.
- Finalize Daily Closing and verify expected cash, counted cash and variance.
- Approve/reopen Daily Closing and verify its audit trail.
- Review accounting, reports, low-stock alerts and expiry alerts.
- Confirm no barcode workflow is required anywhere in the golden path.

## Manual Android UAT checklist

Test on at least one real Android phone:

- Register with a valid license/device/user.
- Verify Local, Cloud and Automatic connection modes.
- Disconnect Internet/Wi-Fi and complete an offline sale.
- Verify local FEFO allocation and same-device oversell protection.
- Restart the app while offline and confirm the sale/stock state remains.
- Reconnect and sync; verify the server receives the sale exactly once.
- Force a server stock conflict and confirm the device surfaces it for
  reconciliation rather than silently changing the completed sale.
- Verify signed offline lease expiry enforcement.
- Print and reprint a receipt using the supported printer path available at the
  test site.
- Enable local notifications and verify low-stock/expiry/sync alerts.
- Confirm tenant switching/registration never exposes another tenant's cached
  database.

## Recovery UAT checklist

- Create a backup.
- Verify the backup checksum/integrity.
- Restore into an isolated staging environment.
- Verify platform login, tenant login, stock totals and a recent sale.
- Confirm the production `.env`, APP_KEY and license signing private key are
  not present inside backup manifests.
- Record the restore-drill date and operator.

## Release sign-off

Batch 29 is accepted only when:

- normal CI is green;
- Release Candidate UAT is green;
- the UAT APK installs on a real Android device;
- the manual web/mobile/recovery checklist has no release-blocking defect; and
- any accepted non-blocking issue is recorded before Batch 30 deployment.
