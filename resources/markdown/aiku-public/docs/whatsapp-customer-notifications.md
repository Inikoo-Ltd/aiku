---
title: WhatsApp customer notifications
summary: Send order updates and reminders on WhatsApp, next to the emails: what each WhatsApp outbox sends, how to switch one on, and why a message might not go.
date: 2026-11-18
tags: whatsapp, notifications, outboxes, orders
category: help-desk
series: WhatsApp
order: 4
help_routes: grp.org.shops.show.dashboard.comms.customer_notification_outboxes, grp.org.shops.show.dashboard.comms.whatsapp_outboxes
---

<aside class="tldr">
A <b>WhatsApp outbox</b> sends one kind of customer notification, such as Order dispatched, on WhatsApp. It works on its own, next to the email of the same name. To switch one on: give it an <b>approved Utility template</b>, then turn it <b>Active</b>. It only reaches customers who <b>opted in to WhatsApp updates</b> and have a phone number we can send to.
</aside>

## Where to find them

In your shop, go to **Comms → Customer Notifications** and open the **WhatsApp** tab. Each row is one outbox:

| Column | What it tells you |
| --- | --- |
| Template | The WhatsApp template it sends, or *No template* |
| Status | **Active**, **Not sending** or **Off** (see below) |
| Sent 1w / Failed 1w | Messages sent and failed in the last 7 days |

Click a row to open the outbox.

## What each outbox sends

| Outbox | When it sends |
| --- | --- |
| Order confirmation | When an order is placed |
| Order dispatched | When an order is dispatched, with the tracking of each parcel |
| Order payment reminder | When a bank transfer order is still unpaid |
| Out of stock in order notification | Every few hours, about products in an order that ran out |
| Out of stock notification | Once a day, when a product the customer asked about is back in stock |
| Pre-order update | When a pre-order changes: balance due, new dispatch date, cancelled |
| Credit balance notification for customer | When the customer's credit balance changes |
| Chat notification to customer | When staff reply in chat and the customer has left the website |
| Review reminder | Once a day, a set number of days after dispatch |
| Gold reward reminder | Once a day, a set number of days after the customer's last invoice |

**WhatsApp and email are separate.** Switching the email off does not stop the WhatsApp message, and the other way round. If both are on, the customer gets both.

## Switching an outbox on

Every outbox starts **Off**. To switch one on:

1. **Get a template approved.** Write it under **Chat → WhatsApp templates** in the **Utility** category and submit it. Meta usually approves within minutes.
2. **Open the outbox** and choose the template under **WhatsApp template**. Only approved templates can be chosen. The **Preview** shows what the customer will read.
3. **Fill in the timing**, for the outboxes that need it:
   - *Out of stock in order notification:* **Hours between runs**.
   - *Review reminder:* **Days after dispatch**.
   - *Gold reward reminder:* **Days after the last invoice**.
4. **Turn on Active.**

aiku will not let you switch an outbox on without an approved Utility template and its timing. Saving tells you what is missing.

### Writing a template that stays approved

- **Use the Utility category.** A Marketing template cannot be used here.
- **Keep the wording factual**, like a receipt. Promotional words ("special offer", "don't miss out") can make Meta move the template to Marketing. When that happens the outbox stops sending and shows **Not sending**.
- **Use the tags** in square brackets, such as `[Customer First Name]`, `[Order Number]`, `[Order Total]`, `[Delivery Address]` or `[Tracking Parcels]`. aiku fills them in for each customer.
- **An approved template cannot be edited.** To change the wording, write a new template, get it approved, then choose it on the outbox.

## Who receives the messages

A customer gets a WhatsApp notification only when **all** of these are true:

- The shop has a **WhatsApp number connected**. If not, the page says so at the top and nothing is sent. See [connecting WhatsApp to your shop](/docs/connecting-whatsapp-to-your-shop).
- The outbox is **Active**, with an approved Utility template.
- The customer **opted in to WhatsApp updates**. They tick the box at checkout or on their account dashboard.
- The customer has a **phone number WhatsApp can use**: their WhatsApp number if they gave one, otherwise their main phone number.

Customers who did not opt in are skipped quietly. That is expected, not an error.

## What the status means

| Status | What it means |
| --- | --- |
| Active | Switched on and sending |
| Not sending | Switched on, but the template is no longer an approved Utility template. Choose another one. |
| Off | Switched off. Nothing is sent. |

## Checking what was sent

Open an outbox to see three tabs:

- **Overview:** the settings, the template preview and the figures. **Sent** means handed to WhatsApp, **Delivered** means it reached the phone, and **Read** means the customer opened it. Customers can turn read receipts off, so Read is a minimum.
- **Sent messages:** every message, with its customer, order and result.
- **Bulk runs:** for the outboxes that send on a schedule (out of stock, reviews, gold reward), one line per run.

Each message also appears in the customer's **WhatsApp chat thread**. A failed message stays there too, with the reason, so you can see what the customer missed.

## When a message did not go

| What you see | Why, and what to do |
| --- | --- |
| Nothing at all for a customer | They did not opt in, or have no usable phone number. Check their customer record. |
| Failed: the template has more variables than are set up | Some of the template's tags are not mapped. Open the template and map every variable. |
| A dash (-) in the message | aiku had no value for that tag, for example an order with no tracking yet. The message still goes. |
| Status says Not sending | Meta moved the template to Marketing, or it is no longer approved. Choose an approved Utility template. |
| Failed with a WhatsApp error | WhatsApp refused it. See [when WhatsApp is not working](/docs/when-whatsapp-is-not-working). |

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See all WhatsApp outboxes:</b> your shop → <b>Comms → Customer Notifications</b> → <b>WhatsApp</b> tab.</li>
<li><b>Switch one on:</b> open the outbox → choose the <b>WhatsApp template</b> → fill in the timing if asked → turn on <b>Active</b>.</li>
<li><b>Write or change a template:</b> your shop → <b>Chat → WhatsApp templates</b>. Use the Utility category.</li>
<li><b>See what was sent:</b> open the outbox → <b>Sent messages</b>.</li>
</ul>
</aside>
