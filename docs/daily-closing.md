# Daily Closing

Daily Closing is a core operational control, not an optional reporting feature.

## Required behavior

Each pharmacy business day and cashier shift must be auditable from opening through closure. The implementation will cover:

- opening user, time and opening cash;
- cashier/terminal/branch identity;
- gross sales and discounts;
- sales returns and voids;
- payment-method totals;
- credit sales and collections where applicable;
- cash expenses/payouts recorded during the shift;
- expected cash;
- physically counted cash;
- shortage or overage variance;
- closing notes;
- close user/time;
- supervisor approval;
- controlled reopen with reason and audit trail;
- final re-close after corrections;
- late transactions arriving from offline Android devices;
- protection against silently changing an already approved closing.

## Permission keys reserved in Batch 7

- daily-closing.view
- daily-closing.open
- daily-closing.close
- daily-closing.approve
- daily-closing.reopen

A cashier may open and close their own shift, while approval/reopening can be restricted to supervisors, managers or owners. Exact workflow and accounting posting are implemented with the POS/returns phase.
