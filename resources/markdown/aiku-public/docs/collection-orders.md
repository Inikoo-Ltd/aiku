---
title: Collection orders
summary: What changes when a customer collects an order instead of having it shipped, what appears on the invoice, and where to correct an address when one is missing or wrong.
date: 2026-09-10
tags: orders, invoices, collection, accounting, crm
category: orders
help_routes: grp.org.shops.show.ordering.orders.show, grp.org.accounting.invoices
---

<aside class="tldr">
A <b>collection order</b> is one the customer picks up themselves. It carries no shipping charge and no delivery address; instead it carries the address they collect from. On the invoice that address takes the place of the delivery address, under the heading <b>Collection address</b>. If a customer has no address on their account, the order stops before the warehouse until someone adds one.
</aside>

## What makes an order a collection

An ordinary order is shipped: it has a delivery address, it is priced with a shipping charge, and the delivery note is sent out with a carrier. A collection order has none of that. The goods are still picked and packed as normal, but the customer comes to us, so there is no shipping line on the order and no delivery address to print.

What the order carries instead is a **collection address** — the place the customer collects from. It comes from the shop, so every collection order for that shop points at the same place unless it has been set otherwise.

## What the invoice shows

A collection invoice has the same two boxes as any other invoice, but the right-hand one changes:

- **Billing address** — comes from the customer's account. This is the address the invoice is billed to, and it is the one that matters for tax.
- **Collection address** — the address the goods were collected from. It replaces the delivery address box.

Blank lines are never printed, so an address with parts missing shows only what it has rather than padding the box out.

An invoice is a fixed document. The collection address is stored on it at the moment it is issued, so changing the shop's collection address later does **not** rewrite invoices that already exist — they keep showing the address that applied when the goods were collected. Invoices issued before this was stored have nothing to show, so their box simply reads **Collection**.

## When a customer has no address

An account can end up with no address at all. When that happens the order is **held before it reaches the warehouse**: no delivery note is created, the order stays in the submitted list, and a warning is added to its warehouse note asking for the address. Nothing is picked and nothing is invoiced until it is sorted out.

The fix is to put the address on the **customer**, not on the order. Open the customer, add their address, and the order carries on to the warehouse by itself. Adding it to the customer also means their next order is right without anyone having to think about it.

Staff creating an order in the back office cannot submit one without a billing address in the first place. A customer ordering on the website can still reach checkout without one — their payment goes through as normal and it is the warehouse hold that catches it, so no one is ever charged and then refused.

## Correcting an address

There are two different jobs here, and they use two different screens.

**For every future invoice** — change the shop's collection address. Open the shop, go to **Settings**, and edit the **Collection address** field. From then on, invoices for collection orders in that shop are issued with the new address. Existing invoices are untouched, which is the point.

**For one invoice that is already issued** — open the invoice and click the pencil on its address box. This edits the invoice's **billing** address only, which is the one that comes from the customer account. The collection side is not typed in by hand; it comes from the shop.

<aside class="wayfinder">

### Where to click in aiku

- **See whether an order is a collection** — open the order; a collection order shows a collection address in place of a delivery address, and carries no shipping line.
- **Find an order that is being held** — the organisation's orders list, **Submitted** bucket. The reason sits in the order's warehouse note.
- **Add an address to a customer** — open the customer from **CRM → Customers**, then edit their address.
- **Change where customers collect from** — open the shop, then **Settings**, and edit **Collection address**.
- **Correct the billing address on one invoice** — open the invoice, click the pencil on the address box, edit, **Save**.

### Permissions you need

- Editing a shop's settings, including the collection address, needs **organisation admin** or **shop admin**.
- The pencil on an invoice address only appears for **accounting supervisors** in that organisation.
- Adding an address to a customer is part of ordinary customer-service work on that shop.

</aside>
