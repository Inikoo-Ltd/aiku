---
paths:
  - 'app/Actions/SupplyChain/**'
---

# Supply Chain

## Units per pack is set in the SKO composition, read org_stocks.packed_in
HELP-4263 (23 Oct 2026). A supplier product's units_per_pack mirrors the SKO trade unit composition: SupplierProduct::skoPackedIn() is the answer, SyncStockTradeUnits pushes it onto linked supplier products, and AttachOrgSupplierProductToOrgStock copies it when a supplier product is first linked to a stock, and UpdateSupplierProduct overrides any other value except from the Aurora fetchers (the only callers passing skipHistoric: true, which keep Aurora's own value). The edit forms show the field read-only. Read packing from org_stocks.packed_in: stocks.packed_in is null for ~21k Aurora stocks. The supplier product's own trade unit pivot quantity is the size of one supplier unit, not the pack: never sync it to the pack. A carton holds a number of SKOs: when the pack changes, SyncSupplierProductUnitsPerPackFromSko rescales units_per_carton so the SKOs per carton stay the same (the upload stores only units_per_sko × skos_per_carton).
