---
paths:
  - 'app/Actions/Procurement/PartnerShoppingListItem/**'
  - 'app/Actions/Procurement/OrgPartner/**'
---

# Partner Shopping List Item

## A hub basket splits into production and rescue lines by the seller's artefacts
HELP-4842 (20 Nov 2026): a line is a production line when the seller has a live artefact for that SKO (PartnerShoppingListItem::madeBySellerSql / isProductionLineSql / whereOrderKind); every other line is a rescue line, i.e. stock the hub only holds (e.g. ES buys olive oil soaps from Camacho). Auto-fill (SuggestPartnerShoppingList::candidates) and Prepare order / the hub's To order page (GetPartnerStockCoverBuckets::rescuableQuery, hub branch) offer production lines only; rescue lines are added by hand. The basket's "Order" filter and SubmitPartnerShoppingList (order=production|rescue) send one kind at a time. Hubs still take no purchase orders: a rescue line goes through the same basket, never a PO.

## Partner All products list: no cover means all, hide-ordered ignores draft lines
HELP-4859 (21 Nov 2026). IndexPartnerCoverBucketItems (shopping/items, "All products" on the Basket) lists every SKO the partner sells when no valid `cover` is passed; it no longer falls back to Out of stock. Ids, chip counts and order all come from one query, GetPartnerStockCoverBuckets::stockRows. `hide_ordered` uses sentOrComingExpression, not alreadyComingExpression: a DRAFT basket line is not "on order", otherwise the row vanishes while the buyer types its quantity. Adding to the basket from this list reaches the seller only on Submit.

## A buyer line the partner cannot make notifies its adder and leaves the default Orders list
HELP-4906 (24 Nov 2026). Any new code that sets a buyer's partner line (org_partner_id set) to DISMISSED because the hub won't make it must call NotifyPartnerLinesCannotBeMade: whoever added the line (added_by_user_id, still holding procurement.{buyer}) is told on their UserNotificationEnum::PARTNER_LINE_CANNOT_BE_MADE channels (email by default), and the rest of the buyer's procurement team gets the bell only. DismissToProduceItems and PrePickPartnerShoppingListItems (closed short) call it today. The Orders (sent) view hides DISMISSED lines unless the "Cannot be made" state chip is picked (IndexPartnerShoppingListItems::DEFAULT_SENT_STATES, the state filter's default).
