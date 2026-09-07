---
title: Discounting a collection
summary: How to run a percentage off every product in one collection, across one shop or many, and how to check in a basket that only those products get it.
date: 2026-09-02
tags: discounts, offers, collections
category: shop
series: Collections and collection offers
order: 3
help_routes: grp.org.shops.show.discounts.campaigns.offer
---

<aside class="tldr">
A <b>Shop Offer</b> normally discounts every product in the basket. Pick a collection while creating it and it discounts only the products in that collection, at whatever quantity, for the dates you set. It is the tool for "20% off everything made in one country" or "15% off the summer shelf". The collection decides who gets it, so build the collection first, see <a href="/docs/shop-collections">Shop collections</a>. For a shelf shared by many shops, build it once as a master collection, see <a href="/docs/master-collections">Master collections</a>, then create one offer per shop.
</aside>

## Create the offer

Open the shop, go to **Offers → Campaigns**, open **Shop offers** and press **Create Shop Offer**. Fill in:

- **Offer name**, which is what the customer sees on the basket line, so write it for them.
- **Select offer type**: leave **All Orders**. **By minimum amount** makes the discount wait until the basket reaches an amount, which you usually do not want for a collection promotion.
- **Only products in collection**: type part of the collection's name or code and pick it. Leave it empty and the offer discounts the whole basket.
- **Discount**, a percentage.
- **Offer Duration**: **Permanent**, or **Interval** with a start and an end. The **1 day** to **7 day** buttons fill the dates for you.

Save, and you land on the offer page, already **Active** if the start date is today. The offer code is the campaign code, the shop code and the collection code joined, so a collection offer is easy to spot in the list.

Repeat in each shop that runs the promotion. Offers belong to a shop, so a group-wide promotion is one offer per shop, all pointing at that shop's copy of the same master collection.

## What happens in the basket

Every time a basket changes, aiku prices it again. For a collection offer it reads the collection's product list at that moment and discounts the lines whose product is in it. Products added to the collection after the offer started get the discount from then on, and products removed lose it.

One discount per line. A product that already has a bigger discount, from a family offer or from the customer's own reward level, keeps the bigger one. The collection offer never stacks on top, and never takes a better price away. So a customer on a 25% reward level sees 25% on a collection product, not 45% and not 20%.

The offer page's **Orders** tab lists every order that used it, and the offer's sales figures come from those lines. That is the number to quote when a promotion promises to pass a share of the sales on.

## Check it before you announce it

Use a test customer with no reward level.

1. Add a product from the collection. The line shows the percentage and the offer name.
2. Add a product that is not in the collection. No discount on that line.
3. Raise the collection line's quantity. Same percentage, nothing more.
4. Log in as a customer with a reward level higher than the offer. The line keeps the reward level, not both.
5. Look at the website product page and basket: the collection product shows the discounted price, the other product does not.

If a product gets the discount and should not, the fix is in the collection, not the offer: remove it from the collection, or remove the family that brought it and add the wanted products directly.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Create the offer:</b> your shop → <b>Offers → Campaigns</b> → <b>Shop offers</b> → <b>Create Shop Offer</b> → fill <b>Only products in collection</b>.</li>
<li><b>See the offer:</b> the offer page opens after saving; later, shop → <b>Offers → Offers</b> and search by name.</li>
<li><b>Orders that used it:</b> the offer page → <b>Orders</b> tab.</li>
<li><b>End it early:</b> the offer page → edit → change the end date.</li>
<li><b>Fix who gets it:</b> shop → <b>Catalogue → Collections</b> → open the collection → <b>Products</b> tab.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
<p>Edit access to the shop's discounts to create or change the offer, and edit access to Products for the shop to change the collection it points at.</p>
</aside>
