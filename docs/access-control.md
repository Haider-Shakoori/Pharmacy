# Pharmacy Authentication, Roles and Permissions

Batch 7 converts the pharmacy shell into an authenticated tenant workspace.

## Login

Pharmacy staff sign in with:

- pharmacy code/slug;
- email;
- password.

The pharmacy code is required because the same email address may legitimately exist in multiple tenants. Tenant context is established before Laravel loads the tenant-scoped user.

Protected pharmacy requests execute in this order:

1. resolve tenant from server session;
2. authenticate pharmacy user;
3. enforce operational subscription/license health;
4. enforce route permission.

## Standard roles

Every pharmacy receives system roles:

- Owner;
- Administrator;
- Pharmacist;
- Cashier;
- Inventory;
- Accountant.

Custom roles can also be created.

## Daily Closing permissions

Daily Closing is explicitly split into:

- daily_closing.perform — perform the normal end-of-day close;
- daily_closing.reopen — reopen a finalized day.

Cashiers receive perform but never reopen by default. Owner and Administrator have all permissions; Accountant receives both Daily Closing permissions. This separation is intentional so reopening a finalized day is auditable and restricted.

## Initial owner

When Platform Admin creates a pharmacy it must also create the initial owner name/email/password. The same transaction provisions the trial, license, standard roles and owner account.

## Isolation

Roles are tenant-owned and use the same fail-closed TenantScope as users. Role selection validation includes tenant_id, preventing a user from being assigned a role belonging to another pharmacy.
