# Darmaltoon web dashboard parity scope

Reference: `Haider-Shakoori/PharmaDesktop/src/BusinessOS.Pharmacy.Desktop/Dashboard/DashboardView.xaml`.

The web dashboard redesign mirrors the desktop's **compact title, multi-KPI strip, sales overview/period selector, low-stock and expiry tables, recent transactions, quick actions, and pharmacy overview panel**. Values are sourced from the tenant's existing Laravel models, and action links retain role checks.

## Explicitly excluded from the web app

- Backup and Restore UI
- Application Updates UI
- Network and Terminals configuration/diagnostics UI

Those are desktop/native operational features and must **not** be added to the pharmacy web navigation or treated as missing web parity. Existing backend operational safeguards and device APIs are out of scope for removal.

## Acceptance still needed

- Compare rendered web dashboard side by side with the desktop's **classic** and **glass** appearance on 1440/1024/390 widths.
- Confirm actual typography, spacing, icon artwork, chart scale, panel sizing, RTL/English-Dari-Pashto text, and visual behavior with screenshots.
- Validate period totals, permissions, empty states and tenant isolation in full CI/UAT.
- Do not call the result pixel-perfect until visual screenshot comparison is demonstrated.
- Do not deploy until GitHub checks succeed and deployment is reviewed.
