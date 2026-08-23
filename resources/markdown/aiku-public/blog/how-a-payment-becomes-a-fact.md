---
title: How a payment becomes a fact
summary: A card payment arrives three ways at once — the browser says "done", the redirect says "done", and a webhook says "captured" — and only one of them is money. The payments layer we built this summer: server‑verified status, one processing path, locked idempotency, a recoverable state machine, a gateway log for every event, a sweeper, and a playbook that made the next provider a week's work.
date: 2026-07-24
tags: payments, accounting, architecture, webhooks
---

<aside class="tldr"><strong>TL;DR</strong>A payment arrives via browser callback, redirect and webhook, but only a server-verified <em>captured</em> status counts as money. Every path funnels into one processing method keyed to an <code>api point</code>, deduped by provider reference under a row lock, with failure recoverable but success final. Every webhook lands in a gateway log with a terminal status, and a sweeper recovers stuck payments every thirty minutes. The same shape, written down as a ten-rule playbook, let a second provider ship in a week.</aside>

Taking a card payment online looks like a widget and a callback. Recording it correctly — so that the warehouse ships exactly the orders that were paid, once, and the accountant can trace every penny back to an event — is an architecture. This summer we rebuilt ours around a modern card provider, then used the same shape for a buy‑now‑pay‑later provider for trade customers, and wrote down the rules so the next one is cheap. This is that architecture.

## Three messengers, one truth

For a single payment, three things tell us it happened: the browser widget's *completed* callback, the redirect back to our success page, and — some seconds later — the provider's server webhook saying the amount was **captured**. Only the last one is money. The first two are the user experience.

So the rule at the root of everything: **never trust a browser as proof of payment.** Every path, including the webhook, re‑fetches the payment from the provider's API and only a *captured* status counts. An *authorised* payment is a promise, not cash, and is treated as pending.

## One door

All three messengers funnel into the same method: *process successful payment for this api point*. An **api point** is our record of "a payment attempt for this order (or top‑up), from this page view", with a state — in process, success, failure — and a jsonb bag of provider details. The checkout page mints one per view; the provider session carries its id in the metadata, so every event that comes back can be bound to the attempt it belongs to, and an event that points at the wrong attempt is refused.

## Idempotency you can explain

Inside a database transaction, lock the api point row, then look for an existing payment with this provider reference for this shop's account. Same reference → already recorded, do nothing. Different reference on the same api point → record it; a second distinct charge must be visible, not hidden, because visibility is how a refund gets issued. The dedupe key is the provider's reference, never "is the api point already successful" — the two questions are different.

## A state machine that forgives

*Failure is recoverable; success is final.* A declined attempt followed by a successful retry flips the api point from failure to success. A late failure message arriving after success is ignored — the handler re‑checks under the lock before writing. The order is submitted to the warehouse only from the *creating* state, and a captured payment arriving for a cancelled order raises an alert rather than shipping a box. Submission happens **after** the payment transaction commits: recorded money is never rolled back by a downstream hiccup.

## Every event, logged

Every inbound webhook lands in a **gateway log** table — received, pre‑processed, processed — with a terminal status: ok, not applicable, failed, duplicated. Duplicates defer only to *older* rows, so two copies of the same event can never both defer to each other and vanish. *Failed* rows plus an alert are the human review queue, and a log can be replayed by id from the command line. When an accountant asks "what happened to this payment", the answer is a row, in order, with timestamps.

## The sweeper

Webhooks arrive late, clients close tabs, networks drop. Every thirty minutes a sweeper looks for api points stuck in progress between thirty minutes and forty‑eight hours old, asks the provider by reference, and recovers any captured payment through the same idempotent success path — trusting only payments whose metadata names the attempt it is looking at. An abandoned basket is queried once, then marked. Nothing is lost because a message was.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The gateway log table is modelled by <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Accounting/PaymentGatewayLog.php">app/Models/Accounting/PaymentGatewayLog.php</a>.</li>
<li>The api point per order lives in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Accounting/OrderPaymentApiPoint.php">app/Models/Accounting/OrderPaymentApiPoint.php</a> (top-ups have their own: <code>app/Models/Accounting/TopUpPaymentApiPoint.php</code>).</li>
<li>The thirty-minute sweeper lives under <code>app/Actions/Accounting/Payment/</code> (one per provider), scheduled from the console kernel; each recovers captured payments through the same idempotent success path.</li>
<li>Locking the api point row before deduping is a standard <code>SELECT ... FOR UPDATE</code> inside a DB transaction — see PostgreSQL's own notes on row locking: <a href="https://www.postgresql.org/docs/current/explicit-locking.html">postgresql.org/docs/current/explicit-locking.html</a>.</li>
</ul></aside>

## Saved cards and merchant‑initiated charges

Customers on the portal can save a card; orders that arrive from their marketplace channels are charged server‑side with no browser in the loop, and the order proceeds regardless — the webhook that follows is reconciliation, not authorisation. That is what lets a dropshipper's overnight orders be picked in the morning.

## Same bones, different provider

When the buy‑now‑pay‑later provider came along, it had no webhooks at all — only redirects — and its "payment" is a *reservation* settled later against the invoice. The shape still held: redirect → server‑side verification of the reservation → same api‑point state machine → same log → at invoicing time, bill the provider the invoiced amount, so a short‑shipped order self‑corrects and the customer is charged for what was sent. The reservation is typed as manually settled, so no automatic "overpayment" credit is ever minted for the gap between what was reserved and what was shipped.

## The playbook

Ten rules, in a file, read before any new provider: verify server‑side; webhook is truth, client is UX; one processing method; lock then dedupe by reference; failure recoverable, success final; bind events to attempts by metadata; log everything with a terminal status; merge jsonb, never replace it; submit after commit; round money at every computation site. Each one is a sentence. Each one is there because of an afternoon.

## What the accountants get

A payments report by provider and by method — card by scheme, wallets, BNPL, bank, cash on delivery — over any interval, with failed attempts as their own column, because a declined card is a fact too. Every row links to its gateway events. And the warehouse ships what was paid, once.

<aside class="tldr bottom"><strong>In one paragraph</strong>The whole architecture reduces to one sentence — never trust a browser as proof of payment, only a server-verified capture counts — and everything else (one processing path, locked idempotency, a forgiving state machine, a full event log, a sweeper) exists to make that sentence hold under real-world timing.</aside>
