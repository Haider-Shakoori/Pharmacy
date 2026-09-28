# Platform Subscription Monitoring — Batch 25

Batch 25 turns the platform control plane into an operational monitoring surface
for SaaS subscriptions, licenses and Android activations.

## Monitoring scope

Every pharmacy is represented, including tenants that do not yet have a
subscription. Each row derives the canonical subscription health through
`SubscriptionHealthService` and adds:

- subscription plan and current deadline;
- license state and key hint;
- active Android activation count versus plan entitlement;
- last device check-in;
- stale active device count;
- operator-attention reasons and severity.

A device is considered stale after
`24 hours` without a check-in. The monitor uses the latest
`last_seen_at` value, falling back to `activated_at` for a newly activated
device that has never synchronized.

## Attention rules

Critical attention is raised for non-operational subscription/license health or
when active device usage exceeds the current plan limit. Warning attention is
raised for paid subscriptions expiring within seven days, trials ending within
two days, and stale active devices.

The platform dashboard shows the top five attention items and summary KPIs.
The dedicated monitoring page supports attention/all/operational/non-operational
views and search by pharmacy, slug or plan.

No tenant operational database is queried. Batch 25 uses only the central SaaS
control-plane data, so platform monitoring does not add cross-tenant pharmacy
database load.
