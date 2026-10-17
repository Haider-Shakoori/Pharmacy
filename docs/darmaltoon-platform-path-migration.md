# Darmaltoon public-form consolidation & Platform Admin move

**Status: staged migration design / non-destructive code preparation only.** Do not remove live DNS records, hosting, API endpoints, subscriptions, trial requests or website leads until all cutover gates pass.

## Desired public URLs

| Purpose | Canonical URL after cutover |
| --- | --- |
| Marketing homepage | https://darmaltoon.com/ |
| Marketing demo request | https://darmaltoon.com/demo.html |
| Private Platform Admin login | https://darmaltoon.com/platform/login |
| Private Platform Admin overview | https://darmaltoon.com/platform |
| Private demo inbox | https://darmaltoon.com/platform/demo-requests |

**One public demo form only:** Keep the current marketing-site `demo.html` form. Do not replace it with the legacy pharmacy registration form and do not silently create trials on demo submission.

## Verified live state, 2026-10-17

- `https://pharmacy.darmaltoon.com/` is live and displays **Request a 7-Day Trial**, with a POST destination at `https://register.pharmacy.darmaltoon.com/trial-request`.
- `https://pharmacy.darmaltoon.com/platform` redirects guests to `/platform/login` and is live.
- `https://darmaltoon.com/demo.html` is live and shows the marketing demo-request form; its JS sends `kind=demo` to `/api/lead.php` and uses the existing private `/home/darmaltoon/.darmaltoon-leads/leads.sqlite` store.
- `https://darmaltoon.com/platform/` and `/platform/login` presently return HTTP 404. There is **no live migration** yet.
- Probes to `register.pharmacy.darmaltoon.com`, `api.pharmacy.darmaltoon.com`, `downloads.pharmacy.darmaltoon.com` were inconclusive from the inspection machine (HTTP 000), so **do not assert they are unused**; inspect installed desktop/mobile configs and cPanel DNS before removal.
- `https://pharmacy.darmaltoon.com/api/v1/license/activate` responded with HTTP 405 for GET (the expected API behavior), indicating API consumers may depend on the old origin.

## Form differences (verified source and live HTML)

| Capability | Existing 7-day trial | New demo form |
| --- | --- | --- |
| Purpose | Provision trial **after platform approval** | Capture a sales/demo lead for staff follow-up |
| Pharmacy/name/phone | Required | Required |
| Owner email | Required | Optional |
| City/location | Required | Optional city + province |
| WhatsApp separate from phone | No | Yes |
| Number of computers | No | Yes |
| Preferred language | Yes | Yes |
| Preferred contact channel | No | Yes |
| Password + password confirmation | **Required**, temporarily encrypted in trial-request record | **Not requested** |
| Consent checkbox | Honeypot and account password validation | Explicit contact consent |
| Persistence | Central `trial_requests` | Private marketing SQLite, synced to central `website_leads` |
| Staff screen | **Trial Requests** approval queue | **Demo Requests** inquiry inbox |

The two workflows are **not** interchangeable. Preserve previous trial-request records and trial provisioning, even if new public entry to the trial form is retired. Public demo submissions must never ask for passwords.

## Prepared Laravel route support

This branch adds optional `PHARMACY_PLATFORM_PATH_PREFIX` support for a **dedicated** platform hostname, e.g. `darmaltoon.com/platform`.

Safe staged environment variables:

```dotenv
PHARMACY_DEPLOYMENT_HOST=pharmacy.darmaltoon.com
PHARMACY_PLATFORM_DOMAIN=darmaltoon.com
PHARMACY_PLATFORM_PATH_PREFIX=platform
# Explicitly pin existing service hosts while old backend remains online:
PHARMACY_API_DOMAIN=api.pharmacy.darmaltoon.com
PHARMACY_REGISTRATION_DOMAIN=register.pharmacy.darmaltoon.com
PHARMACY_DOWNLOADS_DOMAIN=downloads.pharmacy.darmaltoon.com
```

These are **illustrative migration config values** that must be checked against the *actual live server configuration*; do not overwrite a working environment with assumed hostnames. The app's dedicated platform routes are mounted under `/platform`. The legacy deployment-host `/platform` routes remain as a temporary compatibility path. The public Laravel trial page and API have **not** been removed in this branch.

## Web-server and data cutover gates

1. Obtain authorized access to the **actual** cPanel account/domain deployment and back up the Laravel central DB, tenant DBs, app config, generated keys, legacy `trial_requests`, and the marketing site's private lead SQLite database. Never publish backups or secrets.
2. Deploy this Laravel route-capable release and its migrations to a safe staging location first. `APP_URL`, `SESSION_DOMAIN`, `SESSION_PATH`, `SESSION_COOKIE`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, proxy HTTPS trust and redirect generation must be audited. Prefer a **host-only** secure cookie and a distinct platform session cookie name; avoid collision with marketing cookies.
3. Configure the root marketing host to serve only `/platform` (including nested routes) through Laravel's **public/index.php** behind an Apache path mount or a web-server reverse proxy. If Apache/cPanel cannot perform that safely, use a deploy arrangement that can. Do **not** rewrite the marketing homepage, `/api/lead.php`, `/assets`, `/guides`, `/contact.html`, or `/demo.html` into Laravel. Make Laravel Vite assets (`/build/*`) reachable separately without exposing app source or `.env`.
4. Verify `GET /platform/login`, CSRF-protected login POST, password change, logout, session cookies, private Demo Requests/Trial Requests, analytics exclusion, and all admin navigation (including unauthorized/guest cases) **on darmaltoon.com itself**.
5. Verify website demo POST still durably records data and website-to-platform secure import still catches up historical/current entries. Follow up on the known internal test by marking its **specific** imported record Completed + Test; never mark all leads as tests.
6. Verify all current desktop/mobile activation, refresh, SaaS APIs, downloads, provisioning and any tenant domain are working. Migrate API/registration/downloads to new stable hostnames only **after** updating and testing all clients, provisioning templates and TLS certificates. Do not turn old API host off early.
7. Once the new admin is proven live, redirect safe **GET/HEAD** legacy admin paths to `https://darmaltoon.com/platform/...` using 301/308; avoid redirecting active POST/auth/API requests across origins. Old public trial GET can safely redirect to `https://darmaltoon.com/demo.html` after approval, with legacy trial POST temporarily preserved for in-flight forms.
8. Monitor traffic and failed requests for a grace period, then retire the `pharmacy.darmaltoon.com` **public website** and DNS host only after all API/mobile/Windows/tenant dependencies have moved. Preserve all central DBs and audit history; deleting a DNS name is **not** deleting customer accounts.

## Automated test

`.github/workflows/ci.yml` includes an isolated test run with the planned host/prefix environment, in addition to the standard Laravel suite. It verifies the main-domain login URL and legacy host path remain accessible via Laravel routing. This **does not** test cPanel Apache URL routing or prove that production has switched.

## Rollback

Keep the original backend cPanel vhost and complete DB backups during cutover. If admin login or privacy protections fail, restore the old `/platform` route and remove only the new web-server path mount. Do not restore an outdated lead DB over newer submissions; reconcile website lead sync using its stable source IDs.
