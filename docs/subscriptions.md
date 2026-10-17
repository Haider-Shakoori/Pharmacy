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

## Darmaltoon Standard annual offer (owner-approved, revised October 2026)

**AFN 5,000 for the first 12 months**, then **AFN 3,000 for each subsequent 12-month renewal** per **one pharmacy location**. Standard license includes **1 Windows PC and up to 2 Android phones/tablets running the Darmaltoon Android POS app** on that same pharmacy subscription. Do not market two Windows PCs as included. The third Android or second Windows PC and any other branch are not included; additional pricing requires an owner decision.

The required commercial plan entitlements are `currency=AFN`, `billing_period=yearly`, `max_branches=1`, `max_windows_devices=1`, `max_android_devices=2`; apply **only** to this specific Standard offer, with no silent change to older plans or trials. Commercial first-year and renewal amounts need explicit billing modeling; `plans.price` alone cannot distinguish them.

The existing licensing service separates Windows and Android device limits, but mobile distribution and mixed Android–Windows synchronization/stock consistency still need release QA. A shared subscription is **not evidence of automatic LAN sync between a Windows desktop SQLite store and Android mobile devices**. Full requirements and acceptance tests: [Darmaltoon Standard license specification](darmaltoon-standard-license.md). This section does not certify production configuration or Android readiness.
