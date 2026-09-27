# Pharmacy Access Control

Batch 7 establishes pharmacy staff authentication and role-based permissions.

## Login boundary

Pharmacy users authenticate with three values:

1. tenant slug (Pharmacy ID);
2. email;
3. password.

The user lookup is explicitly restricted to that tenant. Suspended tenants and inactive users cannot authenticate. Successful login stores the tenant ID server-side and regenerates the session.

## Roles and permissions

Roles belong to one tenant. Permission definitions are global stable capability keys. Users can have multiple roles.

Default role templates:

- Owner
- Manager
- Pharmacist
- Cashier
- Accountant

Future modules must authorize by permission key, not by role name.

## Daily Closing

Daily Closing permissions are already part of the permission catalog so future POS implementation does not need to retrofit authorization.
