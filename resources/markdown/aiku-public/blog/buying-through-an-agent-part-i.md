---
title: Buying through an agent — Part I
summary: Four sales hubs in Europe buy most of their stock from suppliers in Asia through agents — commission‑based intermediaries who consolidate everyone's purchase orders, pay deposits, and ship one container. The procurement module that models that is built and being migrated, not yet live. Part I is the design: suppliers as group master data, the agent's "mini kingdom", a purchase order that splits per supplier, a stock delivery that is a delivery note in reverse, a cross‑organisation shopping list with MOQ bars, deposits as "money in the air", and a command‑and‑control board built for the fights.
date: 2026-08-19
tags: procurement, supply-chain, purchase-orders, design
---

<aside class="tldr"><strong>TL;DR</strong>Four European hubs buy from Asian suppliers through commission-based agents. The new procurement module models suppliers as group data with the agent owning its own supplier records, splits a hub's purchase order per supplier, treats a stock delivery as a delivery note in reverse, adds a cross-organisation shopping list against MOQ, and tracks deposits as money paid before goods arrive. It is built and tested against real data but not yet live — this is the design, Part II is the results.</aside>

This is the last module to leave the previous system, and the one with the most people in it who are not in the same building. It is built, it is tested against the real data in a local migration, and it is not yet switched on in production. So this is Part I — the design and the decisions — and Part II will be the numbers after it has run for a season.

## The shape of the business

Four sales hubs in Europe buy the bulk of their range from suppliers in Asia. They do not buy from the suppliers directly. They buy through **agents**: commission‑based intermediaries, usually in the supplier's country, who take purchase orders from several hubs, consolidate them into one order per supplier, pay the supplier a deposit, chase production, and put everything into a container. An agent is a small business of their own; some are excellent; all of them, in the words of someone who has dealt with them for years, "sometimes don't care".

Everything in the module follows from that sentence.

## Suppliers are group data; the agent has a mini kingdom

A supplier's identity — code, name, contacts, address, currency — is **group master data**, created once at the supply‑chain level. An organisation does not create suppliers; it *adopts* them, and its only local field is the status of its relationship. That stops four hubs from holding four spellings of the same factory.

The exception is deliberate: an **agent's** organisation may create and edit *its own* suppliers, with the full identity form, because the agent is the one who knows them. Other organisations can see those suppliers and order from them — through the agent — but never edit them. The rule in one line: *identity belongs to whoever owns the relationship; everyone else adopts.*

## A purchase order that splits

A hub raises a **purchase order** to its agent. At submission the order is **split per supplier** into *agent‑supplier purchase orders* — one per factory under that agent — each with a public reference of the form `PO‑reference.supplier‑code`, and every line on the hub's order points at the line on the agent's order that fulfils it. That link is the whole audit trail: what the hub asked for, what the agent actually ordered from whom, and later what arrived. The previous system held it in one column; the migration reconciled 49,000 of those links to the row, 100% per organisation, before we trusted it.

The agent then consolidates many hubs' orders and sends **one stock delivery** to each hub — many‑to‑many with the purchase orders it covers.

## A stock delivery is a delivery note in reverse

Outbound, a delivery note is picked, packed and dispatched. Inbound, a **stock delivery** is dispatched, received, checked, and *sown* into locations — including splitting one line across several bays, with undo. Same state‑machine discipline as dispatch; the counters on the delivery keep the purchase orders' delivery state in step line by line, matched by stock item, and when a delivery is received the purchase order settles itself. The under‑ and over‑delivered lines get their own tab, because that is the conversation with the agent next week.

Costing is parallel to the lifecycle, never a state: agent invoice, shipping, duty, extras, on a checklist that says when a delivery is fully costed. *Placed* is the last state; *costed* is a separate fact.

## The shopping list — only possible in one database

The previous system had one database per organisation, so "are we all near the minimum order quantity for that supplier?" was a conversation on a chat app. aiku has one database, so it is a **shopping list**: any hub adds a line — supplier product, quantity, priority, needed‑by — and the agent sees, per supplier, the demand *across all hubs* with a progress bar against the supplier's minimum carton order. The agent cherry‑picks lines into each hub's purchase order; submission does the per‑supplier split; every line links to its transaction.

Three decisions in it are about people, not data:

- Quantities are canonical in **units**, with the pack and carton sizes snapshotted when the line is added, because suppliers repack cartons and we have been burned by a pack size changing under a live quantity before.
- A partial pick **splits the line and the remainder keeps its original timestamp** — queue seniority is a fairness property and people notice.
- The agent is commission‑based and must not decide too much: the agent can only **propose** dismissing a line, with a mandatory reason, and the hub accepts or reinstates. Hubs own *what* is wanted; the agent owns *when and how* to hit the minimum. Every state change is timestamped and attributed, "because they will fight".

## Deposits: money in the air

Agents pay supplier deposits, sometimes before the hubs have funded them; hubs fund them in a weekly consolidated request, in whatever currency the agent asks. Management's worry is the gap: money paid out against goods not yet received. So deposits are modelled as their own objects — per agent order, with a supplier‑facing state and a funding state that are allowed to disagree — and applied to stock deliveries as they arrive, split across partial shipments. The report management asked for is one number per agent and currency: **deposits paid, nothing received yet**.

## Command and control

On top of all of it sits a board: stalled agent orders by age, deposits at risk, per‑agent scorecards (open orders, confirmed‑to‑received lead time, ordered versus delivered), and one signal that exists because of real history — *hub order submitted and confirmed, deposit taken, and no supplier order placed after N days*. Every purchase order carries its own estimated delivery date from the supplier's lead time, so "delayed" means *past its own promise*, not past a fixed bucket.

## What is not in Part I

Payment accounts for agents (deposits talk to accounting by hand for now); an exchange‑rate capture when a deposit and a delivery are in different currencies; barcodes and picking sessions at goods‑in, which we have decided not to build until someone at the dock asks. And the results — lead times, deposit exposure, how many fights the timestamps settled — which is Part II.

<aside class="tldr bottom"><strong>In one paragraph</strong>The procurement module is built and locally tested against real data, modelling suppliers, agent-split purchase orders, reverse delivery notes and deposits as their own risk objects — but it stays unswitched until Part II reports how it performs on the floor.</aside>
