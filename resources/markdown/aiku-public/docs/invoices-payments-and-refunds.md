---
title: Invoices, payments and refunds
summary: Find any invoice, see whether it has been paid, record a payment against it, and understand how a refund is raised and where the money goes.
date: 2026-09-01
tags: accounting, invoices, payments, refunds
category: accounting
help_routes: grp.org.accounting.invoices, grp.org.accounting.payment, grp.org.accounting.payments, grp.org.accounting.org_payment_service_providers, grp.org.shops.show.dashboard.invoices
---

<aside class="tldr">
Every sale ends up as an <b>invoice</b>. The organisation's <b>Accounting → Invoices</b> screen lists them all, shows whether each one is paid, and lets you open one to see its lines, its payments, and any refunds against it. Money coming in is recorded as a <b>payment</b> against a <b>payment account</b>; money going out is recorded as a <b>refund</b>, which is itself a special kind of invoice.
</aside>

## The invoice list

Open your organisation, then **Accounting → Invoices**. Each row shows the invoice's **Reference**, the **Customer** it belongs to, the **Date**, its **Payment** status, and the **Net** and **Total** amounts. You can search, sort by any of these columns, and filter between dates.

A shop keeps its own view of the same information: inside a shop's dashboard, under **Invoices**, you find the shop's invoices, with separate lists for **paid** and **unpaid** invoices, plus one for invoices that have since been deleted.

The list also has an **Invoices** tab and a **Refunds** tab, so you can switch straight to the refund side without leaving the screen.

## Opening an invoice

Click a reference to open the invoice. Along the top you find its tabs:

- **Transactions** — the lines that make up the invoice: the goods, charges, shipping and so on.
- **Payments** — every payment that has been taken against this invoice.
- **Refunds** — any refunds raised from this invoice.
- **Email** — the emails aiku has sent about this invoice.
- **History** — a changelog of what happened to the invoice and when.
- **Attachments** — any files attached to it.

From here you can also download the invoice as a **PDF**, and, if your organisation switches this option on, download it in the **Omega** format used for some accounting exports.

## Invoice types

An invoice is always one of two types: an ordinary **Invoice**, or a **Refund**. A refund is not a separate kind of record — it is an invoice whose type is set to Refund, linked back to the original invoice it corrects. Opening a refund from the invoice list takes you straight to its own refund page rather than a plain invoice page.

## Payment status

Every invoice carries a payment status you can see at a glance in the **Payment** column:

- **Unpaid** — nothing, or not enough, has been paid against it yet.
- **Paid** — the invoice has been settled.
- **Unknown payment status** — used only for very old invoices (more than three years old) that have no payment history at all, so aiku genuinely cannot say either way.

## Recording a payment

Payments live under their own **Payments** area in **Accounting**, and can also be started from a customer's **payment account**. Creating a payment (**New payment**) asks for a reference, the customer, and the payment details, and it is always made against a specific payment account.

When a payment is saved, aiku works out how it was paid: if the payment came through with card, wallet or scheme details (for example from checkout.com), aiku records the wallet or payment type as the **method** and the card scheme as the **sub method**; otherwise it falls back to the type of the payment account itself. A successful payment is linked to the invoice through the invoice's own **Payments** tab, and a payment list — whether you are looking at the whole organisation, a shop, a payment account or a single invoice — always shows the payment's **Status**, **Reference**, **Payment Account**, **Type**, **Method**, **Amount** and **Date**.

## Payment accounts and payment service providers

A **payment account** is where a payment is actually taken from or into — it belongs to a **payment service provider**, the company that processes the payment (for example a card gateway). Every payment service provider your organisation has connected has its own page listing its payment accounts, and opening an account lists the payments and the shops that use it.

## Refunds

A refund is raised from the invoice it corrects and shares its reference with a `-refund-` suffix (unless your shop's settings switch on a separate refund numbering sequence). When a refund is created, it starts at zero and is marked as **in process** while it is being built up — its amounts only become final once the refund is completed.

A refund can send the money back in different ways, offered as two options when you submit it:

- **Refund money to customer's credit balance** — the amount is added to the customer's credit balance rather than sent back to a card or account.
- **Refund money to payment method of the invoice** — the amount is refunded against a specific original payment, through the payment account it was originally taken from.

Where a refund is processed online (through checkout.com), aiku waits for the provider to confirm the refund has actually succeeded before it updates the original payment's **total refund** and marks it as refunded; if the provider does not confirm success, the refund is not accepted.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See all invoices:</b> your organisation → <b>Accounting → Invoices</b>. Switch between the <b>Invoices</b> and <b>Refunds</b> tabs at the top.</li>
<li><b>See a shop's invoices:</b> the shop's dashboard → <b>Invoices</b> → paid, unpaid, or deleted invoices.</li>
<li><b>Open one invoice:</b> click its reference to see its Transactions, Payments, Refunds, Email, History and Attachments tabs.</li>
<li><b>Record a payment:</b> your organisation → <b>Accounting → Payments</b> → <b>New payment</b> (or from a payment account's own Payments tab).</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
You need permission to view accounting for the organisation for the <b>Accounting</b> section to appear in your navigation at all. Creating or editing a payment or a payment account needs permission to edit accounting for the organisation.
</aside>
