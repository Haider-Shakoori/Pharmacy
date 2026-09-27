# Multi-database Tenancy

The pharmacy SaaS uses database-per-tenant isolation.

## Rules

- Each pharmacy has one infrastructure `Tenant` in the central database and one separate operational database.
- Each tenant is mapped to one or more domain records in the central database.
- Tenant routes use domain identification before auth/session handling.
- Pharmacy operational models do not contain `tenant_id`; their database connection is the isolation boundary.
- Central commercial models explicitly use the central connection.
- Pharmacy users, roles, settings, medicines, stock, sales, prescriptions and accounting are never queried by the central dashboard.
- Same email/code values may exist in different tenant databases without collision.
- Cache, files and queued jobs must retain tenant context.
- Automatic tenant database deletion is disabled. Decommissioning requires an explicit, authorized archival/destructive workflow.

## Migration boundary

Central migrations: `database/migrations`

Tenant migrations: `database/migrations/tenant`

A bare central migration must never create pharmacy-operating tables.

## Testing boundary

Tests must prove two separate tenant databases cannot read each other's rows, and must cover tenant domain resolution, cache isolation, filesystem isolation, queue context, sessions, exports and report tokens as those surfaces are implemented.
