---
title: Competitor prices
summary: How aiku reads the prices of your competitors' websites, matches their products to your master products, and shows where you are cheaper or dearer, with what to check on the Competitors dashboard.
date: 2026-11-06
tags: masters, pricing, products, catalogue
category: shop
help_routes: grp.masters.competitors.dashboard, grp.masters.competitors.index, grp.masters.competitors.show, grp.masters.competitors.prices.index, grp.masters.competitors.prices.confirmed, grp.masters.competitors.prices.rejected, grp.masters.competitors.prices.all
---

<aside class="tldr">
Under <b>Masters › Competitors</b> you tell aiku which competitor websites to watch. aiku reads their products and prices by itself, matches them to your master products, and shows <b>where you are cheaper, about the same or dearer</b>, per unit, in their currency against your own price in it. Marketplaces that block automated visits are <b>checked by hand</b> once a month instead. The AI confirms the matches it is sure of and asks you to <b>review the rest</b> with a tick or a cross. Confirmed prices also show up in the <a href="/docs/price-tips">price tips</a>.
</aside>

## Adding a competitor

Go to **Masters › Competitors** and click **Add competitor**. You need:

- the **master shop** whose products it competes with;
- a **name** and the **website**;
- who it **sells to**: shoppers (their prices are compared with your RRP) or retailers (compared with your price);
- the **currency** of their prices.

The **language of their website** is found by itself when their dossier is read, and can be changed on **Edit**. Their products are matched, and their website searched, with your product names in that language, taken from your shop in that language, so a Spanish competitor is compared with your Spanish names.

That is enough for a Shopify or WooCommerce shop that shows its prices: aiku finds its public catalogue by itself and reads it the next morning at 04:00 UTC, every product and every pack size with its price. The competitor page then says **Shopify catalogue** or **WooCommerce catalogue** under *Prices read from*.

For any other website, fill in one of the fields under **Where to read prices**:

- **Product feed link**: the CSV or text file with all their products and prices that they give their trade customers. Best option; read every morning.
- **Search link**: search their website for any word, copy the link of the results page and put `{query}` where the word was. aiku then searches their site every week for the master products that have an open price tip. When the link is left empty for a site with a login, or the one entered lands on a page without the searched words, aiku uses the search link the website publishes about itself if it has one; otherwise the competitor shows **The search link does not search**.
- **Login page**, **login email** and **password**: only when they show prices to logged-in trade customers. The password is stored encrypted and never shown again; leave it empty when editing to keep it. aiku logs in afresh every time it searches.

## Marketplaces checked by hand

Big marketplaces that sell straight to shoppers block automated visits with security checks and logins, so aiku cannot read them. Add them as a competitor that sells to **shoppers**, in the currency you see on their site, and switch on **Prices checked by hand**. aiku then never opens their website: no catalogue, no search and no dossier.

Once a month, open their website as a buyer in the EU, look up our exposed products (start with the ones made in China, where these sellers are strongest) and, on the competitor page, click **Add price check** for each listing that is the same item as ours:

- **Our product code**, for example the master product code on the label;
- the **link to their listing**, so anyone can open it and see the price with their own eyes;
- **their product name**, the **price for the pack** (VAT included, with delivery if they charge it), the **units in the pack** and the **delivery days** they promise;
- a **screenshot** of their listing with the price showing: choose the file, or paste it straight from the clipboard with Ctrl+V / Cmd+V. It is the proof of the price, kept even after the listing changes or disappears.

The listing becomes a confirmed match, so it counts on the dashboard, in the **Confirmed** price comparison and in the price tips like any other. Every check is also kept: the **Price checks** tab lists them newest first, with their price per unit, yours, the difference, the change since the check before for the same listing, the delivery days, who checked and the screenshot (click it to see it full size). Adding the same link again next month adds a new row, so the history shows whether they are getting cheaper.

## What the dashboard shows

**Masters › Competitors** opens on the dashboard:

- **Competitors**: how many were read fine, how many are failing and why (the website could not be opened, it blocked us, the login failed, or the search link does not search), with the last error in plain words, and when the last read was.
- **Matches to review**: how many matches the AI was not sure of. Click it to review them.
- **Reviewed by staff this week** and **Confirmed this week**, each against last week.
- One card per master shop: how many confirmed products you are **cheaper**, **about the same** (within 5%) or **dearer** on, the median difference, and the share of active master products that have at least one confirmed competitor price.
- **Confirmed matches per week** over the last 12 weeks.
- **Where they are cheapest** and **Where we are cheapest**: the ten confirmed matches with the biggest gap each way, linking to the master product.
- The competitors ranked by confirmed matches, with their median difference, products read, last read and status.

Until the first competitor is added, the dashboard explains what to do instead.

