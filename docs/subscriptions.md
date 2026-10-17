# Subscription Plans and Assignments

Batch 4 introduces the commercial subscription model used by the Platform Admin.

## Plans

A plan defines:

- AFN or other configured currency price;
- monthly, quarterly, or yearly billing period;
- maximum pharmacy users;
- maximum Android POS devices;
- maximum Windows desktop installations (`max_windows_devices`);
- maximum branches;
- offline license grace days;
- optional commercial feature flags;
- active/inactive availability and display order.

Inactive plans remain in historical records but cannot be newly assigned.

## Tenant subscriptions

Each pharmacy has at most one current subscription record. The record tracks the selected plan, operational status, start/end timestamps, auto-renew preference, and platform notes.

This batch deliberately does not generate activation keys. Batch 5 adds signed license/activation data tied to the subscription.

## Core controls versus commercial features

Daily Closing is a core pharmacy control and is not gated by subscription tier. The same applies to transaction integrity, audit history, and basic inventory safety. Optional commercial features may be plan-gated, but essential accounting/safety controls remain available to every valid subscription.

## Darmaltoon Standard annual offer (owner-approved, October 2026)

The Darmaltoon Standard license is **5,000 AFN for its first 12 months**, then **3,000 AFN per 12-month renewal**, covering **one pharmacy location (one branch) and two Windows computers at that location**. The second Windows computer is an included seat on the same subscription, not a second license or branch. Extra branches or Windows computers are not included, and their prices are not approved.

These values are commercial requirements, **not evidence that the current deployment enforces them**. In particular, the current one-time Windows activation flow is single-device; updating only `max_windows_devices` will not make two PCs concurrently activatable. Design, constraints, migration and verification checklist are in [Darmaltoon Standard license specification](darmaltoon-standard-license.md). Confirm activation support and annual first-vs-renewal billing before offering this product publicly.
