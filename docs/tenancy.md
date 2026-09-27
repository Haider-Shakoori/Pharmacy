# Multi-tenancy

Batch 2 establishes fail-closed tenant isolation.

## Rules

- Every pharmacy is represented by a Tenant with a ULID primary key.
- Tenant-owned models use the BelongsToTenant concern.
- TenantScope automatically limits queries to the active TenantContext.
- If no tenant context exists, tenant-owned queries return no rows.
- New tenant-owned records require an active tenant context.
- A model cannot spoof another tenant's tenant_id.
- Tenant identity is resolved from server-side session state by ResolveTenant.
- Suspended or unknown tenants are rejected before tenant routes execute.
- Email uniqueness for pharmacy users is per tenant, allowing the same email address to belong to different pharmacies.

Platform-wide code that legitimately needs cross-tenant access must explicitly remove TenantScope; normal pharmacy controllers, jobs, reports and APIs must never do so.

Future tenant-owned models must include a foreign tenant_id, use BelongsToTenant, and have isolation tests.
