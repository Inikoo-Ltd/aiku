---
title: Helping customers find products on your website
summary: Two switches in the website settings help trade customers find what to stock. The range advisor answers a question typed in the search box with products and a reason for each, and Keep exploring adds related searches, families and products under every search.
date: 2026-11-12
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
- **Assistant name** is the name customers see, up to 30 characters. Left empty, the website uses the name set for the whole group, which is "Merlin" unless someone changed it in **Sysadmin → Settings → AI shopping assistant**. Change it there to rename the advisor on every website that has no name of its own.
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

### Better prices and big orders

When a customer asks for a better price or a discount, or says something is too expensive, the advisor does not bargain. It shows lower-priced products of the kind they asked for and adds three things worked out from our data, never by the AI:

- whether the customer is a **Gold Reward** member and until when, or how they become one (an invoiced order makes them a member for 30 days);
- a member-price amnesty, when one is running in the shop;
- the order size from which our team calls to talk about better terms.

It then asks how much they plan to order. When the planned order reaches that size, the advisor passes it on at once, even before the customer says yes to a call, so a big order is never lost. It offers the call, asks whether to use the phone number on their account or another one and when suits them, and adds the answer to the same lead. It never says who will call and never promises a discount.

Each lead is a high priority task for the **customer services** team of the organisation, linked to the customer, with the planned amount, the phone number, the best time and a link to the conversation. Everyone in the team is told in aiku. The organisation's sales leads managers are on the task and are told by email, Slack and browser notification (each can change this on their profile). While nobody has taken the task, they are reminded after 4 hours and then once a day, on working days between 09:00 and 18:00 in the organisation's time zone.

System administrators set it up under **Organisations**: open the organisation, press **Edit** and go to **Sales leads**.

- **Sales leads manager**: who is told about every lead. Left empty, the customer services supervisors are told, and when there are none, every administrator of the organisation. Set a manager so the leads reach the right person rather than every administrator.
- **Big order from (EUR)**: the planned order size from which a call is offered, converted to each shop's currency. Left empty it is 2,000 EUR, about the largest 1 in 100 orders.
- **Sales leads Slack channel**: where each lead and its reminders are posted. The aiku bot must be a member of the channel.

### When a product is back in stock

When a customer asks when an out of stock product is coming back, the advisor looks at what is on order for it. It gives a period only when the date is reliable: a delivery on its way with an expected date still ahead, or an order placed in the last 9 months whose expected arrival is still ahead. It says "expected between 1 and 15 December", never an exact day or a quantity. Many orders have no expected date, and some old ones were never closed, so often there is no reliable date.

Then the advisor says so and offers to have our purchasing team get back to the customer. Only if the customer says yes, the question becomes a task for the organisation's purchasing people, linked to the product, with the customer, their question and what our orders show. They are told by email, Slack and browser notification and reminded while nobody has taken it, like the big order leads.

To answer, write the answer for the customer in the note when you mark the task done (or as the last message of the task before marking it done). It is translated into the customer's language, added to their conversation with the advisor and emailed to them with the shop's chat notification email, with a link back to the conversation. In a shop where that email is not switched on, customer services gets a task to send the answer instead. If the task is marked done with no answer, nothing is sent and the task says so.

System administrators set it up under **Organisations**: open the organisation, press **Edit** and go to **Restock questions**. The switch **Ask purchasing when there is no reliable date** is on by default; turned off, the advisor only answers from our orders. **Restock questions go to** chooses who gets them; left empty, they go to the organisation's buyers.

### General questions and passing to a person

Besides products, the assistant answers questions about buying from the shop: payment, delivery, returns, ordering and the customer's account. It answers from the shop's own policy pages, the shop settings, and the customer service notes and verified facts kept in the shop's chat settings, and it links the page it used. It never answers from general knowledge, and it only repeats a figure, such as a delivery time or a fee, when that figure is written in the text it used.

When it is not sure, or the question is about the customer's own order, invoice or refund, it does not guess. It says so and offers to ask the team, with one tap on a button under the answer. A **Talk to a person** link is also on the assistant page at all times.

What happens on the tap depends on the moment and on the website:

- Inside chat hours, with someone available, a chat opens in the website's chat window.
- Otherwise the question arrives as an email conversation. The customer is told when the team is back and that the answer comes by email, and their reply to that email lands in the same conversation.
- A shop with no mailbox connected gets a website chat that is answered later.
- A website with neither chat nor a mailbox offers no person.

