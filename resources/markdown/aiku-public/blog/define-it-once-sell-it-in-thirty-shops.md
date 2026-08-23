---
title: Define it once, sell it in thirty shops
summary: The group catalogue — master products, master families, master departments — is where a thing is described once and pushed to every shop that sells it. Majors and minors for multi‑currency prices, a "follow the master" switch at three levels, the scalar column we are retiring because it silently mixed currencies, and a price cascade that ran for a month multiplying RRPs by pack size before a repair put 1,635 values back.
date: 2026-07-27
tags: catalogue, masters, pricing, currencies, architecture
---

A group that runs thirty shops in a dozen countries has a problem that a single‑shop business never meets: the same candle exists thirty times, with thirty names, thirty prices, thirty descriptions, and someone has to keep them honest. The answer in aiku is a **group catalogue** — *masters* — sitting above the shops. A master product is the thing described once; a shop product is that thing as sold here. This note is about how the two stay in step, and the three lessons that cost us something.

## The shape

Masters mirror the shop catalogue one level up: **master departments → master families → master products**, owned by a **master shop** (a group‑level catalogue that several real shops hang off). A master product points at its trade units (the [triangle](/blog/the-triangle-trade-units-products-and-stock) starts here), carries the canonical name, description, images, ingredients and safety text in the source language, and — the part people care about — **prices and RRPs per currency**.

A shop product linked to a master *follows* it: name and description flow down (translated), images flow down, and price flows down unless told otherwise. Create a master product and the shops that should carry it get their product minted with the master's values; edit the master and the children are updated through a hydrator that knows which fields are followed.

## Majors and minors

Prices live on the master as a per‑currency map: `{GBP: 4.68, EUR: 5.52, PLN: …}`. Two or three currencies are **majors** — set by hand by the pricing team. The rest are **minors** that *follow* a major at an agreed rate: złoty follows euro at a rate management announced, and so on. Change the major and every minor recalculates; change the agreed rate and a background pipeline recomputes every master in that currency, cascades to the shops, reindexes search, reprices the open baskets, and reports progress on the screen while it runs. A single currency value can be marked *independent* — a deliberate hand‑set exception that the cascade will never overwrite — and the independence is visible, audited, and rare.

Eleven thousand master products recompute in about three minutes; the baskets take one more.

## Follow the master, or don't, at three levels

Sometimes a shop really does need its own price. The switch exists at three levels — the shop ("this shop does not follow master pricing"), the family, the product — enforced in the one cascade that writes prices, with rejoin when the switch is turned back off. A master‑side report lists every "rebel" product so the pricing team can see who has opted out.

Then we measured. Declared rebels: one family and seventy products. **Products silently diverging from their master: 33,897**, eleven per cent of the followers — not policy, rot, accumulated over years of edits that did not go through the cascade. That audit is why master price editing went from "a screen" to "a project": the cascade is now the only writer, every write is audited per product with the user who caused it, and a repair reconciles the drift in batches with a report before it touches anything.

## Three lessons, paid for

**Never let a side effect write a price.** An editor changed only *descriptions* in a family bulk‑edit screen; a side effect quietly rewrote the family's master prices with no audit entry. Nobody noticed until a customer did. Now bulk edits of text cannot reach a price field, and pricing deliberately stays out of bulk edit altogether.

**Know what basis a number is on.** A cascade treated RRP totals as per‑unit and multiplied by the pack size — once per run, so a product with twelve in a pack went ×12, then ×144. It ran on every cascade for a month. The fix was one argument name; the repair rewrote 1,635 values after proving each was within a tolerance of the major × exchange rate, and skipped anything independent, discontinued or opted out. Four thousand ambiguous values went to a human list.

**Retire the column that lies.** The master still had a scalar `price` from the single‑currency days. It held a value in the master shop's *base* currency — which differs per master shop — so reading it as "the price" silently compared pounds with euros. It is deprecated in the code, never read in new paths, and will be dropped. A column whose meaning depends on which row you are on is not a column, it is a trap.

## What the catalogue team gets

One place to name, describe, photograph and price a thing; a cascade that takes it to every shop that should have it, in every currency, with an audit line per product; an explicit, visible way to say "not this one"; and reports that say when reality drifted from the rule. The thirty candles are one candle again.
