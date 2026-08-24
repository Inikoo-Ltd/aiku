---
title: An invoice is a snapshot
summary: A million invoices and fifty thousand credit notes, and the rule that governs all of them: an invoice is a frozen picture of an order at the moment it was billed, and nothing that happens later — a price change, a tax change, a product rename — can move it. How invoices are minted from orders, how credit notes reference the lines they return, how a paid status is derived rather than set, how invoices are categorised for the accountants, and how a PDF, an e‑invoice and an export all read from the same rows.
date: 2026-08-18
tags: accounting, invoices, ordering, architecture
---

<aside class="tldr"><strong>TL;DR</strong>Of roughly <code>1.08 million</code> invoices and <code>53,000</code> credit notes, every one follows the same rule: an invoice is a frozen snapshot of an order at billing time, and nothing afterward can change it. Totals are always recomputed from transaction lines, never edited directly. Credit notes reference the exact lines and tax they return. Paid status is derived from attached payments, not set by a person, and invoices are categorised by a rule engine for accounting reports.</aside>

There are about **1.08 million invoices** and **53,000 credit notes** in the system, going back to the start of the migration, and one rule governs how all of them are made and kept: **an invoice is a snapshot.** It is a frozen picture of an order — lines, quantities, prices, discounts, tax per line, addresses, the customer's tax number and the category it was rated under — at the moment it was billed. Everything downstream of that moment is designed so the picture cannot move.

## Minted from the order, then detached

An invoice is generated from an order when the order is dispatched (or, for services and fulfilment, when the bill consolidates). Generation copies: each order line becomes an **invoice transaction** with its own net, tax and gross, the historic product version it was sold at, and the offer allowances that discounted it. The order's addresses are copied; the customer's tax number and its validation status at that moment are copied; the tax category that was [resolved for the order](/blog/you-think-you-had-a-bad-day-tax-in-europe) is copied. The invoice then lives on its own. The order can be edited for the warehouse's sake; the invoice does not follow.

Invoices are numbered per shop in an unbroken sequence, dated on the day they were issued, and, once issued, the only field that can change is the date — through one audited action with a reason, because there are legitimate reasons and the accountants want to see each one.

## Totals are recomputed from lines, never edited

An invoice's net, tax and total are calculated from its transactions by one action, and only that action writes them. If a line is added (a late service) or a refund is raised against it, the totals are recalculated from the rows. There is no field a person can type a total into. The same action has a *tax‑only* mode for the one case where an invoice carries tax and nothing else.

## Credit notes reference what they return

A credit note — a *refund* in the type enum — is an invoice with a negative sign that **points at the invoice it credits** and, line by line, at the transactions it returns. It is raised from the original, choosing lines and quantities, so the credited amount and its tax are exactly those of the original lines; a credit note cannot credit a line the invoice did not have, nor tax at a rate the original did not charge. A VAT‑only credit note returns exactly the tax the invoice carried, per line.

Where the money goes is a separate decision from the credit note itself: to the customer's balance on the account, back to the original payment account, or against another invoice. The three are actions with their own authorisation; the credit note is the document, the refund is the movement.

## Paid is derived

An invoice's pay status — *unpaid, paid* — is not set by a person. It is derived from the payments attached to it: attach a payment, the status recalculates; detach it, the same. Payments are attached to invoices when an order is paid at checkout, when a [payment provider's webhook](/blog/how-a-payment-becomes-a-fact) confirms a capture, when accounts record a bank transfer, or when a credit note's refund is applied. An *unknown* status exists for the oldest migrated invoices with no payment history in the source system, so that "we don't know" is never displayed as "unpaid".

## Categories for the accountants

Every invoice is **categorised** at creation by a rule engine the accountants own: by shop type, by whether the customer is in the shop's country or not, by organisation, by sales channel, for a named VIP list, for invoices raised by an external invoicer. Categories drive the reports and the time series that management reads — sales by category over any period — and they are recomputed when a rule changes, with the change audited. A category is a label for reporting; it never changes a number on the invoice.

## One set of rows, several documents

The PDF the customer downloads, the e‑invoice in the national electronic format where a market requires one, the accounting system export, the dropshipping invoice export by date, the customer's invoice list in the portal and the staff's — all read the same transactions and the same totals. There is no rendering path that computes its own figures. The PDF is stored once it is produced, so the document the customer received is the document that is kept.

## Deleted is a history, not a hole

An invoice that must be withdrawn (raised in error before anything was sent) is soft‑deleted, and a **deleted‑invoice history** row records what it was, who withdrew it and why, so the numbering sequence has no unexplained gaps. An invoice that has been sent is never deleted; it is credited.

## Why a snapshot

Because the alternative is an invoice that quietly changes when a product is renamed, a price is corrected or a tax rule is updated — and a customer's copy that no longer matches ours. A snapshot, totals from lines, credit notes that reference their lines, a pay status that is derived, categories that only label: five rules, a million invoices, and a set of books an auditor can walk from any line back to the event that made it.

<aside class="tldr bottom"><strong>In one paragraph</strong>An invoice that can never move after it's issued, with totals always recomputed from its own lines, is what lets a million invoices stay trustworthy years later.</aside>
