---
title: Helping customers find products on your website
summary: Two switches in the website settings help trade customers find what to stock. The range advisor answers a question typed in the search box with products and a reason for each, and Keep exploring adds related searches, families and products under every search.
date: 2026-11-10
tags: website, search, shop, crm
category: marketing
help_routes: grp.org.shops.show.web.websites.edit, grp.org.shops.show.web.analytics.shopping_assistant, grp.websites.shopping_assistant
---

<aside class="tldr">
Open <b>Website</b> in the shop menu and click the sliders icon between <b>Export</b> and <b>Workshop</b>. Two sections matter here. <b>AI shopping assistant</b> turns on the range advisor: a logged in trade customer can ask what their shop needs and gets products with a one-line reason each. <b>Search page</b> turns on <b>Keep exploring</b>: related searches, families that sell well together, best sellers, new arrivals and the customer's forgotten favourites under the search results, also when a search finds nothing. Both are off until you turn them on, for each website separately. To see what they bring in, open <b>Analytics</b> in the website menu and then <b>AI Assistant</b>.
</aside>

## Turning them on

1. Go to **Website** in the shop menu.
2. Click the sliders icon at the top right, between **Export** and **Workshop**. The website settings open.
3. Open **AI shopping assistant** or **Search page** in the list on the left.
4. Flip the switch and press the save icon next to it.

The change reaches the website within about ten minutes. Each website has its own switches, so you can try one website first.

## The range advisor

The range advisor is an assistant that suggests which products a retailer should stock. It only shows to customers who are logged in on the website of a wholesale shop. Visitors who are not logged in do not see it.

In **AI shopping assistant** there are three fields:

- **AI shopping assistant** turns it on for this website.
- **Assistant name** is the name customers see, up to 30 characters. Left empty, the website uses the name set for the whole group, which is "Aura" unless someone changed it in **Sysadmin → Settings → AI shopping assistant**. Change it there to rename the advisor on every website that has no name of its own.
- **Monthly AI budget** is the most the website may spend on answers in a calendar month, in US dollars. Left empty, it is US$30. The small "i" next to it shows what has been spent this month.

Before you turn it on for a shop for the first time, ask the help desk to prepare that shop's catalogue for it. The advisor finds products by meaning, not only by the words in the name, and that needs a one-off preparation of the shop's products.

### What the customer sees

- An **Ask** button with the assistant's name inside the search box.
- When a search reads like a question or a need, an offer to ask the advisor: in the search dropdown and at the top of the results page. A search counts as a question when it has four words or more, or a question mark. The same offer shows when a search finds nothing.
- A conversation page. The customer describes their shop and what they need, and the advisor answers with one short sentence and two tabs of products, **Must-haves** and **Add-ons to your range**. The products are the usual product cards, so the customer can add them to the basket from there, and each card carries a one-line reason such as "Popular with shops like yours in your area", "You used to order this" or "Matches what you asked for".
- An answer shows up to 8 products in each tab, and up to 12 when the customer asks for more or a wider selection.
- **Add all to basket** under each answer puts one of each product shown in the basket and leaves alone the ones already there. The customer can also ask in words ("add these to my basket") and the advisor does the same, only for products it has shown in that conversation. Quantities are changed on the product cards or in the basket.
- A customer can ask the advisor to delete the conversation. It is then cleared from their screen and they cannot open it again. The record is kept in aiku: on the **Conversations** tab its status reads **Cleared by customer**, and the transcript shows what the advisor added to the basket and that the customer asked to clear it.
- The advisor finds a product or a whole family by its code, typed loosely ("tiba 04", "bbskt"), and says when the product asked for is out of stock.
- Asked for the cheapest or the most expensive, it shows the products ordered by price. It still writes no price itself: the cards show them.
- For Christmas and gift questions it adds the products that sold mostly between September and Christmas last year, marked "Sold well last Christmas", whether or not their name says Christmas.
- Asked how fast orders are dispatched, it answers with the real figure of the shop for the last 30 days ("9 out of 10 orders were dispatched within 4 days"). Asked what shipping costs, it gives the shipping of the customer's current basket, as worked out in the basket. Both sentences are written by aiku from the data, not by the AI.
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

