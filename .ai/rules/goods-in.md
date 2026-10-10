---
paths:
  - 'app/Actions/GoodsIn/**'
---

# Goods In

## Service invoices own the checklist rows they fill
Local container bills (freight, duty, import VAT, other) live in stock_delivery_service_invoices, split per container in stock_delivery_service_invoice_allocations, never on the agent/supplier invoice. SyncStockDeliveryServiceInvoiceCosts writes the checklist through Store/Update/DeleteStockDeliveryCost: shipping = freight sum, duty = duty sum (org currency), one extra row per "other" bill (stock_delivery_service_invoice_id), rows flagged from_service_invoices and locked against hand edits. Import VAT is recoverable and never enters costing (Raul, 9 Oct 2026). A bill touching a costed delivery can't change money until Update costing reopens it.
