---
title: How a dropshipping customer's selling prices are set
summary: Where the price in a customer's My Products comes from, what a pricing rule of +100% or +200% really does, why changing the rule does not reprice products already added, and how the customer reprices them.
date: 2026-09-23
tags: dropshipping, crm, prices, shopify, sales channels
category: crm
help_routes: grp.org.shops.show.crm.customers.show.customer_sales_channels.
---

<aside class="tldr">
A dropshipping customer's selling price is <b>our RRP plus the pricing rule the customer set on that sales channel</b>. The rule is applied <b>when a product is added</b> to the channel. Changing the rule later does not touch products already added, so a channel can end up with products at different markups. <b>+100% means double the RRP, +200% means three times.</b> The customer fixes existing products themselves with <b>Edit Price</b> in My Products.
</aside>

## Where the price comes from

Every product has our **RRP**, the recommended retail price. That is the starting point for every dropshipping customer and it is the same for all of them.

Each of the customer's sales channels (a Shopify store, a WooCommerce store, a manual store and so on) can have its own **pricing rule**:

- **Percent** — a percentage over the RRP.
- **Fixed** — an amount of money over the RRP.
- **No rule** — the product is priced at the RRP.

When the customer adds a product to the channel, aiku takes the RRP, applies the channel's rule, and that becomes the price shown in **My Products**. That is also the price sent to their Shopify or other store when the product is uploaded.

The cost price the customer pays us is not part of the calculation. The rule is added on top of the RRP, not on top of the cost.

## What +100% and +200% really mean

The percentage is **added** to the RRP. Many people read it as a multiplier, and it is not one:

| Rule | Price | Example with RRP 16.18 |
|---|---|---|
| no rule or +0% | RRP | 16.18 |
| +50% | 1.5 × RRP | 24.27 |
| +100% | 2 × RRP | 32.36 |
| +200% | 3 × RRP | 48.54 |

Measured against the cost price, the jump looks even bigger: a product costing 7.17 with an RRP of 16.18 sells at 48.54 under a +200% rule, nearly seven times its cost.

## Changing the rule does not reprice what is already there

The rule is used **at the moment a product is added**. If the customer changes the rule afterwards:

- products added **from then on** get the new rule;
- products **already in the channel** keep the price they were given.

So a customer who adds products, changes the rule, and adds more will see different markups in the same channel. Some products sit at the RRP because they were added before any rule existed, and others sit at 2× or 3× depending on the rule in force that day. This is the usual cause of "the markup is not consistent" reports. Nothing is wrong with our RRPs; the prices are exactly what the rule said at the time.

## How the customer reprices their products

The customer does this themselves, in their own account:

1. Open **My Products** in the sales channel.
2. Select the products to reprice (select all to do the whole channel).
3. Click **Edit Price**.
4. Under **Price Mapping**, choose **± % over live RRP** (or the fixed amount) and enter the markup, for example **100** for double the RRP.
5. Save. The new prices are worked out from today's RRP and sent to their store.

Products repriced this way count as having their own price from then on.

## When a customer reports "wrong RRP"

1. Open the customer, go to their sales channels, and note the pricing rule on each one.
2. Compare a few products: the RRP on the product and the price in the channel. A clean multiple (2×, 3×) of the RRP means a rule, not an error.
3. Explain the rule and the table above, and point them to **Edit Price** to reprice.

If the price is not a clean multiple of the RRP and no rule explains it, raise a ticket with the product codes and the channel name.

<aside class="wayfinder">

### Where to click in aiku

- **See a customer's channels and their products** — **CRM → Customers**, open the customer, then **Channels**.
- **Check our RRP** — open the product in the shop's catalogue.

### Where the customer clicks

- **Set the pricing rule** — their sales channel's settings, **Pricing Policy**. It shows an example RRP and the price it becomes.
- **Reprice products already added** — **My Products**, select products, **Edit Price**.

### Permissions you need

- Viewing customers and their channels is part of ordinary customer-service work on that shop. Prices on the customer's channels are theirs to set; we do not change them for them.

</aside>
