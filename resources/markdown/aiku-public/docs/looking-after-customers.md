---
title: Looking after customers
summary: Find your way around a shop's customer list, add a new customer, read a customer's page, and understand the states, logins and prospects that sit around it.
date: 2026-09-29
tags: crm, customers
category: crm
help_routes: grp.org.shops.show.crm.customers, grp.org.shops.show.crm.prospects, grp.org.shops.show.crm.customers.show.web_users
---

<aside class="tldr">
Each shop keeps its own <b>Customers</b> list, reachable from the shop's <b>CRM</b> section. From there you can create a customer, open a customer's page to see everything about them, manage the logins they use on the website, and — separately — keep a list of <b>Prospects</b> who have not become customers yet.
</aside>

## The customer list

Open a shop and go to **CRM → Customers**. The list shows every customer for that shop, with columns for their **Ref**, **Name**, the date they were added (**Since**), their **Last Invoice** date, their estimated **Next order** date, number of **Invoices**, and **Sales**. You can search the list and sort by any of these columns.

You can filter the list down using a global search box (it matches names, and postcodes when you type one), by **Tag**, by **Country**, and by whether a customer has ever placed an order or not. The filter panel above the list adds more, including **Due to Reorder**.

## Customers due to reorder

For every customer with at least two orders, invoiced in the last year, aiku works out how many days they usually leave between orders: the days from their first invoice to their last, divided by the number of gaps between them. Several invoices on the same day count as one order, and refunds are not orders.

Their estimated **Next order** is the date of their last invoice plus that usual gap. It shows on the customer list and on the customer's **Overview**, and is worked out again each night and whenever they are invoiced.

A customer is **due to reorder** when that date falls in the next 7 days, or has passed by less than one of their usual gaps. A customer who is later than that is slipping away rather than due. A customer with an order already submitted and on its way through the warehouse has reordered, so they are not due. Add the **Due to Reorder** filter on the customer list to see them, sort by **Next order** to see who is first, or use the same filter to choose the recipients of a mailshot.

## Reorder reminder emails

A shop can also email customers automatically when they are due to reorder. In the shop's **Comms** section open **Push** and then the **Due to reorder reminder** outbox. Design the email there and set it to active. It goes out once a day, in the afternoon.

In the email, **[Products]** shows up to five of the customer's own repeat products, the ones they are due to reorder first. Each has a picture, a link to its page on the website, the quantity they usually order and the price. **[Last Invoice Date]** is the date of their last invoice. **Days before the expected next order** sets how early the email is sent. Left empty, it is sent 7 days before the customer's estimated **Next order**.

To avoid sending the same customer several similar emails:

- A customer gets this email only once between two of their invoices. When they order again and are invoiced, the next reminder waits until they are due again.
- A customer with an order already on its way through the warehouse is not due, so they are not emailed.
- The **Gold reward reminder** emails also go to customers after their last order. A customer who got one of them in the last 7 days, or is due one in the next 7 days, is skipped for now. If they still have not reordered once the Gold reward reminders are over, they get the reorder reminder then.
- Customers who unsubscribe from the email, or whose **Reorder Reminders** subscription is switched off in **Subscriptions** on their **Overview**, are not sent it again. Customers can switch it off themselves in their account on the website too.

The outbox shows every run, with the emails sent, opened and clicked. Orders placed after a customer clicks a link in the email are credited to it in the marketing reports.

## Adding a customer

Press **Create Customer** on the list. The form is short and sits under one section, **Contact**:

- **Company** — the customer's company name, if they have one.
- **Contact name** — required. The person you deal with.
- **Email**
- **Phone**
- **Address** — a full address form, pre-filled with the shop's own country.
- **Tax number**

Saving the form creates the customer and takes you to their page.

## The customer page

Opening a customer from the list takes you to their page, which is organised into tabs:

- **Overview** — the showcase summary of the customer.
- **Timeline** — a history of activity.
- **Journey** — the customer's journey through the shop.
- **History** — an audit trail of changes.
- **Attachments** — files attached to the customer.
- **Payments**
- **Credit transactions**
- **Reorders** — the products the customer has bought on two or more different days: how many times, the average quantity, the average days between orders of that product, when they last ordered it and when they are likely to order it next. Products they are due to reorder carry a **Due** mark.
- **Favourites** — products the customer has favourited.
- **Reminders**
- **Dispatched emails** — emails aiku has sent them.
- **Offers**

From here you can also reach the customer's orders, invoices, delivery notes, returns, replacements and upcoming transactions — each has its own screen linked from the customer's page.

## Customer states and status

Every customer carries two separate readings, and both show as coloured badges.

The **state** tracks where a customer is in their relationship with the shop:

- **In Process** — still being set up.
- **Registered** — has an account but has not become a regular yet.
- **Active** — currently buying.
- **Potential Comebacks** — used to buy, has gone quiet, but may come back.
- **Dormant** — has not bought for a long time.

The **status** tracks approval:

- **Pre Registration**
- **Pending Approval**
- **Approved**
- **Rejected**
- **Banned**

A customer usually needs to be **Approved** before they can trade normally — this is a separate decision from their state.

## Web users: the customer's website logins

A customer can have more than one login to the shop's website — useful when several people at the same company need their own account. You manage these from the customer's **Web Users** screen.

Each web user has:

- **Type** — Customer or API user.
- **Username** — must be unique on that shop's website.
- **Admin** — a toggle marking this login as an admin login for the customer.
- **Password**

Press **Create Web User** from the list to add one; opening a web user shows their username as the page title and lets you edit their details.

## Addresses

A customer keeps a contact address (used for correspondence and tax purposes) and can have delivery addresses used on their orders, both captured as full address forms — country, postcode, and the rest — on the customer's own record and when creating or editing the customer.

## Prospects: people who are not customers yet

Prospects are a separate list from customers, kept under **CRM → Prospects** on the same shop. A prospect moves through its own states as you work them:

- **No contacted**
- **Contacted**
- **Fail**
- **Success**

Prospects have their own **Create Prospect** button, their own export, and their own mailshots for reaching out to them in bulk. When a prospect becomes a real customer, aiku matches them up rather than leaving two separate records behind.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See or add customers:</b> your shop → <b>CRM → Customers</b> → <b>Create Customer</b>.</li>
<li><b>Open a customer:</b> click their row to see the Overview, Timeline, Journey, History, Attachments, Payments, Credit transactions, Favourites, Reminders, Dispatched emails and Offers tabs.</li>
<li><b>Manage their website logins:</b> on the customer's page, open <b>Web Users</b> → <b>Create Web User</b>.</li>
<li><b>Work prospects:</b> your shop → <b>CRM → Prospects</b> → <b>Create Prospect</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permissions you need</strong>
To view customers you need CRM view access for that shop; to create or edit a customer, its address, or a web user, you need CRM edit access for that shop. Prospects use their own view and edit permissions, separate from customers.
</aside>
