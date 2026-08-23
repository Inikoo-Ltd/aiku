---
title: Eight carrier APIs and a manual button
summary: From the packing bench, one tap books the shipment, prints the label and writes the tracking number onto the delivery note — for eight carrier integrations across four countries, each with its own dialect: SOAP here, REST there, XML that breaks on an ampersand, a service that refuses more than one parcel, cash‑on‑delivery that must go on exactly one box. What the shipment object has in common everywhere, what each carrier taught us, and why every integration ships with a "manual" twin.
date: 2026-07-30
tags: shipping, warehouse, integrations, dispatch
---

At the end of the packing bench there is a button. The packer has weighed the boxes; they tap it; a label prints, a tracking number appears on the delivery note, and the customer's dispatch email goes out with a link they can click. Behind that tap is one of **eight carrier API integrations** — DPD in two countries, GLS in two, APC, CTT, Packeta, a regional courier — and 72 shipper records in the database, because the same carrier is a different contract, account and dialect in each country. About 60,000 shipments have gone through them. This note is what is common, what is not, and the rules we hold.

## The shipment object

Every carrier call starts from the same thing: a **delivery note** with its boxes (count, weight, dimensions), the delivery address, the customer's contact details, the shop's sender details, the declared value, and whether the order is **cash on delivery** and for how much. It ends with the same thing: a **shipment** row — shipper, reference, tracking number(s), the label as a PDF, a state (*in process, success, fixed, error*) and the raw response kept for the day someone asks. The note's dispatch email reads the tracking link from the shipment; the customer's portal shows it; the marketplace (when the order came from one) is told.

The carrier‑specific part is one class per integration: *build the request from the shipment object, call, parse the response into tracking + label + errors*. Everything else — the bench button, the PDF merge, the retry, the state — is shared.

## What the carriers taught us

Each one has a paragraph because each one had an afternoon.

**Ampersands.** A SOAP carrier's XML body was assembled from customer data; a company name with an `&` made the body invalid and the booking failed — for what looked, from the bench, like random customers. Every value now passes through an XML escape. Obvious in hindsight, invisible for months.

**One service will not take two parcels.** A carrier's international service refused any shipment with more than one parcel — zero successes in four months on multi‑parcel international, while staff quietly hand‑keyed two hundred of them in the carrier's portal. The gate is now explicit: for those destinations, one shipment per parcel; domestic keeps one multi‑parcel shipment, because that service accepts it.

**Cash on delivery goes on one box.** On a multi‑parcel COD order, the money must be collected once. One carrier's API keys COD per parcel and would happily collect *n* times if asked for *n* parcels with an amount each; the rule is *full amount on the first parcel, none on the rest*, and a live test with two boxes confirmed box one printed the amount and box two printed nothing. The open question we wrote down rather than guessed: does that carrier treat the two as one consignment or two for delivery and billing — we asked them instead of assuming.

**Labels overwrite each other.** A response with several label nodes used to keep only the last; the PDFs are now merged, and the parsing lives in a function that can be tested offline against saved responses — including the one where the label node sits outside the namespace the rest of the document uses.

**Reference length, incoterms, currencies.** Each API has a maximum reference length, a mandatory customs block for some destinations, and an opinion about which currencies a COD amount may be in. Those are on the list per carrier, with the business decision each one needs, because an incoterm is not something engineering chooses.

## The manual twin

Every API integration has a **manual** shipper beside it: same carrier, no API. When the API is down, when a destination or service is not covered, when the account is being renewed, the packer picks the manual one, books in the carrier's portal, and types the tracking number into the same field. The shipment object, the dispatch email and the portal do not know the difference. A carrier API that fails must degrade to a keyboard, never to a stuck order.

## Where the numbers live

Each integration's successes and errors over time are a report; the "manual" shipper's volume is a signal — when it rises for a carrier that has an API, something in the API path is failing quietly, and that is how the multi‑parcel refusal was found.

## What we would tell a team

Model the shipment once and make carriers adapters over it. Escape everything. Treat "one parcel", "one COD amount" and "one consignment" as three different questions with carrier‑specific answers. Keep every raw response. Parse offline against fixtures. And give every API a manual twin, because the courier's server will be down on the day the warehouse is busiest.
