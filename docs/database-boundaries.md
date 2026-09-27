# Central / Tenant Database Boundaries

## Central only

- tenants — infrastructure ID/provisioning state
- domains — tenant-domain mapping
- businesses — SaaS customer/commercial pharmacy record
- platform administrators/sellers
- plans and plan features
- subscriptions/trials
- platform invoices/payments/discounts/commissions
- licenses and activation metadata
- provisioning events and platform audit logs

## Tenant only

- users, roles and permissions
- pharmacy profile/settings
- branches and storage locations
- medicines/products/categories/manufacturers
- suppliers and purchasing
- product batches and stock movements
- sales, sale lines and POS payments
- returns/refunds
- prescriptions/dispensing records
- customers/receivables
- tenant accounts/ledger/expenses
- Daily Closing and cashier shifts
- tenant activity/audit logs

## Forbidden coupling

- Central operators do not run arbitrary tenant operational queries.
- Platform payments are not pharmacy POS payments.
- Subscription expiry never deletes tenant data.
- Tenant DB foreign keys never point to central tables; cross-boundary identity uses stable IDs/contracts where required.
- Multi-branch operations remain inside one tenant database unless they are genuinely separate commercial tenants.