When the AI cost of the month reaches the **Monthly AI budget**, the advisor pauses on that website until the first day of the next month: the **Ask** button is no longer offered and new questions are not accepted. An answer already on its way still arrives. Raise the budget and the advisor is back within a minute or two. Several customers asking at the same moment can take the spend slightly over the budget.

Every conversation is kept, with the products the advisor showed, the ones the customer clicked and the ones they added to the basket within 24 hours of seeing them. Raising the quantity of a product the advisor showed is recorded too, apart from the adds. Every AI call is recorded with its cost and how long it took, and so is every question turned away by the limits above or by the budget, with the reason.

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

Each search records which suggestions were offered and whether the block came into view on the customer's screen. Every click is recorded, on a result or on a suggestion, with what kind of suggestion it was (did you mean, related or popular search, family, best sellers, new arrivals or forgotten favourites) and whether it was on the product or on a button of its card, up to 20 clicks per search. Each search also records whether the **Ask** button was on offer to that customer. Searches by staff accounts are not recorded at all, so test with a customer account that is not staff if you want to see your own clicks.

## Seeing how it performs

Go to **Website** in the shop menu, then **Analytics** in the top menu and **AI Assistant**. The page reports one website. People who look after every website of the group also find it under **WebOps → AI Assistant**, for all websites together and with a website filter.

At the top you choose the dates and can narrow the figures by language, by where the conversation was opened from (search box, profile page or chat box), by shop type and by country. Every figure is compared with the period of the same length just before, which the line under the filters names. Conversations by staff accounts are left out.

### The Overview tab

- **Conversations**, with the number of customers and how many of them came back.
- **Orders after being shown** and **Revenue of the products shown**: an order counts when the customer ordered a product the assistant showed them, within 7 days of seeing it. Only the lines of those products are added up, never the whole order, and each line is counted once. Best sellers and past favourites get reordered anyway, so read this as "ordered after being shown", not as proof the assistant caused the order.
- Under both, the **strict** figure counts only the lines the customer added to the basket straight from the assistant. It is the lowest the true number can be.
- **Conversion** is the share of conversations followed by such an order. It leaves out conversations of the last 7 days, because they can still turn into orders. An amber note says from which day the figures are not final yet.
- **AI cost**, in total, per conversation and as a share of the revenue.
- **AI budget this month** shows, for each website with the advisor on, what has been spent against its monthly budget. The bar turns amber from 80% and red with **Paused until** and the date when the budget is used up.

**Funnel** follows the conversations step by step: products shown, clicked, added to basket and ordered, each with its share of the step before and of all conversations. **Ordered** can be higher than **Added to basket**, as it also counts products that reached the basket another way. The chart next to it shows the same steps by day, or by week for a long period.

**Where customers leave** counts the conversations by the number of questions asked. **Engagement and value** gives the average time and messages of a conversation, the average order of customers who used the assistant against the other customers, and the families a customer ordered for the first time after the assistant showed them. **Families suggested** lists the families shown most often, with how many conversations clicked, added and ordered them.

**Search on the website** covers Keep exploring: the searches of the period, those with no results, how many searches were offered suggestions and how many customers saw them, and how many searches with no results were followed by a click on a suggestion. For each kind of suggestion it shows how often it was offered, the clicks and the rate. It follows the dates, the website and the language only.

**From the search box to a question** shows how many searches were made with the **Ask** button on offer, in how many of them the offer to ask was shown, and how many conversations were opened from the search box, with the questions refused by the limits or by the budget under it.

The main figures and the funnel steps are links: they open the conversations behind them.

### The Conversations tab

One row per conversation: when it started, the customer, where it was opened from, the first question, the number of messages, the products shown, clicked and added, whether an order followed and for how much, and the AI cost. Search by customer or by the words of a question.

Click the first question to read the conversation. Under each answer you see the products it showed with the reason given, which ones the customer clicked, added and ordered, and the follow-up questions offered. Conversations are for staff only: only people who can see the website in aiku can open them.