For the chat team: these chats arrive in the normal inbox and alerts, marked with the assistant's name. Use the filter **From the AI assistant** to see only them. The assistant's conversation with the customer is at the top of the chat, so you can see what was already said. If an answer of the assistant was wrong, mark it **Wrong answer** there. These chats are not counted in the chat reply-time and AI coverage figures; they are reported on the **AI Assistant** report instead (see below).

To make the assistant answer more, open the report and look at **Questions the assistant could not answer**. Add a note that answers one of them in the shop's chat settings, and the assistant will answer it next time, within about an hour.

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
- **Passed on to the team** lists the big order leads and restock questions of the period, open ones first: the customer, the planned order or the product, whether they asked for a call or how the answer reached them, the task's status and who took it. One nobody has taken is amber and shows how many reminders went out.

**Funnel** follows the conversations step by step: products shown, clicked, added to basket and ordered, each with its share of the step before and of all conversations. **Ordered** can be higher than **Added to basket**, as it also counts products that reached the basket another way. The chart next to it shows the same steps by day, or by week for a long period.

**Where customers leave** counts the conversations by the number of questions asked. **Engagement and value** gives the average time and messages of a conversation, the average order of customers who used the assistant against the other customers, and the families a customer ordered for the first time after the assistant showed them. **Families suggested** lists the families shown most often, with how many conversations clicked, added and ordered them.

**Search on the website** covers Keep exploring: the searches of the period, those with no results, how many searches were offered suggestions and how many customers saw them, and how many searches with no results were followed by a click on a suggestion. For each kind of suggestion it shows how often it was offered, the clicks and the rate. It follows the dates, the website and the language only.

**From the search box to a question** shows how many searches were made with the **Ask** button on offer, in how many of them the offer to ask was shown, and how many conversations were opened from the search box, with the questions refused by the limits or by the budget under it.

The main figures and the funnel steps are links: they open the conversations behind them.

### General questions and hand-overs

On the **Overview** tab, **General questions and passing to a person** shows how the assistant handles questions about the shop and the hand-overs to a person. Only conversations that came after this feature was added are counted. Each figure compares with the previous period, and each is a link to the conversations behind it.

- **General questions** counts the answers about the shop (policies, delivery, returns, the account) and **Answered by the assistant** those it answered itself, without offering a person.
- **Passed to a person** counts the customers who tapped the offer to ask the team. **Hand-over rate** is that count per general question, with the share of conversations that ended in a hand-over under it.
- **AI cost of these answers** is the cost of the general answers only. **Answers marked wrong** counts the answers the chat team marked as wrong.
- **Answered from** shows what the answers drew on: policy pages, shop settings, guides, notes, verified facts or learned rules, family descriptions, or our own data.
- **Why a person was offered** compares, for each reason (not sure of the answer, a question about their own order, the customer asked for a person), how many times a person was offered with how many customers passed on.
- **How it reached the team** splits the hand-overs into live chat, email chat answered later, and website chat answered later.
- **First reply from a person** shows, for inside and outside chat hours, how many hand-overs there were, how many got a reply from a person, and the median wait for that first reply. Outside chat hours the wait includes the night.
- **After passing to a person** shows how many were replied to, how many chats are closed, and how many customers placed an order within 7 days of the hand-over. Only hand-overs older than 7 days can be judged, and the figure says how many that is.
- **Questions the assistant could not answer** lists what customers asked when the assistant was not sure, with how many times, the languages and the last time, and an **Open** link to the latest conversation. Questions are grouped only when the words are the same, so two ways of asking the same thing show as two lines.

On the **Conversations** tab, a column shows which conversations were handed over to a person, and the transcript shows, under each answer, where it came from, when a person was offered, and the chat it was passed to.

### The Conversations tab

One row per conversation: when it started, the customer, where it was opened from, the first question, the number of messages, the products shown, clicked and added, whether an order followed and for how much, and the AI cost. Search by customer or by the words of a question.

Click the first question to read the conversation. Under each answer you see the products it showed with the reason given, which ones the customer clicked, added and ordered, and the follow-up questions offered. Conversations are for staff only: only people who can see the website in aiku can open them.

