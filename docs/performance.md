# Performance Optimization — Batch 26

Batch 26 hardens the pharmacy platform for low-bandwidth and shared-hosting
environments without changing business behavior.

## Mobile synchronization

Delta-sync page defaults are reduced from 200 rows to 100 rows and the server
caps a requested page at 250 rows. The pull service selects only fields present
in the mobile wire contract instead of hydrating complete Eloquent rows.

Composite `(updated_at, id)` indexes back each mobile delta stream
(medicines, inventory batches and customers), matching the cursor ordering and
tie-breaker used by `MobileSyncPullService`.

## Tenant web workload

Operational low-stock alerts now use an SQL aggregate per medicine rather than
eager-loading all sellable batch rows into PHP. The batch alert path has a
composite status/expiry/quantity index.

`PharmacySettings` keeps one tenant-local settings model per service instance,
eliminating repeated reads when a request needs profile, Daily Closing and
inventory policy values.

## Platform workload

Subscription health reuses the already eager-loaded
`business.subscription` graph when the platform monitoring service provides
it. This prevents relationship queries from growing linearly with the number of
pharmacies. A regression test keeps the monitoring snapshot's central query
count bounded as tenants are added.

Central indexes support trial/deadline and active-device monitoring.

## Low-bandwidth rules

- server-rendered web remains the default;
- no external fonts are introduced;
- API payload fields remain compact and explicit;
- mobile sync remains incremental and resumable;
- page sizes are intentionally conservative for unstable Afghan networks.
