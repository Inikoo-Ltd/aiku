---
title: Connecting your AI assistant to aiku
summary: Connect ChatGPT, Claude, Gemini or Perplexity to aiku with your own login, then ask it about sales, stock, orders, customers and your team, and, where an administrator allows it, have it make changes for you. How to connect, what it can read, what it can change, ideas to try and what to do when connecting fails.
date: 2026-11-28
tags: profile, ai, access
category: profile
---

<aside class="tldr">
An administrator switches on <b>Can connect AI assistant</b> on your user. Then you add aiku to your assistant with the address <code>https://app.aiku.io/mcp/aiku</code>, click <b>Connect</b>, log in to aiku if it asks and press <b>Authorize</b>. Your assistant reads aiku as you: only the shops, organisations and warehouses you can see. It only reads, unless an administrator also gives it one of the write permissions. Your profile, tab <b>AI access</b>, shows exactly what yours can do, with examples to copy.
</aside>

## Before you start

**Ask an administrator to switch it on.** On your user, under <b>Access</b>, they turn on <b>Can connect AI assistant</b>. Without it, the <b>AI access</b> tab is missing from your profile, and the connect page says you cannot use the AI assistant with the <b>Authorize</b> button grey.

**Use your own aiku login and your own assistant account.** Everything your assistant reads or changes is done as you and recorded under your name. A colleague's assistant sees what they can see, not what you can, and their name goes on what you do.

**Check what yours can do.** Open your profile (click your picture, then <b>My profile</b>) and the tab <b>AI access</b>. It has the address to copy, the steps for each assistant, and under <b>What your assistant can do</b> each permission you have, with prompts you can copy into your assistant.

## Connecting

You connect once. Your assistant stays connected until you remove it.

### ChatGPT

1. In a company workspace, the <b>Aiku</b> app is already added for everyone: open it under <b>Apps</b> and click <b>Connect</b>. On a personal plan, go to <b>Settings → Connectors</b>, create a connector named <b>Aiku</b> with the address above (if there is no create option, switch on <b>Developer mode</b> under <b>Advanced</b>).
2. A window opens on aiku. If you are not logged in to aiku in that browser, aiku shows its login page with a note that you are connecting your assistant. Log in, and enter your 2FA code if you use one.
3. aiku shows <b>Authorize ChatGPT</b> with your username. Check it is you and press <b>Authorize</b>. The window closes.
4. In a new chat, switch on the Aiku app and ask your question.

### Claude

1. In claude.ai, click your initials, then <b>Settings → Connectors → Add custom connector</b>.
2. Name it <b>Aiku</b>, paste the address and click <b>Connect</b>.
3. Log in and press <b>Authorize</b> as in steps 2 and 3 above.

### Gemini and Perplexity

Gemini works with a personal Google account only: <b>Settings & help → Connected apps</b>, add a custom app with the address, then log in and authorize. Perplexity (paid plans) asks for a key instead of a login: create one on your profile under <b>AI access → Access keys</b> and paste it there. A key works like a password; revoke it on the same page if you stop using it.

## What it can read

With <b>Can connect AI assistant</b> on, your assistant answers questions about everything you can see in aiku. Talk to it in your own language, the way you would ask a colleague who knows aiku well.

**Sales and products**

> *"How did my shop sell last week compared with the week before? Which families grew most and which fell?"*

> *"Which products sell well but have less than two weeks of stock left?"*

> *"Which products had the most refunds this quarter?"*

> *"Which active products have no picture?"*

**Orders and customers**

> *"Show me order AW-123456: where is it and when was it dispatched?"*

> *"Find the customer with the email customer@example.com: what did they order this year and is anything still open?"*

> *"How many customers who registered in September have placed a first order?"*

**Marketing and websites**

> *"Which offers brought the most orders last month, and which did nobody use?"*

> *"How did last week's newsletter do: opens, clicks and orders?"*

> *"What are customers saying in their reviews this month? Summarise the complaints."*

**Warehouse and team**

> *"How many delivery notes are waiting to be picked in the warehouse, and how fast did we pack yesterday?"*

> *"Which stock has not sold for six months and how much is it worth?"*

> *"What would each carrier charge to send a 4 kg parcel to Ireland?"*

**Getting help from colleagues**

> *"Raise a ticket: the label printer in packing prints blank labels since this morning."*

> *"Ask the warehouse to count location A-12-03 and tell me when it is done."*

> *"Send the warehouse manager a message that the pallet for the Spanish partner is ready."*

<b>Super intelligence 🧠</b> is a separate permission for a few people trusted with all company data. Their assistant can ask any question of everything in aiku, in every shop and organisation, including data they cannot see in aiku, and the system records (errors, slow pages, background jobs). It still only reads.

## What it can change

Each kind of change has its own permission, under <b>Through their AI assistant</b> on your user. An administrator turns on only the ones your work needs. You still need the same edit permission in aiku: a permission never lets your assistant do something you could not do yourself.

