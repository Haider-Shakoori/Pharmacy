# Daily Closing Requirement

Daily Closing is a mandatory pharmacy workflow requirement captured before POS implementation so sales, shifts, reporting, and accounting are designed around it.

## Scope

A pharmacy business day must be closed with a permanent closing record containing, at minimum:

- pharmacy/branch;
- business date;
- opening cash;
- gross sales;
- discounts;
- returns/refunds;
- cash sales;
- card/bank/other payment totals when enabled;
- collections or other cash inflows when applicable;
- paid expenses or cash withdrawals when applicable;
- expected closing cash;
- actual counted cash;
- shortage/overage variance;
- transaction count;
- opening user/cashier;
- closing user;
- opened and closed timestamps;
- notes;
- immutable audit metadata.

## Rules

- A business date cannot have more than one final Daily Closing per branch.
- Final closing must use server-calculated totals rather than user-entered sales totals.
- The closer enters counted cash; the system calculates shortage/overage.
- A closing cannot silently rewrite completed sales.
- Reopening a finalized day must require explicit authorization and create an audit event; the previous close is retained.
- Offline Android sales that belong to an already closed business day must enter a controlled late-sync exception flow rather than changing the closing silently.
- Reports and accounting must reconcile to the closing record.
- The next business day/shift opening must use the prior close according to pharmacy configuration.

## Delivery point

The data contract is reserved now. The operational UI and reconciliation engine will be implemented with POS/shifts and daily operations after the Web POS foundation, with Batch 13 covering cashier shifts, returns, and Daily Closing.
