# Darmaltoon Standard — First 10 paying pharmacies promotion

Approved owner pricing policy (October 2026), separate from any previous proposed AFN 3,000 renewal.

## Offer

| Verified paying pharmacies | First-year price | Each following year |
| --- | ---: | ---: |
| First 10 distinct pharmacies | AFN 5,000 | AFN 5,000 |
| Customer 11 onward | AFN 7,000 | AFN 5,000 |

The launch promotion is **only AFN 2,000 off the first year**. No lifetime discounted renewal is promised. Prices in AFN. Every Standard Annual subscription is limited to one pharmacy, 1 Windows PC and 2 Android devices, subject to actual client/server compatibility validation.

## Eligibility and transaction records

- The founding-customer counter increments exclusively when an authenticated Platform Admin **confirms a verified first-year payment** using the controlled "Record payment" action. Demo leads, contact inquiries, pending/unpaid accounts, free 7-day trials and manual subscription status updates do **not** count.
- A unique `commercial_enrollments.business_id` ensures each pharmacy can occupy no more than one founding place. A singleton counter row is locked transactionally before assignment, allowing no more than 10 allocated founders.
- First-year receipt stores amount, AFN currency, payment reference, confirming admin and payment timestamp. Each enrollment snapshots the agreed renewal amount (AFN 5,000) so subsequent price changes do not rewrite historic terms.
- The first 10 confirmed payers get immutable founder numbers 1 through 10; later buyers are standard customers. A duplicated external payment reference or an attempt to enroll the same pharmacy again is rejected. Transactions roll back on failures; provider/cash verification remains a **human responsibility**, not automatic Stripe reconciliation.
- Annual renewal confirmations create new receipts and extend `subscription.ends_at` by one calendar year after the current expiration, or from now when expired. Renewals never take new founder slots. A status transition alone is **not** a payment.
- The public `GET /api/v1/marketing/launch-offer` gives aggregate price and remaining places (no PII). As requests race, this is an estimate; availability is committed **only** at confirmed payment. Never invent or hardcode "X of 10 remaining" on a static marketing site.

## Explicit plan provisioning

Only after code deployment and data backups:

```bash
php artisan migrate --force
php artisan db:seed --class=DarmaltoonLaunchPlanSeeder --force
```

Seeder uses `firstOrCreate`, defaults to **inactive** and deliberately is *not* included in `DatabaseSeeder`. It will **not overwrite** a previously configured plan or existing subscriptions. Review pricing, billing period, device/branch entitlements and physical multi-device readiness. Then manually activate `DARMALTOON_STANDARD_ANNUAL` through **Platform Admin → Plans**. An existing plan with the wrong 5,000 price or wrong device counts is rejected by the payment confirmation service until corrected. Do not overwrite previously agreed customer contracts.

After launch, use **Platform Admin → Pharmacies → Subscription → Verified payments**:

1. A customer submits a demo/trial request; no founder place is consumed.
2. Staff reviews the pharmacy and verifies payment out of band (bank / cash / trusted provider). Enter the actual amount and unique receipt reference, check "payment verified", and confirm **once**.
3. Service atomically awards one of the first ten founding slots if available, records a receipt, creates or converts the pharmacy's subscription to an annual Standard license, and starts the 12-month term. A first-payment attempt using AFN 5,000 after all ten slots are filled fails safely and requires the correct AFN 7,000 amount; do not collect unexpected extra money without discussing the price with the customer.
4. For subsequent years, staff uses the **Renewal payment** action to confirm receipt of AFN 5,000 and extend coverage. The old receipt history remains intact.

## Sales and existing customer protection

- **Existing contracts**: do not silently increase the renewal price for any customer already promised AFN 3,000. Existing subscriptions are not backfilled into new pricing automatically; migrate any legacy commitments individually using a documented exception policy (separate change required before recording their renewal here).
- Founder places represent confirmed paying pharmacies only, not trial sign-ups, admin-created test accounts, refunded/cancelled payments or unpaid offers. Handling refunds/cancellations and freeing a place is **not automated**. Before public launch decide the policy and implement audited reversals/refunds rather than deleting receipts.
- **Billing is currently manual**, not automated PSP confirmation, payment processing, payment links, tax invoices, or bank settlement reconciliation. The UI cannot prove funds arrived; admin attestation and a unique external receipt reference are necessary.
- New website `darmaltoon.com/demo.html` remains **demo lead capture** until the separately planned unified 7-day trial application flow is connected. Do not market the form as immediate account activation.
- Existing desktop/mobile LAN sync capability still requires live end-to-end validation before advertising all devices as simultaneously synchronized offline.
- Root-domain `darmaltoon.com/platform` migration is separately staged in [PR #103](https://github.com/Haider-Shakoori/Pharmacy/pull/103). Do not merge configurations blindly or remove current API hosts.

## Release verification

Required: full Laravel tests, Pint, npm build, server migrations on staging, browser authenticated payment action, founder 10→11 boundary with concurrent requests, a renewal receipt and license entitlement check; finally production DB backup, deployment and live bank-receipt reconciliation. Monitor for signed-in admin access only, idempotency errors and accounting discrepancies before making the offer available to real customers.

**No live charges or live price changes are performed by this code alone.**
