# Darmaltoon Standard Annual License — owner-approved commercial requirements

Decision: **17 October 2026** (revised packaging). Status: **commercial specification only; production plan changes and verification pending**. This supersedes the prior **two Windows PC** proposal. Do not publish the Android inclusion as a verified working feature until mobile, licensing and synchronization acceptance gates pass.

## Standard annual offer

| Item | Policy |
| --- | --- |
| Currency | AFN |
| Initial 12-month term | **AFN 5,000** |
| Subsequent 12-month renewal | **AFN 3,000** |
| Pharmacy locations / branches | **1**, tied to the paid pharmacy subscription |
| Windows computers | **1 registered PC** |
| Android POS phones/tablets | **Up to 2 registered Android devices**, in the same pharmacy |
| Windows PC #2 | **Not included**; price/upgrade not approved |
| Android device #3 | **Not included**; price/upgrade not approved |
| Extra pharmacy location | Not included; requires another license or separate approved commercial agreement |
| Staff user logins | Not decided (independent of device count) |
| Optional hosting/cloud sync | Technical and service boundaries must be verified before marketing |
| Setup/training/import/support | Not yet priced or contractually scoped |
| Trial | Independent from paid offering; existing trial allocations unchanged |

Both Android devices are **mobile device seats**, not desktop PCs. All three devices refer to **one pharmacy and one paid subscription**. They must not create three different subscriptions. The Android app is the pharmacy Android POS application, not a free consumer medicine shopping app.

## Central SaaS config and billing

Commercial Standard offering must have these enforced limits:

- `currency = AFN`
- `billing_period = yearly`
- `max_branches = 1`
- `max_windows_devices = 1`
- `max_android_devices = 2`

Do not globally change existing customers' active plans, one-PC-only plans, or trial plans. Roll this as a named/identified Darmaltoon Standard offering and migrate only with explicit audited authorization. Because `plans.price` is just **one** amount, distinguish **5,000 AFN first-year** from **3,000 AFN renewal** using central commercial billing rules, preserving first-year and renewal invoices, audit logs and payment verification. Never silently overwrite plan price to 3,000 AFN for new sales. Support renewal without deleting/revoking legitimate device activations or pharmacy data. Per-branch limit must be enforced by tenant branch operations, not just persisted in `max_branches`.

## What the code already supports and what still needs validation

- `LicenseActivationService` counts active Windows and Android activations **separately** using `max_windows_devices` and `max_android_devices`. Therefore, the existing model is compatible with **1 Windows PC + 2 Android devices**, subject to tests.
- Windows activation presently consumes its one-time key and requires support intervention for moving it to a replacement computer. That matches the 1-PC offering; the former **2-Windows-PC activation redesign is no longer required** for the Standard plan.
- Android activations use the normal device-cap path. Validate three-device / third-Android denial, a second Android joining without invalidating the first, token refresh, offline leases, suspensions and support transfer/revocation.
- Android offline POS and authenticated sync exist in the repository's release-candidate scope, but **do not assume the Windows desktop's local SQLite database and the Android app synchronize directly over LAN or work as one real-time multi-device stock system**. Explicitly verify topology and consistency before promising shared inventory or fully disconnected cross-device operation.
- The Windows PC can remain the primary pharmacy-management workstation. Android devices may operate mobile POS within their actual supported connectivity / offline mode. Define the source of truth, conflict handling and delayed synchronization so stock balances, batches, FEFO expiry, refunds and daily closing remain correct.
- If there is no reliable direct Windows–Android local-sync path, say clearly that Android sync requires the supported cloud/service connection, or implement and test a separate local-sync option before advertising **fully offline multi-device** behavior.
- Confirm Android distribution/install readiness, supported Android versions, branch/site boundary, staff permissions, backups, and privacy/data minimization before launch.

## Acceptance checklist

- [ ] A distinct Standard annual plan (or equivalent protected commercial configuration) enforces yearly, AFN, `max_branches=1`, `max_windows_devices=1`, `max_android_devices=2`.
- [ ] First annual invoice is 5,000 AFN, subsequent annual renewal invoice is 3,000 AFN; payments audited and renewed term correctly computed.
- [ ] 1 Windows PC activates successfully using current one-time Windows key.
- [ ] A second distinct Windows PC is blocked, including when Android seats are unused; one-time key replay fails.
- [ ] Android device #1 and Android device #2 both activate for the **same** subscription and both receive valid independent leases.
- [ ] Android device #3 is blocked and concurrency cannot exceed the two-device cap.
- [ ] Changing/replacing an Android device via support does not invalidate the licensed Windows PC or the other Android device; actions audited.
- [ ] One pharmacy branch enforced on provisioning, branch creation and POS operations.
- [ ] Android POS offline sales and eventual sync verify correct item batch, stock movement, idempotency, returns, conflict resolution and daily close.
- [ ] Windows / Android mixed-device inventory consistency is tested against the **actual implemented sync architecture**, not assumed from shared license alone.
- [ ] After connectivity loss and restoration, no duplicate sales, overselling or permanent silent stock divergence.
- [ ] Renewal preserves license identity, registered allowed devices, audit history and pharmacy data; device recovery documented.
- [ ] Existing trial and other plan entitlements remain unchanged.
- [ ] CI and device QA green and staged release validated before publishing 1 PC + 2 Android offer on production website.

## Not yet approved

Pricing for extra computers/Android devices, extra branches, migration and support SLA, hosted sync costs, Android stand-alone mode, payment gateway, refunds and installation/training terms remain separate decisions.

Related: Pharmacy issue #98; DarmaltoonWeb draft PR #27. This doc replaces the former draft specification from Pharmacy PR #99.
