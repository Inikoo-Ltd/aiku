---
title: A pallet has twenty states
summary: Running a third‑party fulfilment business inside the same system as your own warehouse: pallets, boxes and oversize items that belong to someone else, stored items inside them, rental agreements with clauses, and a recurring bill that consolidates itself on the customer's cycle. Why a pallet's lifecycle needs twenty states and a separate "booked in" from "received", and how the 3PL customer sees all of it in their own portal.
date: 2026-05-20
tags: fulfilment, 3pl, warehouse, billing
---

<aside class="tldr"><strong>TL;DR</strong>Third-party fulfilment runs inside the same warehouse and system as the group's own stock. A fulfilment customer has a rental agreement with dated, snapshotted clauses; everything they send is a pallet (or box or oversize item), tracked through roughly twenty lifecycle states covering inbound, storage, outbound and incidents like damage or loss. A recurring bill accrues per transaction as things happen and consolidates into an invoice at the end of each billing cycle.</aside>

Alongside selling our own goods, we store and ship other people's. A brand sends us pallets; we book them in, keep them, pick from them, and send them back or send their contents out — and bill for the space and the work, on the brand's cycle, with an agreement behind every line. That is third‑party fulfilment, and it lives in aiku in the same warehouse, on the same racks, handled by the same pickers as our own stock. This note is how it is modelled so that the two never blur.

## Whose stuff is this

The first rule: a **fulfilment customer** is a customer like any other — same record, same portal login — with a *fulfilment* flag and a **rental agreement**. The agreement is the contract: billing cycle (weekly or monthly), the clauses (what a pallet‑week costs, what a pick costs, what a return costs, any special rate), and snapshots of those clauses over time, so a bill from March is priced by March's clauses even if the rate changed in May.

Everything the customer sends is a **pallet** — the word covers pallets, boxes and oversize items; the type is a field. A pallet belongs to a fulfilment customer, sits in a location in one of our warehouses, and may contain **stored items** (the customer's SKUs, counted) when the customer wants item‑level picking rather than whole‑pallet returns. Stored items have their own audits and deltas, because "we counted 118, you said 120" is a conversation that needs a record.

## Twenty states, and why

A pallet's state enum has about twenty values, and we have resisted every attempt to collapse them. The lifecycle, roughly:

- **Inbound**: in process → submitted → confirmed → received → booking in → booked in. *Received* means the truck came; *booked in* means a person put the pallet in a location and the system knows where it is. The gap between the two is real — a busy dock on a Monday — and the customer's portal shows it honestly.
- **Storing**: the normal state; this is what the rental clause bills.
- **Outbound**: return requested → submitted → confirmed → picking → picked → dispatched; or *not picked* when a requested pallet cannot be found on the day.
- **Incidents**: damaged, lost, other incident — first‑class states, because a lost pallet stops being billed and starts being a claim, and both sides need to see when that happened.
- **Not received**: announced, never arrived.

Each transition is an action with its own authorisation and its own effect on the bill. The states are visible to the customer with the same words we use internally; there is no "customer‑friendly" translation layer to drift.

## Deliveries and returns are the documents

A **pallet delivery** is the inbound document — what the customer says is coming, then what actually arrived, pallet by pallet, with the services (unloading, labelling) that were performed on it. A **pallet return** is the outbound one — whole pallets, or picked stored items, to an address or for collection — and it runs through the same picking sessions and packing benches as our own orders, with the same scanners and the same [blocking rules](/blog/one-predicate-to-unblock-a-delivery-note). The picker does not know, and does not need to know, whose goods they are.

## The bill that writes itself

Every fulfilment customer has a **current recurring bill**. Storage accrues onto it per pallet per period from the agreement's clauses; every delivery, return, pick and service posts a transaction onto it the moment it happens. Temporal aggregates are kept up to date so the customer can see, today, what this cycle will cost so far.

At the end of the cycle the bill **consolidates**: it freezes, becomes an invoice through the ordinary invoicing path, and the next bill is created and picks up every pallet still in storage and every space still rented. Nobody compiles anything. If a clause was wrong, the snapshot says which clause and which version; if a pallet was lost mid‑cycle, the accrual stopped on the day the state changed.

## The customer's side

The fulfilment customer logs into the same portal our trade customers use and sees: their pallets and where they are, their stored items and counts, deliveries they have announced, returns they have requested, the running bill, the invoices, and — because they are often a brand selling online — their own sales channels and dropshipping orders, which can be fulfilled from the very stock we are storing. That last link is the reason to run 3PL inside the commerce system rather than beside it: a brand's marketplace order, picked from the brand's pallet in our warehouse, billed on the brand's recurring bill, is one flow, not three integrations.

## What we learned

Model the other party's goods with the same objects and the same people as your own, and separate them by ownership, not by code path. Give a pallet as many states as the dock actually has, and show the customer the real ones. Put the contract in dated clauses and snapshot them, so every line of every bill has a sentence behind it. And let the bill accrue as things happen; the end of the month should be a consolidation, not a reconstruction.

<aside class="tldr bottom"><strong>In one paragraph</strong>Running other people's stock through the same warehouse and system as your own works cleanly once ownership, contract terms and billing are modelled explicitly rather than bolted on.</aside>
