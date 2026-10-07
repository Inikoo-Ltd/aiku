---
title: Price tips
summary: How aiku suggests a markdown or a markup for master products every night, what each tip shows, and how to apply or dismiss one.
date: 2026-09-30
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
Every night aiku looks at master products that have <b>too much stock</b> or are <b>running out</b>, new lines included, and may suggest a price change. A tip is only a suggestion: <b>no price changes until someone applies it and saves</b>. You see the tips on the <b>Pricing</b> tab, the most certain first.
</aside>

## Which products get a tip

The price of a master product is the same in every shop that sells it, so aiku looks at the days of stock of each organisation and averages them by **how much each organisation sold in the last year**. An organisation that sells most of the product counts most; one that sells little of it hardly counts.

- **Markdown (lower price):** that average is **120 days of stock or more**.
- **Markup (higher price):** that average is **under 45 days**.

**New lines** (no sales in the same months a year ago) get a tip once they have been on sale for **60 days**. With no last year to compare with, the AI judges them on their sales since launch against the rest of the family, and on their price against the family's usual price and competitors.

Some products never get a tip:

- products that sold nothing in the last two years;
- the Aroma master shop.

## How the tip is worked out

For each product that qualifies, an AI model looks at:

- sales month by month over the last two years;
- stock and days of stock in each organisation, and stock on the way from suppliers and partners;
- days the product was out of stock, because a stock-out lowers sales without lowering demand;
- the margin over cost;
- the usual price of the other products in the same family;
- offers running on the product or its family;
- earlier price changes, and how sales moved in the three months after each one.

It picks one of: lower the price 15%, 10% or 5%, keep it, or raise it 5% or 10%. It also judges whether a fall in sales is **temporary** (stock-outs, the season, a one-off big order last year).

aiku then applies fixed rules before showing anything:

- the change must go the right way: a markdown only when there is too much stock, a markup only when stock is running out;
- the AI must be at least 50% sure of its pick;
- no markdown when the fall in sales looks temporary (not checked for new lines, which have no last year);
- a markdown never takes the price below **cost + 25%**. If it would, the cut is made smaller, and the reason says so.

When no tip is given, the **Price tip** column says why in grey, for example *No tip: stock for 80 days, no change needed*, *No tip: the AI keeps the price (71% sure)*, *No tip: the fall in sales looks temporary (65% likely)* or *No tip yet: new, on sale for 30 days*.

## What a tip shows

On the **Pricing** tab, the **Price tip** column shows:

- the change, for example **−10%** in amber for a markdown or **+5%** in green for a markup;
- how sure the AI is, for example **72% sure**;
- a **Dismiss** link.

Hover over the change to read the reason, for example: *Stock for 400 days, averaged by what each organisation sells, sales down 20% on last year, 20 more on the way, 80% margin, price −10% on 2025-03-10 moved sales +25%*. The reason is built from the figures above, so you can check each one.

Products with a tip are listed first, the most certain at the top. Click the column header to sort differently.

Tips are worked out again every night. If the stock or sales change, the tip changes or disappears.

## Applying a tip

1. Click the change (for example **−10%**).
2. The price editor opens with every currency already moved by that percentage.
3. Check the prices and adjust any you want.
4. Save.

The new price goes to every shop through the normal price update, exactly as when you edit a price by hand. The change is recorded with your name in the product's history.

About **8 weeks** after a tip is applied, aiku compares the product's sales in the 8 weeks after the change with the 8 weeks before, and with the same weeks a year earlier. That product gets no new tip until this is measured.

## Dismissing a tip

If a tip is wrong, click **Dismiss** and write why, for example "Christmas stock, sells in December" or "price agreed with a key customer". The reason is kept, so the rules can be improved.

A dismissed product gets no new tip for **30 days**.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See the tips:</b> <b>Masters</b> → open the master shop → <b>Families</b> → open the family → <b>Pricing</b> tab → <b>Price tip</b> column.</li>
<li><b>Apply:</b> click the percentage, check the prices, save.</li>
<li><b>Dismiss:</b> the <b>Dismiss</b> link under the percentage.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
<p>Master shops sit at group level. You need group-level access to masters to see the tips, and edit access to masters to apply or dismiss them.</p>
</aside>
