---
title: Why aiku asks for a full address
summary: What counts as a complete address, why an order without one waits before the warehouse, how to release it, and what to do when the customer cannot be reached.
date: 2026-09-10
tags: orders, crm, invoices, addresses, accounting
category: orders
help_routes: grp.org.shops.show.crm.customers, grp.org.accounting.invoices
---

<aside class="tldr">
Everywhere a person types an address, aiku now asks for the parts that country actually uses. If a customer has no address on their account, their order still takes payment normally but <b>waits before the warehouse</b> instead of being picked: nothing ships, and no invoice goes out with a blank address on it. Put the address on the customer and the order carries on by itself. If the customer cannot be reached, there is a button to send it anyway.
</aside>

## What counts as a complete address

An address is not the same thing in every country, so aiku asks for what that country uses and nothing more:

- **United Kingdom** — street, town and postcode.
- **Ireland** — street and town. No postcode is asked for, because Irish addresses do not use one in this format.
- **Spain and Italy** — street, town, province and postcode.
- **United Arab Emirates** — street and emirate. No town.

This comes from the same country list that decides which boxes appear on the form, so the boxes you see are the boxes you need to fill. A single **0** does not count as an address; it used to be accepted and it is what printed as zeros on paperwork.

You will be asked for a complete address anywhere a person types one: signing up on a shop website, a customer editing their own details, adding a delivery address, editing a customer or an order or an invoice in the office, and on the shop, supplier, agent, warehouse and company screens.

Orders arriving on their own from a sales channel — Shopify, eBay, Amazon, TikTok and the rest — are **never** refused for an incomplete address. Their payment is already taken elsewhere, so refusing them would lose the order. The same goes for the overnight data imports.

## What happens when a customer has no address

Some accounts were created before this was asked for, so they have no address at all. When one of them orders:

1. The customer pays as normal. Payment is never refused for a missing address.
2. The order is submitted as normal.
3. It **stops before the warehouse**. No delivery note is created, so there is nothing to pick and nothing to pack.
4. It stays in the **submitted** list with a warning on its warehouse note, saying the customer has no address.

Nothing is lost and nobody is charged for something they will not receive — the order simply waits for somebody to sort the address out.

## Releasing a held order

**The good way: put the address on the customer.** Open the customer, add their address, and the order picks it up and carries on to the warehouse by itself. This is the one to prefer, because it also fixes every future order that account places.

**The other way: put the address on the order.** Open the order and edit its billing address, and its delivery address if that is the missing one. As soon as the order has what it needs, it goes to the warehouse on its own. This fixes the one order, not the account.

If you press **Send to warehouse** while an address is still missing, aiku tells you so rather than doing nothing.

## When the customer cannot be reached

Sometimes there is no reply and the goods have to go. A held order has a **Send anyway, no address** button. It does what it says: the order goes to the warehouse and is picked, packed and dispatched as normal, and a line is added to its warehouse note recording that it was sent deliberately without an address.

Use it as the last resort, because of what follows it: the invoice for that order will show only the country in the address box. That paperwork cannot be changed once it is issued, so it is worth one more attempt at reaching the customer first.

## Why this matters

Before this, an account with no address produced an invoice with zeros where the address should be, and it happened a few times a day, every day. The customer receives a document that looks broken; nobody notices unless they look. The address is also what the invoice is billed to, so an empty one is a real gap in the paperwork rather than a cosmetic one.

<aside class="wayfinder">

### Where to click in aiku

- **See orders that are waiting** — the organisation's orders list, **Submitted**. A held one carries a warning in its warehouse note.
- **Add an address to a customer** — **CRM → Customers**, open the customer, edit the address. This releases any of their held orders.
- **Fix one order only** — open the order, edit the billing address, and the delivery address if that is the empty one.
- **Send it without an address** — open the held order and use **Send anyway, no address**.
- **Correct an invoice's address** — open the invoice and click the pencil on its address box.

### Permissions you need

- Editing customers and orders is part of ordinary customer-service work on that shop.
- The pencil on an invoice address is for **accounting supervisors** in that organisation.
- **Send anyway, no address** needs the same permission as sending any order to the warehouse.

</aside>
