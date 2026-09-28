---
title: Checking master product prices
summary: How aiku suggests a price when you create a master product, the red warnings on prices that look wrong, and what to do when a product's trade units change.
date: 2026-09-28
tags: masters, pricing, products, catalogue
category: shop
help_routes: grp.masters.master_shops.show.master_families.show
---

<aside class="tldr">
A master product's price goes to every shop that sells it, so one mistake reaches every shop at once. aiku now marks two kinds of suspicious price in red: a price per unit <b>far away from the rest of its family</b>, and a price that was <b>not reviewed after the trade units changed</b>. If you create master products or look after prices, read this guide.
</aside>

## Where the suggested price comes from

When you create a master product from trade units, aiku fills in a price for you. It adds up the cost of **every trade unit you selected**, then applies the master shop's usual markup. The RRP is worked out from that price the same way.

That suggestion is only as good as the trade units you picked:

- Select **one** trade unit and the suggestion is the price of one item.
- Select **several** trade units and aiku treats the product as a **bundle** of all of them. The suggested price is the price of the whole bundle.

So if you meant to create one size of a garment and selected every size in the list, the product is priced as a pack of all the sizes. That price is many times what customers expect for one item.

<aside class="tip">Always check the trade units list and the <b>Unit</b> field before saving. If the unit says <b>bundle</b> and you meant a single item, you have selected too many trade units.</aside>

## Warning 1: the price is far from the family

aiku compares each product's **price per unit** with the usual price per unit of the **other products in the same family**. If a product costs **three times the usual price or more**, or **a third of it or less**, aiku shows a warning.

- **While creating a product:** a red box appears under the prices with the family's usual price. When you save, aiku asks you to confirm.
- **On the Pricing tab:** the price shows in red with a warning triangle. Hover over the triangle to see how far it is from the family.

The warning doesn't block anything. Some families mix very different sizes, and a big bottle can honestly cost five times a small one. Treat it as a question to answer: is this really the right price? If it is, leave it. If it isn't, fix it.

The check needs at least three other products in the family, so it stays quiet in very small families.

## Warning 2: the trade units changed after the price was set

Changing a product's trade units (its composition) **doesn't change its price**. If a product was created as a bundle of 17 trade units and later corrected to one, it keeps the 17-unit price until someone edits it.

From now on, when the trade units of a master product change and the prices aren't saved in the same edit, the product is marked for review. On the Pricing tab its price shows in red with a warning triangle, and the tooltip says the composition changed after the price was set.

The mark clears as soon as someone **saves the prices** of that product, either one product at a time or with a bulk price edit. Saving the same price again also clears it, so you can confirm that a price is still right.

## What to check

1. Open the family and go to the **Pricing** tab.
2. Look for prices in red. Hover over the triangle to read why.
3. For each one, check the trade units label next to the name. It shows every trade unit and its quantity.
4. If the trade units are wrong, fix them first on the product's edit page, then set the price.
5. Edit the price with the pencil and save it. The red mark goes away.

Remember the RRP as well: it was worked out from the same wrong price, so check it in the RRP column.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Pricing of a family:</b> <b>Masters</b> → open the master shop → <b>Families</b> → open the family → <b>Pricing</b> tab.</li>
<li><b>Pricing of a variant:</b> open the family → the variant → <b>Pricing</b> tab.</li>
<li><b>Edit one price:</b> the pencil next to the price on the Pricing tab.</li>
<li><b>Edit many prices:</b> tick the products on the Pricing tab and use the bulk price edit.</li>
<li><b>Fix trade units:</b> open the master product → edit → composition.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
<p>Master shops sit at group level. You need group-level access to masters to see the Pricing tab and edit access to change prices or trade units.</p>
</aside>
