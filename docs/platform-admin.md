# Platform Admin

Batch 3 introduces a separate SaaS-owner control plane.

## Security boundary

- Platform administrators authenticate with the platform guard backed by platform_admins.
- Pharmacy users continue to use the separate web guard.
- Platform authentication does not remove TenantScope from pharmacy-owned models.
- Any cross-tenant access must be explicit in platform-only code.
- Inactive platform administrators cannot authenticate.
- Login is rate-limited and the session ID is regenerated after successful authentication.

## Pharmacy tenant management

Platform administrators can:

- create pharmacies;
- edit pharmacy identity/localization;
- search pharmacies server-side;
- filter by status;
- activate, suspend, or archive pharmacies;
- view aggregate pharmacy/user counts.

Subscriptions, plans, licenses and subscription health are intentionally added in later batches.

## Bootstrap administrator

Set PLATFORM_ADMIN_EMAIL and PLATFORM_ADMIN_PASSWORD before running the seeder to create the initial platform administrator. The seeder does not contain a hard-coded production password and will not overwrite an existing administrator password.
