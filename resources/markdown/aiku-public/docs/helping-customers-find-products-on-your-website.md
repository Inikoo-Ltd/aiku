---
title: Helping customers find products on your website
summary: Two switches in the website settings help trade customers find what to stock. The range advisor answers a question typed in the search box with products and a reason for each, and Keep exploring adds related searches, families and products under every search.
date: 2026-11-10
tags: website, search, shop, crm
category: marketing
help_routes: grp.org.shops.show.web.websites.edit
---

<aside class="tldr">
Open <b>Website</b> in the shop menu and click the sliders icon between <b>Export</b> and <b>Workshop</b>. Two sections matter here. <b>AI shopping assistant</b> turns on the range advisor: a logged in trade customer can ask what their shop needs and gets products with a one-line reason each. <b>Search page</b> turns on <b>Keep exploring</b>: related searches, families that sell well together, best sellers, new arrivals and the customer's forgotten favourites under the search results, also when a search finds nothing. Both are off until you turn them on, for each website separately.
</aside>

## Turning them on

1. Go to **Website** in the shop menu.
2. Click the sliders icon at the top right, between **Export** and **Workshop**. The website settings open.
3. Open **AI shopping assistant** or **Search page** in the list on the left.
4. Flip the switch and press the save icon next to it.

The change reaches the website within about ten minutes. Each website has its own switches, so you can try one website first.

## The range advisor

The range advisor is an assistant that suggests which products a retailer should stock. It only shows to customers who are logged in on the website of a wholesale shop. Visitors who are not logged in do not see it.

In **AI shopping assistant** there are two fields:

- **AI shopping assistant** turns it on for this website.
- **Assistant name** is the name customers see, up to 30 characters. Left empty, customers see "our range advisor".

Before you turn it on for a shop for the first time, ask the help desk to prepare that shop's catalogue for it. The advisor finds products by meaning, not only by the words in the name, and that needs a one-off preparation of the shop's products.

### What the customer sees

- An **Ask** button with the assistant's name inside the search box.
- When a search reads like a question or a need, an offer to ask the advisor: in the search dropdown and at the top of the results page. A search counts as a question when it has four words or more, or a question mark. The same offer shows when a search finds nothing.
- A conversation page. The customer describes their shop and what they need, and the advisor answers with one short sentence and two tabs of products, **Must-haves** and **Add-ons to your range**. The products are the usual product cards, so the customer can add them to the basket from there, and each card carries a one-line reason such as "Popular with shops like yours in your area", "You used to order this" or "Matches what you asked for".
- Under the answer, buttons with follow-up questions, a box to type another question, and **Start a new conversation**.

Normal searches by word or product code are not changed and are not slowed down.

### What it knows and what it never says

The advisor answers from four things: the shop's catalogue, the best sellers of the last 12 months among customers like the one asking, the products that customer has not tried yet, and the products they used to order and stopped. "Customers like the one asking" means the same shop type first, in the same area, then the same country, then everybody.

If the customer says what kind of shop they run, the advisor saves it as their **Shop type**. On **CRM → Customers → Shop types** it counts under **Told us**. See <a href="/docs/looking-after-customers">Looking after customers</a> for shop types.

It is built to keep to these rules:

- It only suggests products that are live on the website and in stock.
- It does not write prices, discounts or stock levels in its sentences. The price is on the product card, as everywhere else on the website.
- It says nothing about what any other customer buys, and no buyer counts or sales figures are shown.
- It answers in the language of the shop.

### Limits and what is recorded

Each customer can ask 6 questions a minute, 40 an hour and 120 a day. An answer takes a few seconds, and the page waits for it. Every answer is paid AI usage, so turn the advisor on where you want customers to use it.

Every conversation is kept, with the products the advisor showed, the ones the customer clicked and the ones they added to the basket within 24 hours of seeing them.

## Keep exploring

**Search page → Keep exploring suggestions on the search page** adds a block under the results of every search on the website, for visitors and for logged in customers. When a search finds nothing, the block sits right under "We couldn't find any suitable results", so the customer always has somewhere to go next.

From top to bottom it shows, each part only when there is something to show:

- **Did you mean**, only on a search with no results, when the word is a close spelling of a search that many customers made and that found products. "beeswax" offers "bees wax".
- **Related searches**: what other customers searched in the same visit as this search. When there are none, the heading reads **Popular searches** and lists the most searched terms of the website.
- **Sells well with**: the families set as related to the families the search found.
- Tabs of products: **Best sellers**, **New arrivals** and **Your forgotten favourites**.

### Where each suggestion comes from

- **Related and popular searches** come from the searches of the last 180 days on this website that found products. Only searches by logged in customers are counted: a term needs 5 different customers to be popular and 3 different customers to be related to another one. Terms with a digit or an @ are never shown, so product codes, order numbers and email addresses stay out.
- **Best sellers** are the products most customers bought in the last 12 months. A logged in customer gets the best sellers among customers like them, and the tab reads **Best sellers for shops like yours** only when every product in it comes from shops of their own shop type. Visitors get the best sellers of the whole shop.
- **New arrivals** are the products in their first 30 days on the website, newest first.
- **Your forgotten favourites** shows only to a logged in customer: products they ordered before and have not ordered in the last 12 months.

Every product shown is live and in stock at that moment. The sales figures behind best sellers and forgotten favourites are rebuilt every night, the popular searches once a day, and the suggestions for one search term are kept for an hour.

The block loads after the search results, so the search itself is as fast as before.

### What is recorded

The first click on a suggestion is recorded on the search, with what kind of suggestion it was: did you mean, related or popular search, family, best sellers, new arrivals or forgotten favourites. Searches by staff accounts are not recorded at all, so test with a customer account that is not staff if you want to see your own clicks.
