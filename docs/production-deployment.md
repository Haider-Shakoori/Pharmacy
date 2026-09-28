# Production Deployment & Monitoring — Batch 30

Batch 30 is the final production release milestone for BusinessOS Pharmacy.

## Release gate

Do not deploy a branch or uncommitted workspace. Production must be deployed
from the exact merged `main` commit that passed normal CI and Release Candidate
UAT.

Before switching the live release, run:

```bash
php artisan pharmacy:production:check --remote --strict
```

The gate checks production mode, debug/HTTPS/session security, matched Ed25519
license keys, central database connectivity, an active platform administrator,
cPanel provisioning configuration/read-only connectivity, backup scheduling,
private backup path, writable Laravel runtime directories and monitoring
routes.

Warnings are release blockers when `--strict` is used.

## Safe deployment sequence

1. Clone the exact final `main` commit into a new release directory.
2. Install Composer dependencies with production flags.
3. Install/build frontend assets.
4. Reuse the existing production `.env`; never replace it with `.env.example`.
5. Run `php artisan optimize:clear`.
6. Run central migrations with `php artisan migrate --force`.
7. Run tenant migrations with the tenancy migration command used by the installed Stancl version.
8. Do not run the generic `DatabaseSeeder` in production because it creates demo data.
9. If an initial platform administrator is required, use only the platform-admin bootstrap seeder with a temporary strong secret and clear `PLATFORM_ADMIN_PASSWORD` afterwards.
10. Run `php artisan pharmacy:production:check --remote --strict` again.
11. Switch the document root/release pointer only after the gate passes.
12. Run the post-deploy smoke checks below.

## Monitoring endpoints

- `GET /up` — lightweight Laravel liveness.
- `GET /ready` on `pharmacy.businessos.af` — central database readiness.
  It returns only `{"status":"ready"}` or HTTP 503 with
  `{"status":"unavailable"}`; database errors are never exposed.

External monitoring should check HTTPS, expected status code and latency for
both endpoints. A `/ready` failure should page the operator even when `/up`
still succeeds.

## Post-deploy smoke checks

- `https://pharmacy.businessos.af/up` returns 200.
- `https://pharmacy.businessos.af/ready` returns 200.
- `/platform/login` renders over HTTPS.
- platform authentication works.
- one existing tenant login renders over HTTPS.
- subscription monitoring loads.
- backup create + verify succeeds.
- Laravel scheduler is configured to run every minute.
- storage and logs are writable.
- `APP_DEBUG=false` and HSTS are active.

## Rollback

Keep the previous release directory unchanged during deployment. If the new
release fails readiness or smoke checks, switch back to the previous release.
Restore a database only when a migration/data change actually requires
recovery; never restore a database merely to roll back application code.

## Ongoing operations

- scheduler every minute;
- automated backup + prune according to Batch 28 policy;
- external checks for `/up` and `/ready`;
- review application logs after deployment;
- run Release Candidate UAT for every release candidate;
- perform a documented backup restore drill at least monthly.
