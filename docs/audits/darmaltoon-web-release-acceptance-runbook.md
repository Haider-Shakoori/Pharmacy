# Darmaltoon — Web release acceptance and rollback runbook

Status: **PENDING — NOT APPROVED FOR PRODUCTION**. This runbook complements `docs/audits/darmaltoon-batch-12-final-web-qa.md`. All 12 web implementation batches were merged, but merging and CI do not constitute operational acceptance.

## Pre-release evidence (record an owner, date, environment, URL, output/artifact and PASS/FAIL for each item)

| Gate | Procedure | Result |
| --- | --- | --- |
| Current main regression | Run `php artisan test`, `vendor/bin/pint --test`, `composer audit --no-interaction`, `npm run build` on the intended deployment SHA | PENDING |
| Production configuration | Run `php artisan pharmacy:production:check --strict` on staging with production-equivalent configuration; retain redacted output | PENDING |
| Database / rollback | Back up each tenant database and central database; test restore into isolated staging; rehearse forward and rollback migrations with real schema copies | PENDING |
| Tenant isolation / roles | Exercise tenant A vs B exports and stock mutations, forbidden role POST/PUT/DELETE actions, platform admin boundaries | PENDING |
| Business transactions | Verify FEFO inventory sale, receipt preview, partial refund, supplier GRN, ledger balances, safe daily close and reopening policy | PENDING |
| Responsive + RTL | Capture real browser screenshots at 1440, 1024 and 390 px for dashboard, stock, POS, purchasing, accounting and reports in English, Dari and Pashto | PENDING |
| Offline/LAN desktop | Disconnect WAN, exercise local checkout, queued outbox and reconnection conflict/retry; verify cloud-sync-disabled tenant and revoked/expired leases on real Windows devices | PENDING |
| Printer | Test the supported physical receipt printer, paper width, margins, Unicode/Dari/Pashto output and duplicate-print safety | PENDING |
| Runtime operations | Confirm TLS/HSTS, queue/scheduler workers, log retention, disk space, secret storage, alerts and tenant backup cadence | PENDING |
| Post-release smoke | With user-authorized staging deployment, verify login, tenant boundaries, sync status, POS and daily close without destructive production transactions | PENDING |

## Release execution guardrails

1. Freeze and record the exact target commit SHA and rollback commit SHA.
2. Obtain explicit approval before any real deployment or data migration.
3. Capture verified backups and a **successful restore test** before changing data.
4. Deploy to staging first; run the strict readiness check and all web smoke tests.
5. Verify background jobs, monitoring and exceptions; do not expose secrets in issue logs.
6. Roll back application code on failed smoke tests. Roll back schema only using a rehearsed, data-safe strategy; **never assume `migrate:rollback` is safe for production data**.
7. Reconcile receipts, stock movements, payments and sync outbox before allowing writes after rollback.
8. Record PASS/FAIL, evidence and approver. Leave production on hold if any critical gate is missing.

## Blockers and sequencing

- Pixel-level parity cannot be approved without real screenshots and a reference desktop build.
- Physical printers, Windows LAN and WAN disconnect/reconnect need actual devices.
- Automated GitHub workflows prove only checks they execute, not the above manual scenarios.
- **Android work comes after web batches** and should be organized separately, not used as evidence of web release acceptance.
- No deployment is authorized by this document.
