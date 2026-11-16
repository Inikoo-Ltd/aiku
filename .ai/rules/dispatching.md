---
paths:
  - 'app/Actions/Dispatching/**'
---

# Dispatching

## Picking and packing are warehouse work; the order page keeps its buttons
Decided 10 Oct 2026 (INI-073), not open to exceptions per person or per shop type.
- Warehouse screens (warehouse delivery note page, picking, packing, packing states, finalise and dispatch from the note, trolleys, picking sessions, scan to pick/pack, undo dispatch) are warehouse staff only. The rule is `DeliveryNote::canBeWorkedOnBy()`: dispatching edit, dispatching supervisor, fulfilment edit, returns, organisation admin. Apply it through `WithDeliveryNoteWorkAuthorisation` (it also resolves the note from a `deliveryNoteItem` or `picking` route parameter); warehouse-level screens use `Warehouse::canBeWorkedInBy()`. Do not add `orders.{shop}.edit` to either. Customer service who need these ask their manager for a warehouse position.
- Every button the order page (`ShowOrder`) had stays, and staff with `orders.{shop}.edit` can press it: create or delete a shipment, print its label, get the shipment from the platform, retry shipping fields, mark a finalised note dispatched, generate invoice. The rule for those is `DeliveryNote::canBeShippedBy()` (warehouse rule or orders edit), through `WithDeliveryNoteShipmentAuthorisation` / `WithShipmentWorkAuthorisation`. It is the same for every shop; there is no Faire special case.
- Customer service also keep the waiting-for-customer-service actions (do not pick, replace product, send back to warehouse) via `WithOrdersOrDispatchingAuthorisation`.
- Undoing a dispatch is `canBeUndispatchedBy()` (dispatching supervisor or organisation admin) from the delivery note and from the order page (`Order::canBeUndispatchedBy()`, every note of the order). Accounting edit does not grant it.
- Trolley and picked bay pages open with a permission on that warehouse (dispatching, fulfilment or locations view), or for the organisation's admins, through `WithDispatchingEquipmentAuthorisation`; creating and editing them stays with locations edit. Organisation-wide inventory permissions do not open them.
- A button must not be shown to someone the action will refuse: pages send `can_work` / `is_editable` computed from the same rule as the action.

## Staff notes on a delivery note stay editable by customer service
HELP-3879 (12 Oct 2026). The warehouse note and shipping label note (`DeliveryNote::STAFF_NOTE_FIELDS`) are how customer service tell the warehouse what to check, e.g. on a replacement. Rule: `DeliveryNote::canBeNotedBy()` (warehouse rule or `orders.{shop}.edit`). `UpdateDeliveryNote` lets a non-warehouse user through only when the request carries nothing but those fields; any other field, or an empty request, still needs `canBeWorkedOnBy()`. The note boxes' `editable` flag uses the same rule.

## A dispatched delivery note keeps its shipment; channel tracking is sent once
PRE-73 (6 Nov 2026). DispatchOrder sends the shipment's tracking to eBay/Shopify once, at dispatch, and nothing resends it. So staff cannot add or remove a shipment on a dispatched note (`DeliveryNote::areShipmentsLocked`, checked in StoreShipment for user requests and in DeleteShipment::asController; the panel reads `shipper_directive.shipments_locked`, label printing stays). A wrong label is fixed by undispatching first (supervisor; the invoice must be deleted first). Shippers with `settings.supervisor_only` (APC (manual tracking), the backup for when the APC API is down) are hidden from GetShippers and refused by StoreShipment unless the user is a dispatch supervisor or org admin. On eBay/Shopify orders (`trackingChannel`) a manual shipper needs a tracking number, and the panel warns that it goes to the channel.

## An external product linked to its stock late gets its open order lines put on the delivery note
HELP-4696 (13 Nov 2026). A marketplace order line whose product has no SKO yet gets no delivery note item (WithDeliveryNoteLineCreation::createDeliveryNoteItem loops over zero org stocks), and the order was packed, invoiced and dispatched without it, stock never moved. UpdateTradeUnitsForExternalProduct now runs StoreMissingDeliveryNoteItemsOfProduct: every line of the product on an order still in the warehouse (in_warehouse to packed) with no delivery note item gets one, and a picked or packed note walks back to picking. It only acts on lines with no item at all; it never requantifies existing items. Not covered: an SKO linked after dispatch, and products shipped with no SKO on purpose (samples, testers), so never block dispatch on a line without an SKO.
