# Darmaltoon API — main-domain rollout

Canonical API origin: **https://darmaltoon.com**. The paths remain **/api/v1/**.

On 17 October 2026 the operator mounted only `/api/v1/**` requests into the Laravel front controller on cPanel's marketing document root. This does **not** change route signatures, credentials, licensing keys, Windows/Android entitlements, tenant isolation, or sync protocols.

## Endpoint mapping

| Function | Canonical endpoint |
| --- | --- |
| Windows activation | `https://darmaltoon.com/api/v1/license/activate` |
| Windows license refresh | `https://darmaltoon.com/api/v1/desktop/license/refresh` |
| Windows session login | `https://darmaltoon.com/api/v1/desktop/session/login` |
| Windows sync | `https://darmaltoon.com/api/v1/desktop/sync/{status,push,pull/...}` |
| Desktop update manifest | `https://darmaltoon.com/api/v1/desktop/update/manifest` |
| Android registration/session/sync | `https://darmaltoon.com/api/v1/mobile/...` |
| Trial mobile registration | `https://darmaltoon.com/api/v1/mobile/trial/register` |
| Website lead sync | `https://darmaltoon.com/api/v1/marketing/leads/import` |
| Founding offer aggregate | `https://darmaltoon.com/api/v1/marketing/launch-offer` |

The `pharmacy.darmaltoon.com/api/v1/**` API remains operational **temporarily**, protecting older Windows/Android installations. Do not redirect POST endpoints or deactivate the legacy host until the replacement client installers are distributed, successful license-refresh/sync calls are proven on the new origin and old traffic drains.

## Safe Apache/cPanel dispatch

`/home/darmaltoon/public_html/.htaccess` retains static marketing routes and forwards **only** `^api/v1(?:/.*)?$` to `/platform/index.php` with `[END,QSA,NC]`. Laravel front controller at `/home/darmaltoon/public_html/platform/index.php` boots `/home/darmaltoon/pharmacy-app` and normalizes `SCRIPT_NAME=/index.php` while keeping the original request URI. This avoids Apache interpreting `/platform` as Laravel's base URL.

**Do not** rewrite `/api/lead.php`, `/api/founding-offer.php`, `/api/analytics.php` or other marketing PHP files into Laravel. Keep `PHARMACY_DEPLOYMENT_HOST=pharmacy.darmaltoon.com` for compatibility, `PHARMACY_PLATFORM_DOMAIN=darmaltoon.com`, `PHARMACY_PLATFORM_PATH_PREFIX=platform`, `PHARMACY_API_DOMAIN=darmaltoon.com` and `APP_URL=https://darmaltoon.com`. Route URLs are explicit; do not globally rewrite legacy-host dependencies in one step.

Private website `.darmaltoon-leads/platform-sync.php` and `platform-offer.php` point to the canonical API, with their secrets stored outside the public root.

## Validation

At deploy and again before client packaging:

```bash
curl -fsS https://darmaltoon.com/api/v1/marketing/launch-offer
curl -I https://darmaltoon.com/platform/login
curl -o /dev/null -w '%{http_code}\n' https://darmaltoon.com/api/v1/desktop/sync/status
curl -o /dev/null -w '%{http_code}\n' https://darmaltoon.com/api/lead.php
```

Expected status: 200, 200, 401 (without authentication), 405 (GET to POST-only website intake). Use **invalid JSON** for 422 activation contract smoke tests; never use a real license or write an actual payment during tests.

Before final Windows/Android/iOS packaging: test device activation, license renewal, offline first login/sync and timeouts against the canonical origin, including actual clients. Update signed installers and unsigned IPA **last**, per product-owner instruction.

## Rollback

The production cPanel user stored original `.htaccess` and `.env` under `/home/darmaltoon/.migration-backups/platform-20261017-2300/`. Revert the **API mount rewrite and canonical env keys** only after confirming rollback health on the old origin. The marketing site and legacy API should keep working during an API-mount rollback. Never copy private `.env` or lead-sync credentials into the public root or GitHub.
