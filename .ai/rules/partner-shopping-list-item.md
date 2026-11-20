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
