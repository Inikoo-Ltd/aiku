---
paths:
  - 'resources/js/Components/Procurement/**'
---

# Procurement

## Stock/sales figures follow the ordering-level tab
Any table or modal with Cartons/SKOs/Units tabs (useOrderingLevel) must show stock, sales and coming quantities in the selected level. PurchaseOrderItemStockInfo defaults to SKOs: pass `:inCartons="level === 'cartons'"` wherever a level exists, and format quantities with its formatSales, not formatNumber. HELP-4175: the Cartons tab showed SKO sales because the prop was never passed.

## Partner purchase orders order in SKOs only
Purchase orders to one of our own organisations (is_partner) offer only the SKO ordering level: ModalProductList filters the levels and PurchaseOrder.vue starts partner orders on "skos". This is deliberate because partner carton sizes are not reliable. Do not add cartons or units to partner orders (HELP-4571, cancelled as not a bug).

## Hub suggestions take the hub's shelf stock before a new batch
HELP-4840 (20 Nov 2026): the server decides every quantity ordered from a manufacturing hub in RoundPartnerQuantityToBatches: roundUpPastFreeStock keeps the quantity when the hub's free shelf stock covers it (freeSkosByStockId = available - pre-picked not staged - every buyer's open lines still queued for the shelf; pass the line's id when editing it), and only otherwise rounds up to whole batches. Store, Update, Prepare, rescue Suggest, Browse and the cover bucket all go through it; never call roundUp directly for a hub line again. The pages get the same free figure as hub_stock, which usePurchaseOrderStockCover uses so its ✨ suggestion matches what the server saves. A need larger than the free stock still gets whole batches (no stock + batch split). hub_stock is separate from partner_stock on purpose: partner_stock drives the "partner has only N SKOs" shortfall warning.
