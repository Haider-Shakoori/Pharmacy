# Pharmacy Architecture

The product is developed as four connected environments:

1. **Platform SaaS** — tenants, plans, subscriptions, licenses, devices, health and support.
2. **Pharmacy Web** — pharmacy administration, inventory, purchasing, reporting, accounting and web POS.
3. **Android Offline POS** — offline-first Flutter sales application with local SQLite persistence.
4. **Synchronization** — idempotent push/pull synchronization, signed offline license leases and conflict handling.

## Batch 1 decisions

- Laravel 13 / PHP 8.3+ baseline.
- Tailwind CSS 4 with Blade-first server-rendered pages.
- Alpine.js only for lightweight interaction.
- No external web-font dependency.
- AFN and Asia/Kabul are product defaults.
- English, Dari and Pashto locale foundation with RTL support.
- pharmacy.businessos.af is the planned deployment target at Batch 12.
- Domain boundaries are established before tenant, subscription and transaction models are introduced.
- Server-side pagination/search and small payloads are non-negotiable as modules are added.
