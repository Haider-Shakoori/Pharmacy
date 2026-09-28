# BusinessOS Pharmacy

Production-oriented, multi-tenant SaaS pharmacy management platform designed for Afghanistan and unreliable/low-bandwidth internet environments.

## Current status

**Batch 30 — Production deployment and monitoring: in progress**

Batches 1–29 are implemented and the Release Candidate UAT gate is green,
including the SaaS control plane, tenant pharmacy, purchasing/inventory, FEFO
POS, Daily Closing, accounting/reporting, Android offline POS, authenticated
synchronization, signed offline licensing, receipt printing, operational
alerts, performance/security hardening, backup/recovery and cross-module UAT.

Batch 30 adds the final production-readiness gate, health monitoring contract
and controlled deployment to pharmacy.businessos.af.

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
