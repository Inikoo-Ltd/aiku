---
paths:
  - 'app/Actions/Procurement/**'
---

# Actions Procurement

## Agent warehouse: read what is held through AgentWarehouseStock::heldQuantitySql, never quantity
INI-206 (5 Nov 2026). Nothing writes agent_warehouse_stocks when goods go in a container or the container is dispatched, so a row's quantity is what was received, not what the agent still holds. Held = quantity minus the units of the same purchase_order_transaction_id on live stock delivery items (any state but cancelled/not_received, staged or dispatched), taken from the line's packed rows first, then its oldest, so a later goods receipt for the rest of a line stays held and an unpacked lot is not hidden by a packed one that shipped. PO stages keep reading the raw quantity for the packed date. Every reader of "what the agent holds" (Warehouse screens, backlog, ready checks, stages, packable quantities) uses AgentWarehouseStock::heldQuantitySql() / whereHeld() / heldQuantityColumn(). Do not add a write path that decrements rows on dispatch: undispatch, cancel and item edits would all need to restore it.

## Ordering suggestions skip seasonal SKOs outside their tag's ordering months
HELP-4598 (10 Nov 2026). A product-property tag can carry an ordering season (tags.order_from_month / order_to_month, 1-12, may wrap the year end; set on the tag edit page). Tag::outOfOrderingSeasonSql($stockIdColumn) is true for a stock whose trade units or their families carry seasonal tags, none in window this month. Every query that proposes what to order applies it next to is_excluded_from_auto_ordering: SuggestPartnerShoppingList::candidates, GetPartnerStockCoverBuckets::rescuableQuery (reason out_of_season in leftOut), SuggestAgentShoppingList, SuggestSupplierShoppingList; HubOrderPlanningTool reports out_of_ordering_season. A new suggestion path must apply it too. Adding by hand, bucket counts and the recommended-quantity chips are deliberately not gated.
