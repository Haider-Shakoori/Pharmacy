# Batch-Aware Inventory — Batch 11

Inventory lives only inside each pharmacy tenant database.

## Core model

The inventory model is:

medicine -> product_batches -> stock_movements

`product_batches.available_quantity` is a current-state cache for fast reads. The immutable `stock_movements` ledger is the audit trail for every stock change. Application code must never silently edit stock balances without creating a movement backed by a source document.

Each batch records medicine, supplier/purchase source, branch, stock location, lot/batch number, manufacturing/expiry dates, status, cumulative received quantity, current available quantity, purchase cost and optional batch sale price.

## Branches and locations

Branches and stock locations are inside a pharmacy tenant. A chain is not represented by mixing separate SaaS tenants.

Every pharmacy is provisioned with a default MAIN branch and MAIN stock location. Additional branches/warehouses/shelves can be created inside the tenant.

## Goods receipt posting

Batch 10 captures the physical GRN. Batch 11 is the only stock-posting path.

Posting a GRN:

1. locks the goods receipt;
2. returns safely if it was already posted;
3. locks/creates the destination product batch;
4. calculates effective cost using received quantity, free/bonus quantity, proportional PO discount and allocated landed cost;
5. updates the batch's weighted purchase cost and cumulative received quantity;
6. writes one idempotent purchase_receipt stock movement per GRN line;
7. increments PO received quantity by purchased quantity only (bonus units do not satisfy ordered quantity);
8. updates PO state to partially_received or received;
9. stamps the GRN inventory_posted_at and destination location.

## FEFO

Normal consumption uses First-Expiring-First-Out:

- only active batches are eligible;
- expired stock is excluded;
- quarantined, recalled and damaged stock is excluded;
- earliest expiry is used first;
- no-expiry batches sort after dated eligible batches;
- rows are locked during allocation;
- insufficient stock rejects the entire allocation transaction.

Batch 12 POS must call this inventory service inside the sale transaction so sale creation and stock deduction remain atomic.

## Status controls

Supported controlled status changes are active, quarantined, recalled and damaged. Every manual status transition requires a reason and creates a batch_status_event.

A zero-balance active batch can automatically become depleted. Depleted inventory is not sellable.

## Adjustments

Manual adjustments require the inventory.adjust permission. Each adjustment first creates an inventory_adjustment source document and line, then an idempotent stock movement in the same database transaction.

Any movement that would produce a negative balance is rejected.

## Configurable policy

Tenant settings include:

- low-stock threshold;
- near-expiry warning days;
- expired-sale blocking policy;
- FEFO enabled policy.

These values are shared with inventory screens and are intended for dashboard/POS alert behavior.

## Non-deletion rule

Batches and movements with business history are never physically deleted through normal workflows. Corrections use source-backed reversal/adjustment movements and status changes.
