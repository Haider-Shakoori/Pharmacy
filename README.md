# BusinessOS Pharmacy

Production-oriented, multi-tenant SaaS pharmacy management platform designed for Afghanistan and unreliable/low-bandwidth internet environments.

## Current status

**Batch 1 — Foundation and project architecture: in progress**

The repository now contains the Laravel 13 application foundation and the first tenant-facing pharmacy shell. The dashboard and navigation are intentionally data-light until tenant isolation and operational modules are introduced.

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

Deployment is scheduled for **Batch 12**, when the Web POS is introduced.

## Architecture

The product is divided into Platform SaaS, Pharmacy Web, Android Offline POS and Synchronization layers. See docs/architecture.md.

## Development rule

Each batch must include implementation, tests, formatting/build checks, a clean commit and CI verification before moving to the next batch.
