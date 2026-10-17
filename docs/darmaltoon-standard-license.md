# Darmaltoon Standard Annual License — approved commercial requirements

Owner decision recorded: **17 October 2026**. Status: **confirmed specification / pending implementation verification**; no production settings or existing subscriptions are changed by this document.

## Included license terms

| Item | Requirement |
| --- | --- |
| Standard product | Darmaltoon pharmacy software |
| Currency | AFN |
| Initial license | **5,000 AFN for the first 12 months** |
| Renewal | **3,000 AFN for each following 12 months** |
| Location allowance | **Exactly one pharmacy location (one branch)** |
| Windows computers | **Exactly two registered Windows computers maximum, at that pharmacy** |
| Additional Windows computers | Not included; separate pricing/permission **not yet authorized** |
| Additional branches | Not included; require separate license or expressly approved multi-location contract |
| Android devices | Not decided; do not infer Android included or excluded from this Windows limit |
| Staff user count | Not decided; must not conflate employee logins with licensed Windows devices |
| Hosting / sync | Not decided; do not promise cloud features or continuous connectivity |
| Setup, training, backups, support and import | Specific included service scope and fees not yet approved |
| Trial plan | Remains separate from paid standard plan |

The stated annual amounts apply per **one licensed pharmacy location**, not per computer. The included two Windows computers should be permitted to operate as two cashier stations on the same local network where the application actually supports such operations. Never market LAN sync or true fully disconnected renewal without functional testing.

## Required central SaaS configuration

- Standard annual commercial offering must have `currency = AFN`, `billing_period = yearly`, `max_branches = 1`, and `max_windows_devices = 2` (both limits must be non-null).
- The current `plans.price` field stores one price only; it cannot represent an initial **5,000** and recurring **3,000** amount safely by itself. Introduce an explicit first-year vs renewal amount model or separate auditable invoice/quote rules, without silently reducing prices for new sales or overwriting plan history.
- Do not assume 2 active Windows sessions means 2 subscription records; both computer activations belong to the *same* central `Subscription` and `License`.
- Guard branch creation and cross-branch usage against exceeding the single licensed location; `max_branches = 1` alone is only configuration unless enforced in business operations.
- A second pharmacy location owned by the same customer requires another license, unless a future multi-location pricing rule explicitly permits otherwise.
- Renewal should keep the same pharmacy/license identity and permitted devices; a simple renewal must not revoke working paid activations or erase offline business data.
- Maintain auditable operator-verified payments, invoice numbering, dates and license start/end/renewal events in central SaaS. Distinguish collected payments from billings and recognized revenue.
- Never downgrade medicine safety, expiry safeguards, stock movement audit integrity or Daily Closing on active paid plans.

## CRITICAL: current one-time Windows activation design conflicts with the two-PC promise

At specification time, `app/Services/Licensing/LicenseActivationService.php` rejects Windows activation after `windows_activation_key_consumed_at` is set; `LicenseKeyService::reissueWindowsActivationKey()` revokes **all** existing Windows activations and issues a fresh one-time key. The platform license-admin page also advertises only one one-time activation and a destructive all-device reassignment. Setting `max_windows_devices = 2` **alone is insufficient**: after activating PC #1, PC #2 cannot activate without revoking PC #1.

**Required functional design before selling as a two-PC product:**
1. Issue **two independent, single-use, device-bound activation opportunities** under one paid subscription, respecting the two-PC cap. Retain existing security guarantees: never store plaintext keys, expiry/status checks, token signatures, versioning, and atomic transactions.
2. Support PC #1 and PC #2 simultaneously. Issuing/consuming PC #2's key must not revoke PC #1. A PC #3 must be rejected even with a formerly valid key. Retry/reinstall on the same registered PC must not unexpectedly consume a new paid seat.
3. Track and display both active devices, slot occupancy and history in Platform Admin. Issue an additional key when a slot remains; allow **targeted** revoke/reassignment of exactly one selected PC. Do not revoke the other PC merely because support replaces a machine.
4. Reassignment must revoke that computer's lease/session securely and leave the other valid. Record support identity and action in audit log. Ensure per-device key replay is denied. Handle concurrent activations without over-allocating a slot.
5. Preserve working legacy one-PC plans and their keys during migration; migrate existing activation state and existing customers without granting extra devices on other plans.
6. An offline paid installation needs documented device identification, activation/refresh, lease expiry, support recovery, backup/export and renewal behavior. An expired payment term must not destroy pharmacy data.
7. Do not count Android POS devices as Windows computers. Define any separate Android entitlement only after owner approval.

## Required test acceptance cases

- [ ] Admin can create/assign the Darmaltoon Standard annual offer with `max_branches = 1`, `max_windows_devices = 2`, AFN, yearly.
- [ ] New pharmacy's first-year commercial invoice is **5,000 AFN** and renewal after 12 months is **3,000 AFN**.
- [ ] First two distinct Windows computers activate on **same** pharmacy subscription; both can refresh independent signed leases and operate while licensed.
- [ ] Third Windows computer is denied, including concurrent/racing activation requests.
- [ ] Consumed one-time activation keys cannot be replayed for another computer.
- [ ] Replacing PC #2 revokes PC #2 only, and PC #1 remains valid; action is owner/audit traceable.
- [ ] License used for another branch is rejected and additional branch creation is blocked under `max_branches=1`.
- [ ] One Windows PC and an Android POS do not consume two Windows slots.
- [ ] Existing one-PC subscription remains limited to one Windows PC.
- [ ] Simple annual renewal preserves both registered Windows PCs and all customer data; license/state updates and payment record are auditable.
- [ ] Suspended, revoked, expired and unpaid licenses follow documented operational access/restore rules, including disconnected tests.
- [ ] Release CI, safe tenant scope, authorization and staged deployment smoke checks pass.

## Out of scope until separate owner decision

Rates for a third PC, more than one pharmacy branch, Windows-versus-Android combinations, user limits, cloud backups, installation services, support SLA, VAT/tax treatment, reseller commissions and payment gateway. No UI or public pricing claim should assume their amounts or inclusion.

Related launch plan: `Haider-Shakoori/DarmaltoonWeb` draft PR #27. Related backend implementation: issue #98.
