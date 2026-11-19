---
paths:
  - 'app/Actions/Dispatching/PartnerShipment/**'
---

# Partner Shipment

## Customs invoices of a partner truck are never invoices
Raul, 19 Nov 2026 (HELP-4482). The customs invoices of a truck to a partner (AROES0077...) are customs-only paper re-adding goods already sold on the real AR/ACAi shop invoices. They live only in partner_shipments, partner_shipment_delivery_notes, partner_customs_invoices and partner_customs_invoice_lines; references come from the OrgPartner customs_invoice SerialReference (AROES seeded at 76 for the two made by hand). Never create Invoice/InvoiceTransaction rows for them, and never add them to sales, accounting or dashboard reports: revenue would count twice. A consolidated delivery note (partner_shipments, AROES-C0001; named "truck" only in code) lives in warehouse Dispatching, not Procurement (procurement is buying): StorePartnerShipment puts packed or finalised notes on it; StorePartnerCustomsInvoices makes its customs invoices only once every note is invoiced, freezing lines and header (partner_shipments.data) in English; the XLSX export reads only those, never live notes, shop or stock flags. DispatchPartnerShipment dispatches every note through DispatchDeliveryNote; it never finalises (finalising creates the sales invoice). Nothing is deleted: VoidPartnerShipment keeps numbers and lines and frees the notes and invoices (unique pivots). A note on a dispatched (not void) consolidation cannot be undispatched (UndispatchDeliveryNote refuses, Raul 19 Nov 2026: fix such cases by ticket).
