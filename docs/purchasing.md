# Suppliers and Purchasing — Batch 10

All purchasing records are tenant-database records. The central SaaS database contains no supplier, purchase order, goods receipt, supplier invoice or supplier payment tables.

## Supplier master

Suppliers store contact details, payment terms, active status and notes. Supplier balances are not hidden mutable fields; financial balances will derive from source invoices, payments and accounting entries.

## Purchase orders

Purchase orders snapshot currency, ordered quantity, unit cost, line discount, allocated landed cost and totals. Calculations use arbitrary-precision decimal arithmetic rather than PHP floats.

Workflow:

draft -> submitted -> approved -> receipt/invoice processing

Approval is a separate permission from purchase preparation.

## Goods receipts

Batch 10 records the physical receipt source document and captures:

- medicine;
- received quantity;
- bonus/free quantity;
- batch/lot number;
- manufacturing date;
- expiry date;
- unit cost;
- optional sale price.

A receipt is stored as pending_inventory. It does not mutate stock. Batch 11 must post it transactionally into product batches and stock movements, then set inventory_posted_at. Retrying that posting must be idempotent.

## Supplier invoices and payments

Supplier invoice amounts snapshot the approved PO totals. Duplicate supplier invoice numbers are blocked per supplier. Supplier payments support cash, bank, hawala and other methods, are idempotency-key aware, lock the invoice row, reject overpayment/currency mismatch and update paid/balance status atomically.

The accounting ledger posting is intentionally deferred to the accounting batch; the purchase invoice/payment source documents are already immutable references for that posting.

## Purchase returns

Purchase-return stock reversal depends on the Batch 11 stock movement/batch model. It must be implemented with that inventory layer rather than creating a second stock path inside purchasing.
