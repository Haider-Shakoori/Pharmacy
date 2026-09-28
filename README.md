# BusinessOS Pharmacy

Production-oriented, multi-tenant SaaS pharmacy management platform designed for Afghanistan and unreliable/low-bandwidth internet environments.

## Current status

**Batch 29 — Release Candidate regression/UAT: in progress**

Batches 1–28 are implemented, including the SaaS control plane, tenant pharmacy,
purchasing/inventory, FEFO POS, Daily Closing, accounting/reporting, Android
offline POS, authenticated synchronization, signed offline licensing, receipt
printing, operational alerts, performance/security hardening and backup/recovery.

Batch 29 is the release-candidate validation gate before Batch 30 production
deployment and monitoring.

## Technology

- Laravel 13 / PHP 8.3+
- Blade-first UI
- Tailwind CSS 4
- Alpine.js for lightweight interaction
- REST API for the future Flutter Android application
- English, Dari and Pashto with RTL support
- AFN default currency
- Asia/Kabul default timezone

## Deployment

The planned pharmacy web deployment target is pharmacy.businessos.af.

The production deployment target is **pharmacy.businessos.af**. Final production
deployment and monitoring are scheduled for **Batch 30**, after the Batch 29
release-candidate UAT gate is green.

## Architecture

The product is divided into Platform SaaS, Pharmacy Web, Android Offline POS and Synchronization layers. See docs/architecture.md.

## Development rule

Each batch must include implementation, tests, formatting/build checks, a clean commit and CI verification before moving to the next batch.
