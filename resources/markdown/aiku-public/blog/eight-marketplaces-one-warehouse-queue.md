---
title: Eight marketplaces, one warehouse queue
summary: Shopify first, in 2024; then WooCommerce, TikTok Shop, Magento, Amazon, eBay, Allegro, Wix, and a wholesale marketplace with its own rules. Two years of connecting other people's platforms to our warehouse — the model that survived all of them (platform, channel, portfolio), the lesson about who owns the truth, and the small ugly things that each platform taught us.
date: 2026-08-10
tags: dropshipping, shopify, marketplaces, integrations, warehouse
---

The first external platform we connected was Shopify, in July 2024. A customer of ours had a Shopify store; they wanted to list our products, take the order, and have us ship it to their buyer under their name. That is dropshipping, and it is simple to describe and full of edges to build.

Two years later there are eight platform types in the enum — Shopify, WooCommerce, TikTok Shop, Magento, Amazon, eBay, Allegro, Wix — plus a "manual" channel for customers who want to key orders themselves, plus a wholesale marketplace whose orders land through the same door. Every one of them was an odyssey. This note is what stayed constant and what each one taught us.

## The model that survived

Three objects, unchanged since the first integration:

- **Platform** — the kind of thing out there: Shopify, eBay. Holds the API shape, the auth dance, the webhook topics.
- **Customer sales channel** — *this customer's* connection to *that platform*: their store, their token, their status, their settings. A customer can have several.
- **Portfolio** — the products this customer has chosen to sell on this channel, each with the platform's own identifier for it, its price there, and a sync state.

Orders from a channel become ordinary orders in the customer's account, with the end‑buyer as a *customer client*, and then they are ordinary delivery notes in the warehouse. **The picker cannot tell a TikTok order from a phone order**, and that is the single most important design property: every platform we add costs us an adapter, never a new warehouse flow.

## Who owns the truth

The rule we learned slowest and now hold hardest: **for a marketplace order, the marketplace owns the money and the relationship; we own the box.**

Shopify tells us the fulfilment order; we fulfil it and tell Shopify, and we do not invent a price. Our wholesale‑marketplace shops are typed *external*, and our discount engine returns early for them — not at the call site, in the engine, because nine callers reach it — so none of our own pricing rules can ever rewrite what the marketplace said the buyer paid. A buyer who edits an order on that marketplace, whatever state our delivery note is in, is accepted: the note walks back (unpack, undo pick, back to handling) in one locked transaction rather than refusing. And there is deliberately **no return button** on a dispatched marketplace order in our app, because a return raised only on our side makes the two systems stop agreeing; returns are raised where the order lives. Goods can still come back to the warehouse — the box is ours — but the money is theirs.

## What each one taught us

**Shopify** taught us webhooks are a contract with fine print. Fulfilment‑order webhooks include line items with a remaining quantity of zero — edits, refunds, lines reassigned to another location — and for a while we ingested them verbatim, producing delivery‑note items a picker could not get past. Now ingestion skips them and the pick actions auto‑ignore any zero‑quantity line left over. Shopify also taught us to build the privacy webhooks (customer data request, customer redact, shop redact) *before* the app review, not after, and that a channel needs a *check* and a *reset* because tokens expire and merchants reinstall.

**WooCommerce and Magento** taught us that self‑hosted platforms come in as many versions as there are customers, and that a "connection check" that actually calls the store is worth more than any amount of onboarding copy.

**TikTok Shop** taught us onboarding as a product: a dedicated flow in the customer portal, because the platform's authorisation is a multi‑step dance and a customer who gets lost in it never comes back.

**Amazon and eBay** taught us that listing is the hard half. Our product has a name and a price; their listing wants a category, a policy set (shipping, returns, payment), item specifics, and an identifier scheme that is not ours. eBay got its own policy management and a repair action for the cases where our portfolio data and theirs had drifted apart — because they will drift, and the honest design admits it.

**Allegro** taught us that a new marketplace is mostly the previous ones again, if the model held. It came in fastest.

**The wholesale marketplace** taught us everything in the "who owns the truth" section, usually by a ticket from customer service titled "amount is off". One bulk update, run without a guard, rewrote the history of a few hundred dispatched lines; that is how the guards got written.

## The parts that are the same everywhere

- Every channel has a **status** and a **check** action; a failed check is visible to the customer and to us.
- Every portfolio item has a **sync state** and a **log**; "why isn't my product showing" is answered from a screen, not a database.
- Every platform's orders feed the same **time series**, so the group dashboard can say what each platform brought in this month next to the shops.
- Every adapter is **tests against the real database**, with recorded platform payloads, because the payloads are where the surprises live.

## What we would tell someone starting

Model platform, channel and portfolio on day one and never let a platform leak into the warehouse. Decide, per platform, who owns the money and write it into the engine, not the edge. Assume tokens expire, merchants reinstall, buyers edit, and webhooks contain zeros. And keep a list of what each one taught you — the next marketplace will ask the same questions, and the answers are already there.
