---
paths:
  - 'resources/js/Components/Procurement/**'
---

# Procurement

## Stock/sales figures follow the ordering-level tab
Any table or modal with Cartons/SKOs/Units tabs (useOrderingLevel) must show stock, sales and coming quantities in the selected level. PurchaseOrderItemStockInfo defaults to SKOs: pass `:inCartons="level === 'cartons'"` wherever a level exists, and format quantities with its formatSales, not formatNumber. HELP-4175: the Cartons tab showed SKO sales because the prop was never passed.

## Partner purchase orders order in SKOs only
Purchase orders to one of our own organisations (is_partner) offer only the SKO ordering level: ModalProductList filters the levels and PurchaseOrder.vue starts partner orders on "skos". This is deliberate because partner carton sizes are not reliable. Do not add cartons or units to partner orders (HELP-4571, cancelled as not a bug).
