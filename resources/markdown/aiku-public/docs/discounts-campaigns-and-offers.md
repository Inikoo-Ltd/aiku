---
title: Discounts: campaigns and offers
summary: How a shop's discounts are organised into campaigns, how an individual offer is built and timed, and how the discount ends up showing on an order.
date: 2026-09-02
tags: discounts, offers, campaigns
category: shop
help_routes: grp.org.shops.show.discounts.campaigns, grp.org.shops.show.discounts.offers
---

<aside class="tldr">
Every shop keeps a fixed set of <b>campaigns</b> — one for each kind of discount aiku knows how to run, such as volume discounts, vouchers, or gifts. Inside a campaign you create individual <b>offers</b>: the actual rule with a start date, an end date, and the reward it gives. An offer moves through a small set of states on its own — scheduled, active, finished, suspended — and once it is active, aiku applies it automatically when a matching order is placed. One thing to know before you start: the button that creates an offer is not always on the campaign page — step discounts and gifts are created from a product, and mix &amp; match from a family.
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

Each campaign has its own create button, named after what it makes — **Create Product Offer**, **Create Voucher**, **Create Gift Offer** — and each opens a form built for that one kind of discount. What every form asks for is:

- An **offer name** — the name your team will recognise it by.
- A **trigger** — usually a choice between **By quantity** and **By minimum amount**, plus whatever the offer hangs off: a product, a family, a customer.
- A **discount** — a percentage in most forms, an amount off on vouchers, free items on gifts.

The full offer, once built, also carries:

- A **start date** and an optional **end date**. Leave the start empty and the offer begins immediately.
- A **duration**: **Permanent**, meaning it runs from the start date with no end, or **Interval**, meaning it is bounded and an end date is required.
- One or more **allowances** — the actual reward. Each allowance has a type: **Percentage Off**, **Amount Off**, **Free Items**, **Gift**, **Shipping**, or a **Mixed** combination.

An offer's trigger — what a customer has to do to earn it — depends on the campaign type it lives in. A product-offers campaign triggers on ordering a given product or quantity of it; a category-offers campaign triggers on a department, sub-department, family or category; a shop-offers campaign triggers on the order total for the whole shop; a first-order campaign triggers on being a customer's first order; a voucher campaign triggers on a voucher code being entered; and a shipping campaign gives a free-shipping allowance instead of a price reduction.

## Where each offer is actually created

For most campaigns the create button sits on the campaign page itself, but not for all of them:

| Campaign | Where the button is | What it says |
| --- | --- | --- |
| Product offers | campaign page | Create Product Offer |
| Category offers | campaign page, or a family's **Offers** tab | Create Category Offer |
| Shop offers | campaign page | Create Shop Offer |
| Customer offers | campaign page | Create Customer Offer |
| Vouchers | campaign page | Create Voucher |
| Gifts | campaign page, or a product's **Offers** tab | Create Gift Offer |
| Shipping discount | campaign page | Create Discount Shipping |
| First order | campaign page | Create First Order Bonus |
| Step offers | a product's **Offers** tab | Create Step Discount |
| Volume/GR discount | campaign page, in the header | Set up Vol/GR Gift · New GR Amnesty |
| Discretionary discounts | nowhere — the offer already exists | — |
| Order recursion | not exposed | — |

The catalogue routes are the ones people miss. Open a family, go to its **Offers** tab, and you get **Create Category Offer** and **Create Mix & Match Offer**; open a product and the same tab carries **Create Gift Offer** and **Create Step Discount**. Offers built there are filed into the right campaign for you — a mix & match started from a product lands in Product offers, one started from a family lands in Category offers — so they turn up in the campaign afterwards even though you never opened it.

## States: how an offer's life runs

An offer's state is shown as one of:

- **Scheduled** — saved with a future start date, not live yet.
- **Active** — running now; matching orders receive the discount.
- **Finished** — past its end date.
- **Suspended** — switched off by a team member before its natural end.

Open an offer and the page is titled with its code, showing the start and end times, its state, its type, and a preview of the discount as the customer will see it. Its tabs are **Orders** and **Customers**, showing who has used the offer, and **History**.

## How a discount shows on an order

When an order qualifies for an offer, the reward is applied to the order automatically — you do not attach it by hand. On the order, each affected line shows a **Net Amount** alongside its original (gross) amount; where the two differ, the discount has been applied to that line, and the line can be reset back to the original amount if needed. Discretionary discounts — the manual, staff-applied kind rather than an automatic offer — can additionally be switched on, removed, or restored across every line of an order as a single **Global discount** action, with its own percentage and label.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See all discounts:</b> your shop → <b>Offers</b> in the main navigation, which opens the <b>Insights</b> page.</li>
<li><b>Browse by campaign type:</b> shop → <b>Offers → Campaigns</b> → open a campaign → its <b>Overview</b> and <b>Offers</b> tabs (Volume/GR campaigns also have <b>GR Amnesty</b>).</li>
<li><b>Browse every offer at once:</b> shop → <b>Offers → Offers</b>, listing name, label, type, start and end dates, orders, invoices and sales.</li>
<li><b>Create an offer:</b> the campaign page for most types; a product's <b>Offers</b> tab for step discounts and gifts; a family's <b>Offers</b> tab for category and mix &amp; match offers.</li>
<li><b>See performance:</b> shop → <b>Offers → Insights</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
<p>Viewing a shop's campaigns and offers needs view access to that shop's discounts; changing them needs edit access. Ask your organisation admin if the create or edit buttons are missing.</p>
</aside>
