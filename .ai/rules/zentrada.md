---
paths:
  - 'app/Actions/Dropshipping/Zentrada/**'
---

# Zentrada

## Zentrada orders come from authenticated TradeSafe emails only
HELP-4938 (26 Nov 2026). ProcessInboundEmail hands a "Delivery order transaction" mail to ImportZentradaOrderFromEmail before any chat or label handling, but only when shops.settings.zentrada.import_orders is on, the sender is @tradesafe.eu and the TOPMOST Authentication-Results (Gmail's own; lower ones and ARC can be planted by the sender) shows dkim=pass for tradesafe.eu, or spf=pass when it was not forwarded. Never loosen this to subject or sender alone: an order ships goods billed to Zentrada. The mail reaches shop EU's mailbox (contact@awgifts.eu) through Pavol's forwarding rule. Billed to settings.zentrada.customer_reference (27227), non-EU deliveries to non_eu_customer_reference (61482). Lines at Zentrada's CSV price, D_MENGE_VE = our packs and must equal E_MENGE_STUECK / units; freight is O_FREIGHT as manual shipping. Reference "EU-NL 958559" (from the subject) is customer_reference and external_id. Hand-keyed orders never had it, so a Zentrada customer's order of the last 21 days to the same postcode with the same product codes counts as the same transaction. Unknown code, stock short, or piece price more than 1 cent off ImportZentradaOrder::feedPiecePrice: order left in creating with internal notes and a StaffTask to settings.zentrada.orders_user_id (else customer-services). Unreadable mail: task, and the mail falls through to the inbox. Address columns M01-M11 are mapped by German header names and were built before a real CSV was seen: check against a real file before switching import_orders on.

## Other TradeSafe mail is kept, never turned into an order
HELP-4938 point 7. Authenticated TradeSafe mail whose subject is not a fresh "Delivery order transaction" (cancellations, complaints, payouts, Re:/Fwd:) goes to SaveZentradaNoticeFromEmail: body (.txt) and attachments are saved on the order whose customer_reference/external_id is the "EU-XX nnnnnn" in the subject or body, else on the settings.zentrada.customer_reference customer, and the mail is filed under aiku/zentrada. Anything but a payout notice also opens a StaffTask for the orders person. It never creates an order. With nothing to keep it on it falls through to the inbox.
