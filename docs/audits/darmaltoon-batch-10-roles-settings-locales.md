# Darmaltoon Batch 10 — RBAC, Settings and Languages

Baseline: PharmaDesktop read-only reference and Pharmacy Laravel target.

## Verified Laravel entry points
- `RoleController` and role permission views for permission assignment.
- `SettingsController` and pharmacy settings edit view.
- `PharmacySettingsTest` for regression coverage.

## Verification and implementation scope
1. Map desktop account, staff, roles, permissions, pharmacy policies and locale switching controls to current web routes and screens; desktop layout structure needs deeper inspection.
2. Exercise backend authorization for each sensitive operation, not just hide page buttons.
3. Confirm English, Dari and Pashto switching, translation coverage, RTL tables/forms, and persistence across redirects.
4. Preserve tenant-scoped roles and settings; add negative-path RBAC and cross-tenant tests as needed.
5. Implement UI discrepancies on a dedicated feature branch after Batch 09 validation, run Laravel CI/Web UAT/Android UAT, and conduct actual browser visual checks.

Audit branch only; no production or desktop changes. Batch 09 PR #76 remains subject to final CI/UAT.
