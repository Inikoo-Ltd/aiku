---
paths:
  - 'app/Actions/Dropshipping/Ankorstore/**'
---

# Ankorstore

## Ankorstore orders land in the EU shop, billed to the retailer
HELP-4762 (17 Nov 2026). No Ankorstore shop: GetAnkorstoreOrders imports into the shop holding settings.ankorstore (EU), sales channel `ankorstore`, at the shop's own prices, shipping and charges, topped up with the shop's discretionary charge to what Ankorstore pays (brandTotalAmount, before VAT), as staff keyed them by hand; Ankorstore paying less goes to staff. The customer is the retailer, found by email, then by VAT number (with or without the country prefix: staff often keyed retailers under another email), else created. The order is left TO_BE_PAID_BY_MARKETPLACE; a payment staff record later pays it. Staff-keyed orders carry the Ankorstore number as the payment reference or customer_reference, and the importer skips those (soft-deleted orders included). The payment is often recorded days after the order, so an order that does not fit is marked handed_to_staff in settings.ankorstore.skipped_orders and never imported later, even when the cause clears. Quantity: Ankorstore `quantity` is our packs when the product's unitMultiplier is 1 or equals our units; anything else is skipped to staff, never guessed. Tracking goes back at dispatch (DispatchOrder → ShipAnkorstoreOrder: ship/custom with the parcels, then confirm the quote with the tracking). ship/custom is missing from the current published spec but used by the Ankorstore UI; prove it on the sandbox. Writes (accept, ship) run only in production, the sandbox or tests: the credentials travel with every DB copy.
