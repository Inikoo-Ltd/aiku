---
title: Letting customers order through their own assistant
summary: The next thing we are building — an MCP server for customers, so a trade buyer or a dropshipper can connect their own AI assistant to their account and ask, browse and eventually order without an integration project. What it will do first, what it will not do until someone accountable says so, and the groundwork already in place.
date: 2026-08-24
tags: mcp, ai, retina, dropshipping, b2b
---

We already run an [MCP server for staff](/blog/an-mcp-server-for-a-whole-business). The question that followed within a week was obvious: why not for customers?

A trade customer re‑orders the same forty lines every month. A dropshipper checks stock on their portfolio before they run an ad. Both of them increasingly have an assistant open — a desktop AI client, a chat product, an agent framework — and both would rather say *"reorder last month's candles, skip anything out of stock, ship to the Leeds address"* than click through a portal. We think the businesses that let them do that early will be the ones they stay with.

This is a design note. The server is being built as we write; nothing below is live yet, and we would rather publish the rules before the feature than after.

## What it will do first: read

The customer portal already has a REST API with personal access tokens, scoped to a customer and, for dropshippers, to one sales channel. The MCP server is the same surface, in the shape an assistant can use:

- **My account** — who I am, which channels I have, balances and credit.
- **My catalogue** — the products I can buy (or, for a dropshipper, the portfolio I have listed), with *my* prices and current stock.
- **My orders** — status, tracking, what shipped short and why.
- **My invoices** — what is owed and what has been paid.

Every tool identifies things by the same slugs and references the portal shows, returns the same numbers the portal shows, and is scoped by the same token. There is no second source of truth and no separate permission system to drift.

## What it will not do until someone says so: write

Placing an order by assistant is the point, and it is also the part that needs a human decision that is not ours to make. So write tools — *add to basket, submit order, add an item for a dropship client* — are built behind a separate token scope (`write`), off by default, and each write tool's description requires explicit confirmation from the person before the assistant may call it. Management decides when and for whom the scope is issued. The read side can ship without that decision; the write side waits for it.

That mirrors how the staff server works: the boundary is structural, not a setting buried in a config file.

## Groundwork that is already in place

None of this starts from zero:

- The portal API now has **per‑token rate limits** and **read/write abilities**, with a read‑only checkbox where customers mint their tokens, and older tokens migrated to explicit scopes.
- The staff MCP server's **per‑tool permission traits, request logging and denial reporting** transfer as they are; a customer call is logged the same way a staff call is.
- The storefronts publish an `llms.txt` that already tells an assistant the honest state of things: register to see prices and order; no public ordering API yet.

## The bits we are still arguing about

- **Identity**: whether the assistant connects with a portal user's token or with a channel token, and what happens when a buyer has several channels.
- **Confirmation**: whether "the tool description says ask first" is enough, or whether the server should refuse to submit an order it has not shown back as a quote in the same conversation.
- **Abuse**: an assistant in a loop can place a thousand baskets in a minute; the rate limit exists, but the shape of a *sensible* limit for a robot buyer is different from a human one.

## Why we are doing it at all

Because a trade customer's time is the scarcest thing in the relationship, and because "no integration needed — connect the assistant you already use" is a sentence we can say before most of our competitors can. We will write a second note when the read side is live and customers have actually used it; that one will have numbers.
