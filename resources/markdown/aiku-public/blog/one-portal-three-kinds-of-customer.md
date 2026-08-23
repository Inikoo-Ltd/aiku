---
title: One portal, three kinds of customer
summary: A trade buyer reordering forty lines, a dropshipper running a shop on someone else's platform, and a brand whose pallets we store — all log into the same customer portal, built on the same code as the staff app, living on the storefront's own domain. What each sees, what they share, why the dropshipper's order is the most complicated object in the system, and the two things the portal refuses to let a customer do.
date: 2026-06-25
tags: customer-portal, dropshipping, b2b, fulfilment, architecture
---

Every shop in aiku has a public face — the storefront — and a private one: the **customer portal**, reached from the storefront's own domain under `/app`, where a logged‑in customer does everything that is not browsing. It has been live since early 2024 and it serves three quite different people with one codebase. This note is what they have in common, what they do not, and where the complexity actually lives.

## The same bones as the staff app

The portal is the same stack as the staff application — same actions, same models, same component library — with a different layout, a different guard and a different set of routes. A "show order" screen for a customer is the staff "show order" screen with fewer columns and no buttons that move money. That is on purpose: a feature built for staff is a small step from being a feature for customers, and the two never disagree about what an order *is*.

It also means the portal inherits everything underneath: per‑line tax, the discount engine, the payment layer, search, the same stock numbers the warehouse sees. The customer is looking at the truth, formatted politely.

## Three kinds of customer

**The trade buyer** (B2B, and B2C where a shop sells direct). Catalogue with *their* prices, basket, checkout with saved cards and credit, order history with tracking, invoices, credit notes, a statement, top‑ups to a balance, favourites, back‑in‑stock reminders, a place to manage users on their account, and an API token if they want to integrate. This is the portal at its most ordinary, and it is most of the traffic.

**The dropshipper.** A customer who sells *our* catalogue on *their* channels — their own web shop or a marketplace — and has us ship to *their* buyer. Their portal has everything the trade buyer has, plus: **channels** (connect a store, see its status, reconnect), a **portfolio** per channel (which of our products they have listed there, at what price, synced or not, with a log), **clients** (their end buyers, as address books), and orders that arrived from the channel or were keyed by hand for a client. Their order is shipped under their name; their client never sees us.

**The fulfilment customer.** A brand whose goods we store. Their portal is the [pallets, stored items, deliveries, returns, and the recurring bill](/blog/a-pallet-has-twenty-states) — and, because many of them also sell online, the same channels and portfolio as a dropshipper, fulfilled from the stock we are holding for them.

The three are not three portals. A customer can be more than one kind; the navigation shows what their account has.

## The dropship order is the hardest object

An ordinary order is a customer, lines, a payment. A dropship order is a customer (the dropshipper) *and* a client (the buyer), a channel it came from, a platform order id to report fulfilment back to, a portfolio item per line that maps our product to their listing, a price the platform says the buyer paid, and a delivery address that is not the customer's. It is paid — sometimes by a saved card the instant it arrives, with no browser in the loop — and it must be picked under the dropshipper's name with the dropshipper's documents.

All of that is modelled once, in the core, and the portal is a window on it. The portal's job is to keep the dropshipper informed without ever letting the two sides blur: their client sees their brand; our warehouse sees a delivery note.

## Two things the portal refuses to do

**It never lets a customer see another customer.** Every query in the portal is scoped to the authenticated customer — and, for dropshippers, to the channel in context — before any row is fetched, the same way the staff app is scoped to an organisation. There is no "switch customer" for customers, and the API tokens carry the same scope with read/write abilities and a rate limit per token. A portal that leaks one buyer's order to another is not a portal; it is a breach.

**It never invents a number.** Prices, stock, order status, invoice totals are read from the same rows the staff see. If the warehouse short‑ships, the customer's order shows what shipped and what is owed. If the dropshipper's marketplace says the buyer paid £18.40, the order says £18.40. Nothing on the portal is a copy that can drift.

## What the customer service team gets from it

Fewer calls. Every "where is my order", "send me the invoice", "what is my balance", "reconnect my shop" is a screen the customer can open themselves; the chat widget sits in the corner for the rest, and an agent answering it sees the same screens the customer does, with the customer's context loaded. When something is wrong on the portal, the fix ships with the staff app's release that afternoon, because it is the same code.

## Next door

The next step for the portal is the one we are [designing in the open](/blog/letting-customers-order-through-their-own-assistant): the same account, reachable by the customer's own AI assistant, read first and write when management says so. The portal already draws the lines that server will have to respect.