Before changing anything your assistant shows you what will change and waits for your yes. Every change is kept in the <b>AI changes</b> log with your name and the exact words you used, and most can be undone there.

### Goods

**Can discontinue SKOs.** See everything that still depends on a SKO (stock, open purchase orders, dropshipping customers, live pages, open orders), then change it to discontinuing, discontinued, suspended or back to active, and stop or restart ordering it from the supplier.

> *"What still depends on SKO ABC-01 if I discontinue it?"*

> *"Discontinue ABC-01 in every organisation except Spain, and stop ordering it from the supplier."*

**Can edit trade units.** Change names, weights, ingredients, origin, customs and safety codes, which reach every product selling the trade unit, give a barcode to a trade unit that has none, and record packaging weighed in the warehouse.

> *"Set the net weight of ABC-01 to 250 g and its customs code to 3307 41 00."*

### Catalogue

**Can change master prices.** Save master prices, which every shop following the master sells at, and put master products on pre-order.

> *"Show the master prices of the Incense Cones family with cost and margin, then raise them all by 5%."*

**Can change website content and redirects.** Change the related products ("Sells well with") on family pages, move a product to another family, and point the redirects of closed pages to live pages.

> *"The old Gift Sets page is closed: point its redirects to the Gifts department."*

**Info websites.** Group webmasters can change the information-only websites (pages, header, footer). Nothing is saved until you have seen the preview.

> *"On aw.gifts, change the opening hours in the footer to 9:00 to 17:00 and show me the preview."*

### Procurement

**Can add to the manufacturing hub shopping list.** See what to order from the hub, most urgent first, and add SKOs in whole batches. The hub sees the list only once someone sends it.

> *"What should we order from the manufacturing hub this week? Add the ten most urgent."*

**Can place orders to the manufacturing hub, partners and suppliers.** Send the hub basket, place rescue orders with partners and submit purchase orders to suppliers. Orders cannot be undone from the AI changes log.

> *"Place a purchase order with our incense supplier for everything with less than four weeks of stock."*

**Can save stock delivery costs.** Save shipping, duty and extra costs of stock deliveries, which decide the value of the stock and the margins.

> *"Which stock deliveries are still missing their costs? Delivery SD-1234: shipping was 820 euros, save it."*

### Warehouse

**Can put SKOs on locations.** Add SKOs to warehouse locations, many at a time. It never moves stock, changes a quantity or takes a SKO off a location.

> *"Put these twenty SKOs on location B-04-02."*

### Production

**Can set up artefacts, raw materials and recipes.** Create and edit artefacts, raw materials and tasks, and replace recipe steps.

> *"Show me the recipe of artefact ABC-01, then add a labelling step after filling at 120 units per hour."*

### Customer service

**Can serve customers.** Read the chats waiting for you with everything aiku knows and save draft replies for you to send. On a chat you name, it can also reply, close the chat, settle a claim and change an order not yet packed. See [Answering customers with your AI assistant](/docs/answering-customers-with-your-ai-assistant).

> *"Which chats are waiting for me? Open the oldest one and draft a reply asking for photos of the broken mugs."*

### Settings

**Can change who gets Ask CRM and Ask warehouse questions.** Add and remove staff on those lists in one shop or every shop of an organisation.

> *"Add the new customer service colleague to Ask CRM in every shop of the organisation."*

<aside class="warning">
Permissions that change what customers pay, spend money or change the value of stock are for the people who make those decisions. Ask your manager before asking an administrator for one.
</aside>

## Getting good answers

- **Say which shop and which dates.** *"The UK shop, 1 to 30 November"* beats *"recently"*.
- **Ask for a table when you want to compare.** *"As a table with last year next to it."*
- **Ask it to show its working.** *"Which figures did you use?"* It reads the same numbers you see in aiku, so you can check them there.
- **Read before you say yes.** For a change, it lists exactly what will change. Say no or correct it if anything looks wrong.
- **It knows what you can see, not more.** If it says it cannot see a shop, you do not have access to it in aiku either.

## When connecting does not work

- **A page saying "This link is not complete".** You opened the aiku connect address yourself. Close it, go back to your assistant and click <b>Connect</b> there.
- **A page saying "This connect page has expired".** It was already used or left open too long. Close it and click <b>Connect</b> in your assistant again.
- **"You cannot use the AI assistant" and a grey Authorize button.** <b>Can connect AI assistant</b> is off on your user. Ask an administrator.
- **The connect page shows somebody else's username.** Your browser is logged in to aiku as them. Log out of aiku in that browser, then click <b>Connect</b> again.
- **Asked for a 2FA code.** You use two-factor login, so aiku asks for your code before connecting. Enter the code from your authenticator app.
- **The assistant says "Couldn't connect to the server" after you authorized.** Your access was switched off, or you authorized as a user without access. Ask an administrator, then remove Aiku from your assistant and connect again.

If it still fails, raise a ticket and say which assistant you used and what the page showed. Administrators can see every connect attempt and its result in <b>System Administration → AI analytics → Connections</b>.
