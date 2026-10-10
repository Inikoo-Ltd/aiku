---
paths:
  - 'app/Actions/Dispatching/**'
---

# Dispatching

## Picking and packing are warehouse work; the order page keeps its buttons
Decided 10 Oct 2026 (INI-073), not open to exceptions per person or per shop type.
- Warehouse screens (warehouse delivery note page, picking, packing, packing states, finalise and dispatch from the note, trolleys, picking sessions, scan to pick/pack, undo dispatch) are warehouse staff only. The rule is `DeliveryNote::canBeWorkedOnBy()`: dispatching edit, dispatching supervisor, returns, organisation admin. Apply it through `WithDeliveryNoteWorkAuthorisation` (it also resolves the note from a `deliveryNoteItem` or `picking` route parameter); warehouse-level screens use `Warehouse::canBeWorkedInBy()`. Do not add `orders.{shop}.edit` to either. Customer service who need these ask their manager for a warehouse position.
- Every button the order page (`ShowOrder`) had stays, and staff with `orders.{shop}.edit` can press it: create or delete a shipment, print its label, get the shipment from the platform, retry shipping fields, mark a finalised note dispatched, generate invoice. The rule for those is `DeliveryNote::canBeShippedBy()` (warehouse rule or orders edit), through `WithDeliveryNoteShipmentAuthorisation` / `WithShipmentWorkAuthorisation`. It is the same for every shop; there is no Faire special case.
- Customer service also keep the waiting-for-customer-service actions (do not pick, replace product, send back to warehouse) via `WithOrdersOrDispatchingAuthorisation`.
- A button must not be shown to someone the action will refuse: pages send `can_work` / `is_editable` computed from the same rule as the action.
