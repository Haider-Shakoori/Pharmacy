# Subscription Plans and Assignments

Batch 4 introduces the commercial subscription model used by the Platform Admin.

## Plans

A plan defines:

- AFN or other configured currency price;
- monthly, quarterly, or yearly billing period;
- maximum pharmacy users;
- maximum Android POS devices;
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
