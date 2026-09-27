# Pharmacy SaaS Architecture

The product uses one shared Laravel codebase with two hard data zones and four functional surfaces.

## Data zones

### Central SaaS database

Contains only software-company/commercial and infrastructure records: tenants, domains, businesses, platform administrators, plans, subscriptions, licenses/activations, platform billing data, provisioning metadata, and platform audit data.

`tenants` represents infrastructure identity and provisioning state. `businesses` represents the commercial pharmacy/customer relationship. Platform payments must never share a ledger with pharmacy POS payments.

### Tenant pharmacy databases

Every hosted pharmacy receives a separate database. Tenant databases contain users/roles/permissions, pharmacy settings, medicines/categories/manufacturers, branches/locations, suppliers, purchasing, batches, stock movements, sales/POS, returns, prescriptions, customers, tenant accounting, expenses, daily closings, and tenant audit data.

A normal `php artisan migrate` runs central migrations only. Pharmacy operating tables live under `database/migrations/tenant` and are applied with the tenancy migration workflow.

## Functional surfaces

1. **Central Platform SaaS** — commercial CRM, plans, subscriptions, billing, licenses, provisioning, support and platform reports.
2. **Tenant Pharmacy Web** — administration, medicines, inventory, purchasing, POS, Daily Closing, reporting and accounting.
3. **Android Offline POS** — Flutter/SQLite sales with signed license leases and local outbox.
4. **Synchronization** — idempotent tenant-bound push/pull, conflict handling and late-sync exceptions.

## Domain topology

- Central control plane: `pharmacy.businessos.af`
- Hosted tenant example: `kabul-central.pharmacy.businessos.af`

Tenant HTTP routes initialize tenancy by domain before authentication/session access. Pharmacy staff therefore enter email/password on their pharmacy domain; they do not select a tenant from a central database.

## Isolation layers

Database isolation is primary. Stancl tenancy additionally scopes cache, filesystem and queued tenant context. Tenant-local sessions use the tenant database. Central models explicitly use the central connection even while tenant tenancy is active.

## Deployment status

The application is not production-ready until the cPanel-aware database/domain/TLS provisioner is connected and provisioning smoke tests verify the actual tenant URL. `application_ready` means application/database setup completed; it must not be presented as DNS/TLS-ready until those checks pass.
