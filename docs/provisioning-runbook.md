# Tenant Provisioning Runbook

## Provisioning sequence

1. Validate pharmacy/commercial fields, owner credentials and reserved/unique subdomain.
2. Create central infrastructure tenant in `provisioning` state.
3. Create central business/commercial record.
4. Create isolated tenant database through the configured database provisioner.
5. Run tenant migrations only in that new database.
6. Seed tenant settings, permissions, standard roles and initial owner.
7. Create central domain mapping.
8. Provision/verify DNS and TLS through the cPanel-compatible production adapter.
9. Create trial/subscription/license at the defined activation milestone.
10. Run tenant URL/auth/DB smoke tests and only then promote provisioning state to externally ready.

## Failure behavior

Persist the failed step and failure message. Retrying must be idempotent. Never automatically drop a tenant database after a partial failure because it may already contain operating data.

## Production cPanel requirement

Local/test environments may use Stancl's local DB manager. Production must use a cPanel-aware adapter for database creation, domain mapping and TLS verification so tenant resources participate in account backups. Direct root-only assumptions are not allowed.

## Release behavior

Use versioned release directories, shared `.env`/storage, atomic `current` symlink changes, central backup plus tenant backups, central migrations followed by controlled tenant migrations, and post-rollout smoke checks for every tenant.

## cPanel production provisioning

Production supports `TENANCY_DB_PROVISIONER=cpanel`. The application authenticates to cPanel UAPI with a scoped API token, never a cPanel password. Required values are `CPANEL_API_HOST`, `CPANEL_USERNAME`, `CPANEL_API_TOKEN`, `CPANEL_DATABASE_PREFIX`, and `CPANEL_DATABASE_USER`.

Database provisioning is idempotent: the service checks the account database list, creates the pharmacy database only when absent, grants the configured Laravel MySQL user access, persists the tenant database name, then runs tenant-only migrations. A failed attempt does not delete the tenant database.

Tenant web routing uses a wildcard host (`*.pharmacy.businessos.af`) mapped once to the shared Laravel release. cPanel AutoSSL is requested after the tenant domain is registered. The tenant remains `awaiting_domain_tls` until an HTTPS request to its login page succeeds. The seven-day trial is created only after that readiness check passes.

Every provisioning stage writes a central `provisioning_events` record. Platform Admin can retry incomplete provisioning, and the scheduler retries `awaiting_domain_tls` tenants every five minutes. Initial owner credentials are retained only as APP_KEY-encrypted temporary provisioning data and are cleared immediately after the owner account exists.

Do not configure `SESSION_DOMAIN` as a parent wildcard domain. Tenant cookies should remain host-only so platform and pharmacy sessions cannot bleed across subdomains. Tenant database, cache, filesystem, and queue context are initialized before pharmacy application middleware.