## Reviewing matches

After every read the AI compares each of their products with yours. A match it is sure of is **confirmed by itself** (marked *auto*); the others wait under **Prices to review**. Each row shows your product, your price per unit, their product with a link, their price per unit, and the difference: red when they are cheaper, green when they are dearer. Both prices are in their currency, yours taken from your price in that currency (an AW class price in euros for a competitor in euros); only when you have no price in their currency are both converted to the group currency.

**Units in their pack** under their product is what their price is divided by. Packs are not always counted the same way on both sides: your box of 12 packets may be held as 1 unit while theirs is entered as 12, or their box of 12 may be read as 1. When that would put their price per unit under 40% or over 250% of yours while pack against pack the two prices are close, aiku takes the packs to be the same and compares them pack against pack; the row then says *Compared pack against pack*. A difference that is still very large after that is a real gap or a wrong match: open their link and check.

Click the **tick** when it is the right match and the **cross** when it is not. Rejected matches are not shown again. The buckets at the top switch between **To review**, **Confirmed**, **Rejected** and **All**; the **Confirmed** bucket doubles as the price comparison, sortable by difference.

A match marked *Similar, not the same item* is kept as a hint and not counted in the price comparison until you click the **tick** on it: the tick says it is the same item, and from then on it counts.

## The competitor page

Click a competitor's name on the list or the dashboard. The header shows its logo, website, the platform its shop runs on (Shopify, WooCommerce and so on), master shop, who it sells to, the currency, how its prices are read, when the prices were last read and when the dossier was last refreshed. Four tabs follow:

- **Dossier**, the default: the intelligence report on the competitor, built from its public pages once a week (see below).
- **Matches**: its matches with the same tick and cross review.
- **Products read**: everything read from it, with the price, the price before the discount when the shop shows one, how much is off, units and link. The **On sale now** filter keeps only the discounted products.
- **Policies**: the text of each shipping, returns and terms page read for the dossier, each with its link, so a fact in the dossier can be checked against the page it came from.

**Edit** opens the same form as when adding, without the master shop.

### The dossier

Every week aiku opens the competitor's homepage and its shipping, returns and terms pages in a browser, exactly as a visitor would, without logging in. It takes a screenshot of the homepage, reads what the company publishes about itself, and the AI pulls out the facts, each with the page it came from, and writes a short briefing. The dossier is also built when a competitor is added, and whenever you press **Refresh dossier** (it takes a few minutes; come back to the page).

The tab shows, from the top:

- **Briefing**: four to six sentences on who they are, how they price against us, how much they lean on offers, and their shipping and terms, with a one-line positioning.
- **What changed**: what differs from the refresh before, with dates: the free-shipping threshold, shipping costs or delivery times, minimum order, returns window, an offer or coupon that appeared or ended, payment methods, phone, email or address, platform, a product count that moved more than 10%, or a share on sale that moved more than 10 points. Earlier changes follow as a short timeline. A first dossier says there is nothing to compare yet.
- **Screenshot** of their homepage; click to enlarge.
- **Identity**: company name, phones, emails, address, country and social accounts, as published on their site.
- **Key numbers**: products listed, price range, share on sale now, average discount, confirmed matches and the median difference against us.
- **Site-wide offers**: banners, coupon codes, volume or tier discounts and newsletter discounts, each with its source page, plus the announcements on their homepage.
- **Share of products on sale, last 90 days**: a line from the daily feed reads, so a shop with a high list price that is always on offer shows as such.
- **Shipping and terms**: free shipping from, shipping costs, where they ship, delivery times, minimum order, returns and payments, each with a link to the page that says so. *Not found on their pages* means the pages read do not say.
- **Most discounted right now**: their products with the biggest discount at the last feed read.

A fact comes only from the pages read: the AI is told to say *not found* rather than guess. When a website blocks the browser or does not answer, the dossier keeps its last content and the header shows the error.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Dashboard:</b> <b>Masters</b> → <b>Competitors</b>.</li>
<li><b>Add one:</b> <b>Masters</b> → <b>Competitors</b> → <b>Add competitor</b>.</li>
<li><b>Review matches:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → tick or cross on each row.</li>
<li><b>Price comparison:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → <b>Confirmed</b>.</li>
<li><b>One competitor:</b> <b>Masters</b> → <b>Competitors</b> → <b>Competitors</b> → click its name.</li>
<li><b>Add a price checked by hand:</b> open the competitor → <b>Add price check</b>; the history is on the <b>Price checks</b> tab.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
<p>Competitors sit at group level with the master shops. You need group-level access to masters to see the dashboard, the lists and the competitor pages, and edit access to masters to add or edit a competitor and to review matches.</p>
</aside>
