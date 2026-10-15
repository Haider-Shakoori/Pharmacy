# Darmaltoon Batch 03 — Dashboard & Operational Alerts

Date: 2026-10-15
Sources: PharmaDesktop DashboardView.xaml, NotificationCenterView.xaml and NotificationService.cs; Laravel DashboardController, AlertController, OperationalAlertService and Blade dashboard/alerts views. The user also supplied a screenshot of the completed desktop dashboard as a visual reference.

## Dashboard completion follow-up
- Added seven-day completed-sales revenue chart by tenant business date; no synthetic points or CDN chart dependencies.
- Added recent completed sales with authorized POS visibility and eager-loaded customer names.
- Added low-stock and near-expiry preview lists using the existing operational alert snapshot.
- Added license/subscription and pharmacy overview side panels. Purchase order count uses the tenant database; date uses the same pharmacy business date.
- User-supplied desktop screenshot is a visual reference, not a claim of pixel-perfect equivalence. No browser screenshot comparison has yet been executed.

## Delivered
- Dashboard: low-stock, near-expiry and expired summary cards linking to the existing authorized alerts route. Counts use the existing tenant-derived `$alerts` snapshot; no new calculations or queries.
- Dashboard: hooks for desktop-inspired KPI card treatments with the existing desktop blue palette.
- Alerts: business-date/policy context, a return link to the dashboard, and RTL-friendly table headings.
- The existing `dashboard.view` middleware remains the permission gate for both the dashboard and alerts. Other action links preserve their existing permission checks.
- Presentation-only changes; no migrations, controllers, service logic, subscription logic, tenant filtering, or desktop files modified.

## Parity review
| Topic | Desktop | Laravel web | Assessment |
|---|---|---|---|
| Dashboard KPI presentation | Compact responsive cards and panels | Eight tenant-derived KPIs | Present; visual parity needs screenshot review |
| Stock/expiry alerts | Dashboard highlights and notifications | OperationalAlertService snapshot | Better surfaced in web dashboard |
| Notification history | Persistent per-device history with categories/read state | Operational alert page | Not equivalent: require separate tenant/user event data model and privacy/retention design |
| Toast preferences | Local notification preferences | Flash success/alerts | Gap retained for later work |
| Connectivity/sync notifications | Per-terminal desktop events | Cloud web operational alerts | Platform-specific; do not invent browser terminal status |

## Test and acceptance ledger
- [x] Changes committed on a dedicated feature branch.
- [x] Existing Laravel controllers, migrations, middleware and desktop source untouched.
- [ ] GitHub CI/UAT workflow result (must inspect before merge).
- [ ] Real screenshot capture against desktop reference at 1440px, 1024px and 390px.
- [ ] Browser RTL visual review in English, Dari and Pashto.
- [ ] Keyboard and screen-reader accessibility test.
- [ ] Runtime verification of KPI amounts, query tenant scope and permission combinations.

## Outstanding Batch 02 carry-over
Live visual verification of desktop/sidebar expansion, mobile menu, and full-screen POS at 1440/1024/390, including RTL, remains outstanding and must not be marked passed without browser evidence.

## Follow-up
A persistent web NotificationCenter requires explicit tenancy-safe storage, event generators, read/unread operations, retention and role permissions. Do not fake persistence using browser-local state or expose other tenants' events.
