# Darmaltoon Batch 02 — Web Shell & Design-System Delivery

Date: 2026-10-15. Base: `audit/darmaltoon-desktop-web-parity-batch01`. Desktop source of truth: `PharmaDesktop/src/BusinessOS.Pharmacy.Desktop/Themes/Colors.xaml`.

## Implemented
- Web CSS tokens sourced from desktop palette (blue primary #0B82F6, selected #DDEEFF, canvas #F5F8FC, border #E2E8F0, success/warning/danger).
- Persistent desktop sidebar collapsed/expanded state using local browser storage, with safe exception handling. Sidebar expansion toggle uses ARIA state.
- Blue selected navigation, navigation section labels, grouped workspace/operations/resources, accessible current-page marker and RTL-aware indicator.
- Browser shell/background/topbar and common card focus, hover, responsive and reduced-motion styling.
- Improved mobile menu ARIA linkage and bounded scrolling. Mobile reports link now respects `reports.view`.
- No controller, route, database, migration, entitlement, financial logic or desktop repository changes.

## Behavior and security constraints
- All existing sidebar and mobile navigation permission guards remain in place.
- Language switching remains routed through existing localization mechanism.
- Full-screen POS layout remains bypassed by the shared shell; do not change checkout business behavior here.
- Browser storage persists only a UI preference, never credentials or tenant data.

## Evidence & verification
- Files modified: `resources/css/app.css`, `resources/views/layouts/pharmacy.blade.php`, `resources/views/pharmacy/partials/sidebar.blade.php`, `resources/views/pharmacy/partials/mobile-nav.blade.php`.
- Source inspected: desktop `Themes/Colors.xaml`, desktop `MainWindowViewModel.cs`, web Blade layout/partials and Batch 01 parity audit.
- GitHub commits successfully written and branch files fetched back.
- Runtime PHP test/Pint/Vite build, Playwright screenshots, permission smoke and visual comparison **not executed** in this GitHub-only session. Do not interpret repository write success as passing tests.
- Existing workflow: `.github/workflows/ci.yml`; run its checks on this feature branch before merging.

## Remaining Batch 02 follow-up before final signoff
- Verify mobile+desktop browser renders (1440 / 1024 / 390) and `dir=rtl` for Dari/Pashto.
- Verify accessibility focus and active state across all permission roles.
- Confirm no change to browser POS full-screen UX or print styles.
- Run PHP feature tests, Laravel Pint and Vite build; create a PR only when CI passes.
- Audit design consistency of deep module-specific screens; this commit covers shared chrome/design foundation, not every form or table.
