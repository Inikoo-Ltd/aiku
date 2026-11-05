---
paths:
  - 'app/Actions/Procurement/**'
---

# Actions Procurement

## Agent warehouse: read what is held through AgentWarehouseStock::heldQuantitySql, never quantity
INI-206 (5 Nov 2026). Nothing writes agent_warehouse_stocks when goods go in a container or the container is dispatched, so a row's quantity is what was received, not what the agent still holds. Held = quantity minus the units of the same purchase_order_transaction_id on live stock delivery items (any state but cancelled/not_received, staged or dispatched), taken from the line's packed rows first, then its oldest, so a later goods receipt for the rest of a line stays held and an unpacked lot is not hidden by a packed one that shipped. PO stages keep reading the raw quantity for the packed date. Every reader of "what the agent holds" (Warehouse screens, backlog, ready checks, stages, packable quantities) uses AgentWarehouseStock::heldQuantitySql() / whereHeld() / heldQuantityColumn(). Do not add a write path that decrements rows on dispatch: undispatch, cancel and item edits would all need to restore it.
