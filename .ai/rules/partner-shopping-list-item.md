---
paths:
  - 'app/Actions/Procurement/PartnerShoppingListItem/**'
  - 'app/Actions/Procurement/OrgPartner/**'
---

# Partner Shopping List Item

## A hub basket splits into production and rescue lines by the seller's artefacts
HELP-4842 (20 Nov 2026): a line is a production line when the seller has a live artefact for that SKO (PartnerShoppingListItem::madeBySellerSql / isProductionLineSql / whereOrderKind); every other line is a rescue line, i.e. stock the hub only holds (e.g. ES buys olive oil soaps from Camacho). Auto-fill (SuggestPartnerShoppingList::candidates) and Prepare order / the hub's To order page (GetPartnerStockCoverBuckets::rescuableQuery, hub branch) offer production lines only; rescue lines are added by hand. The basket's "Order" filter and SubmitPartnerShoppingList (order=production|rescue) send one kind at a time. Hubs still take no purchase orders: a rescue line goes through the same basket, never a PO.
