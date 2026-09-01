---
title: Discounts: campaigns and offers
summary: How a shop's discounts are organised into campaigns, how an individual offer is built and timed, and how the discount ends up showing on an order.
date: 2026-09-01
tags: discounts, offers, campaigns
category: shop
help_routes: grp.org.shops.show.discounts.campaigns, grp.org.shops.show.discounts.offers
---

<aside class="tldr">
Every shop keeps a fixed set of <b>campaigns</b> — one for each kind of discount aiku knows how to run, such as volume discounts, vouchers, or gifts. Inside a campaign you create individual <b>offers</b>: the actual rule with a start date, an end date, and the reward it gives. An offer moves through a small set of states on its own — scheduled, active, finished, suspended — and once it is active, aiku applies it automatically when a matching order is placed.
</aside>

## Campaigns: one per type of discount

Open a shop and go to **Offers → Campaigns**. Each row is a campaign, and its type tells you what kind of discount it can run:

- **Order recursion**
- **Volume/GR discount**
- **First order**
- **Customer offers**
- **Shop offers**
- **Category offers**
- **Product offers**
- **Step offers**
- **Discretionary discounts**
- **Shipping discount**
- **Gifts**
- **Vouchers**

The list shows the campaign name, how many current offers it holds, how many customers and orders it has touched. You do not create new campaigns yourself — a shop already has one of each type — you open the one whose type matches the discount you want and add an offer inside it.

Open a campaign and you land on its **Overview** tab, which summarises its offers. From there you can switch to the **Offers** tab to see or add the offers inside it, and **History** to see what has changed. A Volume/GR discount campaign has an extra tab, **GR Amnesty**, for its own kind of offer.

A campaign itself also has a state, shown against each of its offers as a group: **In process**, **Active**, **Finished**, or **Suspended**.

## Offers: the actual discount rule

Inside a campaign, go to its **Offers** tab and press **Create Offer**. The basic-information step asks for:

- **Offer Code** — a unique short code (letters, numbers and dashes only).
- **Offer Name** — the name your team will recognise it by.
- **Offer Type** — which of the campaign's specific trigger patterns this offer uses (for example, ordering a minimum quantity of a product, spending a minimum amount, or placing a given order number).

The full offer, once built, also carries:

- A **start date** and an optional **end date**.
- A **duration**: **Permanent**, meaning it simply runs between those dates, or **Interval**, meaning it repeats.
- One or more **allowances** — the actual reward. Each allowance has a type: **Percentage Off**, **Amount Off**, **Free Items**, **Gift**, **Shipping**, or a **Mixed** combination.

An offer's trigger — what a customer has to do to earn it — depends on the campaign type it lives in. A product-offers campaign triggers on ordering a given product or quantity of it; a category-offers campaign triggers on a department, sub-department, family or category; a shop-offers campaign triggers on the order total for the whole shop; a first-order campaign triggers on being a customer's first order; a voucher campaign triggers on a voucher code being entered; and a shipping campaign gives a free-shipping allowance instead of a price reduction.

## States: how an offer's life runs

An offer's state is shown as one of:

- **Scheduled** — saved with a future start date, not live yet.
- **Active** — running now; matching orders receive the discount.
- **Finished** — past its end date.
- **Suspended** — switched off by a team member before its natural end.

An offer's own **Settings** tab holds this timing and state information, alongside a **Vouchers** tab (for voucher codes), an **Orders** tab and a **Customers** tab showing who has used the offer, and a **History** tab.

## How a discount shows on an order

When an order qualifies for an offer, the reward is applied to the order automatically — you do not attach it by hand. On the order, each affected line shows a **Net Amount** alongside its original (gross) amount; where the two differ, the discount has been applied to that line, and the line can be reset back to the original amount if needed. Discretionary discounts — the manual, staff-applied kind rather than an automatic offer — can additionally be switched on, removed, or restored across every line of an order as a single **Global discount** action, with its own percentage and label.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See all discounts:</b> your shop → <b>Offers</b> in the main navigation, which opens the <b>Insights</b> page.</li>
<li><b>Browse by campaign type:</b> shop → <b>Offers → Campaigns</b> → open a campaign → its <b>Overview</b> and <b>Offers</b> tabs (Volume/GR campaigns also have <b>GR Amnesty</b>).</li>
<li><b>Browse every offer at once:</b> shop → <b>Offers → Offers</b>, listing name, label, type, start and end dates, orders, invoices and sales.</li>
<li><b>Create an offer:</b> open the relevant campaign → <b>Offers</b> tab → <b>Create Offer</b>.</li>
<li><b>See performance:</b> shop → <b>Offers → Insights</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
<p>Viewing a shop's campaigns and offers needs view access to that shop's discounts; changing them needs edit access. Ask your organisation admin if the create or edit buttons are missing.</p>
</aside>
