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
