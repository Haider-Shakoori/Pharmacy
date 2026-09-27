# Medicine Master

Batch 9 introduces the tenant-scoped medicine catalog.

## Catalog fields

Each medicine supports:

- internal medicine code;
- optional barcode;
- brand name;
- generic name;
- strength;
- dosage form;
- category;
- manufacturer and manufacturer country;
- purchase unit;
- sale unit;
- units per purchase unit;
- reorder level;
- prescription-required flag;
- batch/lot tracking flag;
- expiry tracking flag;
- active/inactive state;
- notes.

Medicine, category and manufacturer identifiers use ULIDs so records can be referenced safely by future offline Android synchronization.

## Stock boundary

The medicine master does not store available quantity, batch number, cost or expiry date. Those are inventory facts and belong to Batch 11 stock/batch records.

Batch and expiry tracking are enabled by default for medicines so inventory and POS can enforce FEFO/expiry rules later.

## Performance

Medicine listing uses server-side search, category/status filters and pagination. Search covers brand, generic name, internal code, barcode and strength.
